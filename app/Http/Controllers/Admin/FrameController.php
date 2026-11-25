<?php

namespace App\Http\Controllers\Admin;

use App\Models\BaseItem;
use App\Models\Frame;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Frames: one record per category holding many frames. Each frame is a PAIR —
 * a frame image + a thumbnail — added together (several pairs at once),
 * re-orderable, removable. The API returns both URLs per frame.
 */
class FrameController extends BaseItemController
{
    protected string $module = 'frame';

    protected function rules(Request $request, ?BaseItem $item): array
    {
        return [
            'category_id'       => [
                'required',
                'exists:' . $this->categoryTable() . ',id',
                Rule::unique('frames', 'category_id')->ignore($item?->id),
            ],
            'existing'          => 'nullable|array',
            'existing.*'        => 'integer|min:0',
            'new_frames'        => 'nullable|array|max:100',
            'new_frames.*'      => 'image|mimes:webp|max:10240',
            'new_thumbnails'    => 'nullable|array|max:100',
            'new_thumbnails.*'  => 'image|mimes:webp|max:10240',
            'status'            => 'nullable|boolean',
        ];
    }

    protected function messages(): array
    {
        return parent::messages() + [
            'category_id.unique'    => 'This category already has frames — edit that record instead.',
            'new_frames.*.mimes'    => 'Only .webp images are allowed.',
            'new_thumbnails.*.mimes' => 'Only .webp images are allowed.',
        ];
    }

    /** List uses the shared gallery view; the form is frame-specific (pair rows). */
    protected function render(string $view, array $data = []): View
    {
        $template = $view === 'form' ? 'modules.frame.form' : 'modules.gallery.index';

        return view($template, $data + ['cfg' => $this->cfg(), 'module' => $this->module, 'noun' => 'frame']);
    }

    protected function indexExtras(): array
    {
        return ['totalImages' => Frame::all(['images'])->sum(fn ($r) => count($r->pairs()))];
    }

    protected function formExtras(?BaseItem $item): array
    {
        return ['usedCategoryIds' => Frame::when($item, fn ($q) => $q->where('id', '!=', $item->id))->pluck('category_id')->all()];
    }

    protected function persist(Request $request, BaseItem $item): void
    {
        /** @var Frame $item */
        $old     = $this->oldCategoryId($item);
        $current = $item->pairs();

        // "existing[]" holds positions of saved pairs to keep, in the new order.
        $keep = [];
        foreach ((array) $request->input('existing', []) as $idx) {
            if (isset($current[(int) $idx])) {
                $keep[] = $current[(int) $idx];
            }
        }

        $newFrames = array_values($request->file('new_frames', []));
        $newThumbs = array_values($request->file('new_thumbnails', []));

        if (count($newFrames) !== count($newThumbs)) {
            throw ValidationException::withMessages(['new_frames' => 'Each frame needs its thumbnail (and vice-versa).']);
        }
        if (count($keep) + count($newFrames) === 0) {
            throw ValidationException::withMessages(['new_frames' => 'Add at least one frame (with its thumbnail).']);
        }

        $newCat   = $request->integer('category_id');
        $frameDir = $this->dir($newCat, 'frame');       // frame/{Category}/frame
        $thumbDir = $this->dir($newCat, 'thumbnail');   // frame/{Category}/thumbnail

        // Remove pairs the user deleted (both files, from the old folder).
        $removed = array_udiff($current, $keep, fn ($a, $b) => $a === $b ? 0 : 1);
        $oldFolder = $old ? $item->categoryFolder($this->categoryName($old)) : null;
        foreach ($removed as $p) {
            if ($oldFolder) {
                Media::delete('upload/' . $this->dir($old, 'frame') . '/' . $p['frame']);
                Media::delete('upload/' . $this->dir($old, 'thumbnail') . '/' . $p['thumbnail']);
            }
        }

        // Category changed → move kept pairs into the new folders.
        if ($old && $old !== $newCat) {
            foreach ($keep as $p) {
                Media::move('upload/' . $this->dir($old, 'frame') . '/' . $p['frame'], 'upload/' . $frameDir . '/' . $p['frame']);
                Media::move('upload/' . $this->dir($old, 'thumbnail') . '/' . $p['thumbnail'], 'upload/' . $thumbDir . '/' . $p['thumbnail']);
            }
        }

        // Store the new pairs (keeping original filenames).
        foreach ($newFrames as $i => $frameFile) {
            $keep[] = [
                'frame'     => Media::store($frameFile, $frameDir),
                'thumbnail' => Media::store($newThumbs[$i], $thumbDir),
            ];
        }

        $item->category_id = $newCat;
        $item->images      = array_values($keep);
        $item->status      = $request->boolean('status');
        $item->save();
    }

    protected function cleanup(BaseItem $item): void
    {
        foreach ($item->imageRelPaths() as $rel) {
            Media::delete($rel);
        }
    }

    private function categoryName(int $categoryId): string
    {
        return $this->cfg()['category_model']::find($categoryId)?->name ?? 'uncategorized';
    }
}
