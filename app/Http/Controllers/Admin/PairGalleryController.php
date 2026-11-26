<?php

namespace App\Http\Controllers\Admin;

use App\Models\BaseItem;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * One record per category holding many PAIRS — each a frame image + a thumbnail
 * (Template also carries a count). Add several at once; the edit screen shows the
 * saved pairs so you can reorder / remove them, and new pairs are appended.
 */
abstract class PairGalleryController extends BaseItemController
{
    protected string $noun = 'frame';

    /** Whether each entry also has a count field (Template). */
    protected bool $hasCount = false;

    protected function itemTable(): string
    {
        return (new ($this->itemModel()))->getTable();
    }

    protected function rules(Request $request, ?BaseItem $item): array
    {
        $rules = [
            'category_id'    => [
                'required',
                'exists:' . $this->categoryTable() . ',id',
                Rule::unique($this->itemTable(), 'category_id')->ignore($item?->id),
            ],
            // Ordered list of rows: each value is an existing index or "new".
            'slots'          => 'nullable|array',
            'frame_files'    => 'nullable|array',
            'frame_files.*'  => 'image|mimes:webp|max:10240',
            'thumb_files'    => 'nullable|array',
            'thumb_files.*'  => 'image|mimes:webp|max:10240',
            'status'         => 'nullable|boolean',
        ];
        if ($this->hasCount) {
            $rules['counts']   = 'nullable|array';
            $rules['counts.*'] = 'integer|min:1|max:100';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return parent::messages() + [
            'category_id.unique'   => "This category already has {$this->noun}s — edit that record instead.",
            'frame_files.*.mimes'  => 'Only .webp images are allowed.',
            'thumb_files.*.mimes'  => 'Only .webp images are allowed.',
        ];
    }

    /** Both Frame and Template render the shared pair views. */
    protected function render(string $view, array $data = []): View
    {
        $template = $view === 'form' ? 'modules.pair.form' : 'modules.gallery.index';

        return view($template, $data + [
            'cfg'      => $this->cfg(),
            'module'   => $this->module,
            'noun'     => $this->noun,
            'hasCount' => $this->hasCount,
        ]);
    }

    protected function indexExtras(): array
    {
        return ['totalImages' => $this->itemModel()::all(['images'])->sum(fn ($r) => count($r->pairs()))];
    }

    protected function formExtras(?BaseItem $item): array
    {
        return ['usedCategoryIds' => $this->itemModel()::when($item, fn ($q) => $q->where('id', '!=', $item->id))->pluck('category_id')->all()];
    }

    protected function persist(Request $request, BaseItem $item): void
    {
        $old     = $this->oldCategoryId($item);
        $current = $item->pairs();
        $newCat  = $request->integer('category_id');

        $frameDir    = $this->dir($newCat, 'frame');
        $thumbDir    = $this->dir($newCat, 'thumbnail');
        $oldFrameRel = fn ($name) => 'upload/' . $this->dir($old, 'frame') . '/' . $name;
        $oldThumbRel = fn ($name) => 'upload/' . $this->dir($old, 'thumbnail') . '/' . $name;

        $slots      = (array) $request->input('slots', []);
        $frameFiles = $request->file('frame_files', []);
        $thumbFiles = $request->file('thumb_files', []);
        $counts     = (array) $request->input('counts', []);
        ksort($slots, SORT_NUMERIC);

        $result    = [];
        $keptIndex = [];

        foreach ($slots as $k => $ref) {
            $newFrame = $frameFiles[$k] ?? null;
            $newThumb = $thumbFiles[$k] ?? null;

            if ($ref === 'new') {
                if (!$newFrame || !$newThumb) {
                    throw ValidationException::withMessages(['frame_files' => "Each new {$this->noun} needs both a frame and a thumbnail."]);
                }
                $pair = ['frame' => Media::store($newFrame, $frameDir), 'thumbnail' => Media::store($newThumb, $thumbDir)];
                if ($this->hasCount) {
                    $pair['count'] = (int) ($counts[$k] ?? 1) ?: 1;
                }
                $result[] = $pair;
                continue;
            }

            // Existing entry (keep / replace / move).
            $idx  = (int) $ref;
            $pair = $current[$idx] ?? null;
            if (!$pair) {
                continue;
            }
            $keptIndex[] = $idx;

            // Frame: replace, or move when the category changed.
            if ($newFrame) {
                if ($old) {
                    Media::delete($oldFrameRel($pair['frame']));
                }
                $pair['frame'] = Media::store($newFrame, $frameDir);
            } elseif ($old && $old !== $newCat) {
                Media::move($oldFrameRel($pair['frame']), 'upload/' . $frameDir . '/' . $pair['frame']);
            }
            // Thumbnail: same.
            if ($newThumb) {
                if ($old) {
                    Media::delete($oldThumbRel($pair['thumbnail']));
                }
                $pair['thumbnail'] = Media::store($newThumb, $thumbDir);
            } elseif ($old && $old !== $newCat) {
                Media::move($oldThumbRel($pair['thumbnail']), 'upload/' . $thumbDir . '/' . $pair['thumbnail']);
            }
            if ($this->hasCount && isset($counts[$k])) {
                $pair['count'] = (int) $counts[$k] ?: 1;
            }
            $result[] = $pair;
        }

        // Delete any existing entries that were removed from the list.
        if ($old) {
            foreach ($current as $idx => $pair) {
                if (!in_array($idx, $keptIndex, true)) {
                    Media::delete($oldFrameRel($pair['frame']));
                    Media::delete($oldThumbRel($pair['thumbnail']));
                }
            }
        }

        if (count($result) === 0) {
            throw ValidationException::withMessages(['frame_files' => "Add at least one {$this->noun} (with its thumbnail)."]);
        }

        $item->category_id = $newCat;
        $item->images      = array_values($result);
        $item->status      = $request->boolean('status');
        $item->save();
    }

    protected function cleanup(BaseItem $item): void
    {
        foreach ($item->imageRelPaths() as $rel) {
            Media::delete($rel);
        }
    }
}
