<?php

namespace Database\Seeders;

use App\Models\AiImage;
use App\Models\AiImageCategory;
use App\Models\AiVideo;
use App\Models\AiVideoCategory;
use App\Models\BabyBackdrop;
use App\Models\BabyBackdropCategory;
use App\Models\DynamicFrame;
use App\Models\DynamicFrameCategory;
use App\Models\Frame;
use App\Models\FrameCategory;
use App\Models\MobileApp;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\VideoTemplate;
use App\Models\VideoTemplateCategory;
use App\Support\Media;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Fills every module with 20 items plus their files. Files follow the
 * category-based layout: upload/{module}/{Category Name}/.../{file}.
 * Safe to re-run: it clears its own demo content first.
 */
class DemoContentSeeder extends Seeder
{
    private const PER_MODULE = 20;
    private const PALETTE = [
        [99, 102, 241], [236, 72, 153], [20, 184, 166], [245, 158, 11],
        [14, 165, 233], [139, 92, 246], [16, 185, 129], [239, 68, 68],
    ];

    /** @var MobileApp[] */
    private array $apps = [];

    public function run(): void
    {
        $this->cleanup();
        $this->seedApps();

        $this->seedFrames();
        $this->seedSimple('template', TemplateCategory::class, Template::class);
        $this->seedDynamicFrames();
        $this->seedGallery('baby-backdrop', BabyBackdropCategory::class, BabyBackdrop::class, 'backdrop', orientation: true);
        $this->seedVideoTemplates();
        $this->seedAiImages();
        $this->seedAiVideos();

        $this->command?->info('Demo content: 20 items per module created (category-based paths).');
    }

    // ── Apps ─────────────────────────────────────────────────────────────────

    private function seedApps(): void
    {
        foreach ([
            ['Baby Photo App', 'com.ngd.baby'],
            ['Birthday Frames', 'com.ngd.birthday'],
            ['AI Studio', 'com.ngd.aistudio'],
        ] as [$name, $pkg]) {
            $this->apps[] = MobileApp::updateOrCreate(['package_name' => $pkg], ['app_name' => $name, 'status' => true]);
        }
    }

    private function appIdsFor(int $index): array
    {
        $ids = [$this->apps[0]->id];
        if ($index % 2 === 0) {
            $ids[] = $this->apps[1]->id;
        }
        if ($index % 3 === 0) {
            $ids[] = $this->apps[2]->id;
        }

        return array_values(array_unique($ids));
    }

    // ── Categories ───────────────────────────────────────────────────────────

    /** @return array<int,object> each: {model, name} */
    private function makeCategories(string $module, string $catModel, int $count, bool $orientation = false): array
    {
        $theme = ['Birthday', 'Wedding', 'Festival', 'Anniversary', 'Baby', 'Love', 'Nature', 'Travel', 'Party', 'Vintage'];
        $cats  = [];
        for ($i = 1; $i <= $count; $i++) {
            $name  = $theme[($i - 1) % count($theme)] . ' ' . $i;
            $attrs = [
                'name'   => $name,
                'image'  => $this->webp($module, $name, '', 'cover.webp', substr($name, 0, 10), $i),
                'status' => true,
            ];
            if ($orientation) {
                $attrs['orientation'] = $i % 2 ? 'portrait' : 'landscape';
            }
            $cat = $catModel::create($attrs);
            $cat->apps()->sync($this->appIdsFor($i));
            $cats[] = $cat;
        }

        return $cats;
    }

    // ── Modules ──────────────────────────────────────────────────────────────

    private function seedSimple(string $module, string $catModel, string $itemModel): void
    {
        $cats = $this->makeCategories($module, $catModel, 5);
        for ($i = 1; $i <= self::PER_MODULE; $i++) {
            $cat  = $cats[($i - 1) % count($cats)];
            $data = [
                'category_id' => $cat->id,
                'frame'       => $this->webp($module, $cat->name, 'frame', "frame-{$i}.webp", "F{$i}", $i),
                'thumbnail'   => $this->webp($module, $cat->name, 'thumbnail', "thumb-{$i}.webp", "T{$i}", $i + 1, 200, 200),
                'status'      => $i % 7 !== 0,
            ];
            if ($module === 'template') {
                $data['count'] = ($i % 5) + 1;
            }
            $itemModel::create($data);
        }
    }

