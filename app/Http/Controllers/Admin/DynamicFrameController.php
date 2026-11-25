<?php

namespace App\Http\Controllers\Admin;

use App\Models\BaseItem;
use App\Models\DynamicFrame;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Dynamic frame = layered frame shipped as a .zip with a JSON layout inside
 * (same flow as ASM013 posts). The JSON is extracted and stored so the API
 * can return it directly.
 */
class DynamicFrameController extends BaseItemController
{
    protected string $module = 'dynamic-frame';

    protected array $searchColumns = ['aspect_ratio'];

    protected array $toggleable = ['status', 'is_paid'];

    protected function rules(Request $request, ?BaseItem $item): array
    {
        return [
            'category_id'  => 'required|exists:' . $this->categoryTable() . ',id',
            'aspect_ratio' => ['required', Rule::in(DynamicFrame::RATIOS)],
            'zip'          => $this->fileRule($item, 'file|mimes:zip|max:102400'),
            'thumbnail'    => $this->fileRule($item, 'image|mimes:webp|max:10240'),
            'is_paid'      => 'nullable|boolean',
            'status'       => 'nullable|boolean',
        ];
    }

    protected function imageFields(): array
    {
        return ['thumbnail'];
    }

    protected function persist(Request $request, BaseItem $item): void
    {
        if ($request->hasFile('zip')) {
            $json = $this->extractJson($request->file('zip')->getRealPath());
            if ($json === null) {
                throw ValidationException::withMessages(['zip' => 'The zip must contain a valid .json layout file.']);
            }
            $item->json = $json;
        }

        $old = $this->oldCategoryId($item);
        $item->category_id  = $request->integer('category_id');
        $item->aspect_ratio = $request->input('aspect_ratio');
        $item->is_paid      = $request->boolean('is_paid');
        $item->status       = $request->boolean('status');
        $this->putFile($request, $item, 'zip', 'zip', $old);
        $this->putFile($request, $item, 'thumbnail', 'thumbnail', $old);
        $item->save();
    }

    /** First .json in the zip, with "0xRRGGBB" colours normalised to "#RRGGBB". */
    private function extractJson(string $zipPath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $raw = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'json' && !str_contains($name, '__MACOSX')) {
                $raw = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();

        if (!$raw) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        if (isset($data['path']) && is_string($data['path'])) {
            $data['path'] = trim($data['path']);
        }
        if (!empty($data['layers']) && is_array($data['layers'])) {
            $data['layers'] = array_map(fn ($l) => is_array($l) ? $this->normalizeLayer($l) : $l, $data['layers']);
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function normalizeLayer(array $layer): array
    {
        if (isset($layer['color']) && is_string($layer['color'])) {
            $layer['color'] = $this->color($layer['color']);
        }
        foreach ($layer['effects'] ?? [] as $k => $effect) {
            if (is_array($effect) && isset($effect['color']) && is_string($effect['color'])) {
                $layer['effects'][$k]['color'] = $this->color($effect['color']);
            }
        }

        return $layer;
    }

    private function color(string $c): string
    {
        return (str_starts_with($c, '0x') && strlen($c) === 8) ? '#' . strtoupper(substr($c, 2)) : $c;
    }
}
