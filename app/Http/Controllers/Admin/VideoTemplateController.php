<?php

namespace App\Http\Controllers\Admin;

use App\Models\BaseItem;
use App\Models\VideoTemplate;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Video templates (ASM044 style). The uploaded zip is extracted to a public
 * folder; its data.html (or a generated Lottie player for data.json) becomes the
 * template's video_url, and template.json fills in counts / size / json.
 */
class VideoTemplateController extends BaseItemController
{
    protected string $module = 'video-template';

    protected array $searchColumns = ['title', 'zip_original_name'];

    protected array $toggleable = ['status', 'is_paid'];

    /** Never extract server-side scripts into the public folder. */
    private const BLOCKED_EXT = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'cgi', 'pl', 'py', 'sh', 'exe', 'bat', 'htaccess', 'htpasswd', 'ini'];

    protected function rules(Request $request, ?BaseItem $item): array
    {
        return [
            'category_id' => 'required|exists:' . $this->categoryTable() . ',id',
            'title'       => 'required|string|max:191',
            'zip'         => $this->fileRule($item, 'file|mimes:zip|max:204800'),
            'thumbnail'   => $this->fileRule($item, 'image|mimes:webp|max:10240'),
            'is_paid'     => 'nullable|boolean',
            'status'      => 'nullable|boolean',
        ];
    }

    protected function imageFields(): array
    {
        return ['thumbnail'];
    }

    protected function persist(Request $request, BaseItem $item): void
    {
        /** @var VideoTemplate $item */
        $zipFile = $request->file('zip');
        $entries = $zipFile ? $this->inspectZip($zipFile->getRealPath()) : null;

        $old = $this->oldCategoryId($item);
        $item->category_id = $request->integer('category_id');
        $item->title       = trim($request->input('title'));
        $item->is_paid     = $request->boolean('is_paid');
        $item->status      = $request->boolean('status');
        $this->putFile($request, $item, 'thumbnail', 'thumbnail', $old);

        if ($zipFile) {
            $item->zip_original_name = $zipFile->getClientOriginalName();
            $this->putFile($request, $item, 'zip', 'zip', $old);
            $item->save(); // need an id for the extract folder
            $this->extract($item, $entries);
        } elseif ($old && $old !== (int) $item->category_id && $item->extract_dir) {
            // Category changed without a new zip: move the extracted player folder too.
            $newExtract = 'upload/' . $this->dir((int) $item->category_id) . '/extracted/' . $item->id;
            Media::moveDir($item->extract_dir, $newExtract);
            $item->extract_dir = $newExtract;
        }

        $item->save();
    }

    /**
     * Serve a file from the template's extracted folder (data.html, data.json, images ...)
     * under an admin URL, so previews never reveal the real upload path.
     */
    public function player(int $id, string $path): BinaryFileResponse
    {
        $item = VideoTemplate::findOrFail($id);
        abort_unless($item->extract_dir, 404);

        $base = realpath(public_path($item->extract_dir));
        $file = $base ? realpath($base . '/' . $path) : false;
        abort_unless($file && is_file($file) && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $base) . '/'), 404);

        $mime = [
            'html' => 'text/html; charset=utf-8', 'json' => 'application/json', 'js' => 'text/javascript',
            'css' => 'text/css', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ttf' => 'font/ttf',
            'otf' => 'font/otf', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4',
        ][strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';

        return response()->file($file, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    protected function cleanup(BaseItem $item): void
    {
        parent::cleanup($item);
        Media::deleteDir($item->extract_dir);
    }

    /** Validate the archive up front and return the list of safe entries to extract. */
    private function inspectZip(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['zip' => 'The file is not a readable zip archive.']);
        }

        $safe = [];
        $hasPlayer = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '__MACOSX')) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, self::BLOCKED_EXT, true) || str_starts_with(basename($name), '.ht')) {
                continue;
            }
            $base = strtolower(basename($name));
            if ($base === 'data.html' || $base === 'data.json') {
                $hasPlayer = true;
            }
            $safe[] = $zip->getNameIndex($i);
        }
        $zip->close();

        if (!$hasPlayer) {
            throw ValidationException::withMessages(['zip' => 'The zip must contain a data.html or data.json (Lottie) file.']);
        }

        return $safe;
    }

    private function extract(VideoTemplate $item, array $entries): void
    {
        Media::deleteDir($item->extract_dir);

        $folder = Media::folder($item->category?->name ?? 'uncategorized');
        $rel = 'upload/video-template/' . $folder . '/extracted/' . $item->id;
        $abs = public_path($rel);
        if (File::exists($abs)) {
            File::deleteDirectory($abs);
        }
        File::makeDirectory($abs, 0775, true, true);

        $zip = new ZipArchive();
        // $item->zip stores only the filename; rebuild the full path to the uploaded zip.
        $zip->open(public_path($item->fileRelPath('zip')));
        $zip->extractTo($abs, $entries);
        $zip->close();

        $item->extract_dir     = $rel;
        $item->video_html_path = $this->resolveVideoHtml($abs);
        $this->applyTemplateJson($item, $abs);
    }

    /** Use the zip's data.html, or generate a Lottie player next to data.json. */
    private function resolveVideoHtml(string $root): ?string
    {
        if ($html = $this->findFile($root, 'data.html')) {
            return $html;
        }
        if (!$json = $this->findFile($root, 'data.json')) {
            return null;
        }

        $dir = dirname($json);
        $dir = ($dir === '.' || $dir === '') ? '' : $dir . '/';
        File::put($root . '/' . $dir . 'data.html', $this->lottiePlayer());

        return $dir . 'data.html';
    }

    private function findFile(string $root, string $filename): ?string
    {
        $rootN = rtrim(str_replace('\\', '/', $root), '/') . '/';
        foreach (File::allFiles($root) as $f) {
            if (strtolower($f->getFilename()) === $filename) {
                return ltrim(str_replace($rootN, '', str_replace('\\', '/', $f->getPathname())), '/');
            }
        }

        return null;
    }

    private function applyTemplateJson(VideoTemplate $item, string $root): void
    {
        $rel = $this->findFile($root, 'template.json');
        if (!$rel) {
            return;
        }
        $raw  = File::get($root . '/' . $rel);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return;
        }

        $item->json = $raw;
        $elements   = is_array($data['elements'] ?? null) ? $data['elements'] : [];
        $w = $h = $editable = 0;
        foreach ($elements as $e) {
            $size = $e['size'] ?? [0, 0];
            $w = max($w, (float) ($size[0] ?? 0));
            $h = max($h, (float) ($size[1] ?? 0));
            if ((int) ($e['editable'] ?? 0) === 1) {
                $editable++;
            }
        }
        $item->total_image_count = count($elements);
        $item->total_editable    = $editable;
        $item->width             = $w ? (string) (int) round($w) : ($data['w'] ?? null);
        $item->height            = $h ? (string) (int) round($h) : ($data['h'] ?? null);
    }

    private function lottiePlayer(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<style>html,body{margin:0;height:100%;background:#fff;overflow:hidden}#animation{position:absolute;inset:0}</style>
</head>
<body>
<div id="animation"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
<script>
  lottie.loadAnimation({container: document.getElementById('animation'), renderer: 'svg', loop: true, autoplay: true, path: 'data.json'});
</script>
</body>
</html>
HTML;
    }
}