    private function seedDynamicFrames(): void
    {
        $cats   = $this->makeCategories('dynamic-frame', DynamicFrameCategory::class, 5);
        $ratios = DynamicFrame::RATIOS;

        for ($i = 1; $i <= self::PER_MODULE; $i++) {
            $cat    = $cats[($i - 1) % count($cats)];
            $layout = [
                'path'   => "frame_{$i}.png",
                'layers' => [
                    ['type' => 'image', 'name' => 'background', 'width' => 1080, 'height' => 1080],
                    ['type' => 'text', 'name' => 'title', 'color' => '#FFFFFF', 'text' => "Sample {$i}"],
                    ['type' => 'photo', 'name' => 'user_photo', 'x' => 120, 'y' => 200],
                ],
            ];
            $json = json_encode($layout, JSON_UNESCAPED_SLASHES);

            DynamicFrame::create([
                'category_id'  => $cat->id,
                'aspect_ratio' => $ratios[$i % count($ratios)],
                'zip'          => $this->zip('dynamic-frame', $cat->name, 'zip', "layout-{$i}.zip", ['layout.json' => $json]),
                'thumbnail'    => $this->webp('dynamic-frame', $cat->name, 'thumbnail', "df-{$i}.webp", "DF{$i}", $i, 300, 375),
                'json'         => $json,
                'is_paid'      => $i % 3 === 0,
                'status'       => $i % 7 !== 0,
            ]);
        }
    }

    /** Frames: one record per category, each holding several frame+thumbnail PAIRS. */
    private function seedFrames(): void
    {
        $cats = $this->makeCategories('frame', FrameCategory::class, self::PER_MODULE);
        foreach ($cats as $i => $cat) {
            $pairs = [];
            $n = 3 + ($i % 4); // 3–6 frames each
            for ($k = 1; $k <= $n; $k++) {
                $frame = $this->webp('frame', $cat->name, 'frame', "frame-{$k}.webp", "F{$k}", $i + $k, 300, 400);
                $thumb = $this->webp('frame', $cat->name, 'thumbnail', "thumb-{$k}.webp", "T{$k}", $i + $k + 1, 200, 266);
                $pairs[] = ['frame' => $frame, 'thumbnail' => $thumb];
            }
            Frame::create(['category_id' => $cat->id, 'images' => $pairs, 'status' => ($i + 1) % 7 !== 0]);
        }
    }

    /** Gallery modules: one record per category holding several images (20 categories = 20 rows). */
    private function seedGallery(string $module, string $catModel, string $itemModel, string $noun, bool $orientation = false): void
    {
        $cats = $this->makeCategories($module, $catModel, self::PER_MODULE, $orientation);
        foreach ($cats as $i => $cat) {
            $images = [];
            $n = 3 + ($i % 4); // 3–6 images each
            for ($k = 1; $k <= $n; $k++) {
                $images[] = $this->webp($module, $cat->name, '', "{$noun}-{$k}.webp", strtoupper($noun[0]) . $k, $i + $k, 300, 400);
            }
            $itemModel::create(['category_id' => $cat->id, 'images' => $images, 'status' => ($i + 1) % 7 !== 0]);
        }
    }

    private function seedVideoTemplates(): void
    {
        $cats = $this->makeCategories('video-template', VideoTemplateCategory::class, 5);

        for ($i = 1; $i <= self::PER_MODULE; $i++) {
            $cat      = $cats[($i - 1) % count($cats)];
            $lottie   = json_encode(['v' => '5.7.4', 'fr' => 30, 'ip' => 0, 'op' => 60, 'w' => 1080, 'h' => 1920, 'layers' => []]);
            $template = json_encode(['elements' => [
                ['size' => [1080, 1920], 'editable' => 1],
                ['size' => [500, 500], 'editable' => 1],
                ['size' => [400, 200], 'editable' => 0],
            ]]);

            $vt = VideoTemplate::create([
                'category_id'       => $cat->id,
                'title'             => 'Animated Template ' . $i,
                'thumbnail'         => $this->webp('video-template', $cat->name, 'thumbnail', "vt-{$i}.webp", "VT{$i}", $i, 300, 533),
                'zip'               => $this->zip('video-template', $cat->name, 'zip', "vt-{$i}.zip", ['res/data.json' => $lottie, 'template.json' => $template]),
                'zip_original_name' => "vt-{$i}.zip",
                'json'              => $template,
                'total_image_count' => 3,
                'total_editable'    => 2,
                'width'             => '1080',
                'height'            => '1920',
                'is_paid'           => $i % 4 === 0,
                'status'            => $i % 7 !== 0,
            ]);

            // Extract to upload/video-template/{Category}/extracted/{id}/... (matches the controller).
            $extractRel = 'upload/video-template/' . Media::folder($cat->name) . '/extracted/' . $vt->id;
            $abs = public_path($extractRel);
            File::ensureDirectoryExists($abs . '/res');
            File::put($abs . '/res/data.json', $lottie);
            File::put($abs . '/template.json', $template);
            File::put($abs . '/res/data.html', $this->lottiePlayer());
            $vt->update(['extract_dir' => $extractRel, 'video_html_path' => 'res/data.html']);
        }
    }

    private function seedAiImages(): void
    {
        $cats    = $this->makeCategories('ai-image', AiImageCategory::class, 5);
        $prompts = [
            'A watercolor portrait of a child under soft morning light',
            'Cyberpunk city skyline at night, neon reflections on wet streets',
            'A cozy cabin in a snowy forest, warm window glow',
            'Studio product shot of a perfume bottle on marble',
            'Fantasy dragon flying over misty mountains, cinematic',
        ];
        for ($i = 1; $i <= self::PER_MODULE; $i++) {
            $cat = $cats[($i - 1) % count($cats)];
            AiImage::create([
                'category_id' => $cat->id,
                // upload/ai-image/{Category Name}/{file}
                'image'       => $this->webp('ai-image', $cat->name, '', "image-{$i}.webp", "AI{$i}", $i),
                'prompt'      => $prompts[($i - 1) % count($prompts)] . " (variation {$i})",
                'status'      => $i % 7 !== 0,
            ]);
        }
    }

    private function seedAiVideos(): void
    {
        $cats     = $this->makeCategories('ai-video', AiVideoCategory::class, 5);
        $sample   = base_path('tests/Fixtures/sample.mp4');
        $hasVideo = File::exists($sample);

        for ($i = 1; $i <= self::PER_MODULE; $i++) {
            $cat      = $cats[($i - 1) % count($cats)];
            $videoName = "clip-{$i}.mp4";
            if ($hasVideo) {
                $dest = public_path('upload/ai-video/' . Media::folder($cat->name) . '/video/' . $videoName);
                File::ensureDirectoryExists(dirname($dest));
                File::copy($sample, $dest);
            }

            AiVideo::create([
                'category_id' => $cat->id,
                'video'       => $videoName,
                'thumbnail'   => $this->webp('ai-video', $cat->name, 'thumbnail', "vthumb-{$i}.webp", "V{$i}", $i, 320, 180),
                'prompt'      => "Make the subject dance in scene {$i}",
                'status'      => $i % 7 !== 0,
            ]);
        }
    }

    // ── File builders ────────────────────────────────────────────────────────

    private function path(string $module, string $catName, string $sub, string $file): string
    {
        return 'upload/' . $module . '/' . Media::folder($catName) . ($sub !== '' ? '/' . $sub : '') . '/' . $file;
    }

    private function webp(string $module, string $catName, string $sub, string $file, string $label, int $colorIndex, int $w = 400, int $h = 400): string
    {
        $rel = $this->path($module, $catName, $sub, $file);
        $abs = public_path($rel);
        File::ensureDirectoryExists(dirname($abs));

        $im = imagecreatetruecolor($w, $h);
        [$r, $g, $b] = self::PALETTE[$colorIndex % count(self::PALETTE)];
        imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $b));
        imagestring($im, 5, 14, (int) ($h / 2) - 8, $label, imagecolorallocate($im, 255, 255, 255));
        imagewebp($im, $abs, 80);
        imagedestroy($im);

        // The database stores only the filename; the path is rebuilt by the models.
        return $file;
    }

    /** @param array<string,string> $entries */
    private function zip(string $module, string $catName, string $sub, string $file, array $entries): string
    {
        $rel = $this->path($module, $catName, $sub, $file);
        $abs = public_path($rel);
        File::ensureDirectoryExists(dirname($abs));

        $zip = new ZipArchive();
        $zip->open($abs, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $file;
    }

    private function cleanup(): void
    {
        foreach ([
            Frame::class, Template::class, DynamicFrame::class, BabyBackdrop::class,
            VideoTemplate::class, AiImage::class, AiVideo::class,
        ] as $m) {
            $m::query()->delete();
        }
        DB::table('app_category')->delete();
        foreach ([
            FrameCategory::class, TemplateCategory::class, DynamicFrameCategory::class,
            BabyBackdropCategory::class, VideoTemplateCategory::class, AiImageCategory::class, AiVideoCategory::class,
        ] as $m) {
            $m::query()->delete();
        }
        foreach (['frame', 'template', 'dynamic-frame', 'baby-backdrop', 'video-template', 'ai-image', 'ai-video'] as $dir) {
            File::deleteDirectory(public_path('upload/' . $dir));
        }
    }

    private function lottiePlayer(): string
    {
        return <<<'HTML'
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>html,body{margin:0;height:100%;background:#fff;overflow:hidden}#a{position:absolute;inset:0}</style></head>
<body><div id="a"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
<script>lottie.loadAnimation({container:document.getElementById('a'),renderer:'svg',loop:true,autoplay:true,path:'data.json'});</script>
</body></html>
HTML;
    }
}
