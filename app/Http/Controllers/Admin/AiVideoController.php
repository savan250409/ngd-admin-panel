<?php

namespace App\Http\Controllers\Admin;

use App\Models\AiVideo;
use App\Models\BaseItem;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI videos. The thumbnail is generated in the browser: the selected video is
 * drawn onto a <canvas> (no libraries, no ffmpeg) and the frame is posted as a
 * data URL in "thumbnail_data".
 */
class AiVideoController extends BaseItemController
{
    protected string $module = 'ai-video';

    protected array $searchColumns = ['prompt'];

    protected function rules(Request $request, ?BaseItem $item): array
    {
        return [
            'category_id'    => 'required|exists:' . $this->categoryTable() . ',id',
            'video'          => $this->fileRule($item, 'file|mimetypes:video/mp4,video/webm,video/quicktime|max:51200'),
            'prompt'         => 'required|string|max:10000',
            'thumbnail_data' => 'nullable|string|max:8000000',
            'status'         => 'nullable|boolean',
        ];
    }

    protected function persist(Request $request, BaseItem $item): void
    {
        $old = $this->oldCategoryId($item);
        $item->category_id = $request->integer('category_id');
        $item->prompt      = trim($request->input('prompt'));
        $item->status      = $request->boolean('status');

        // Video: store/replace (or move to the new category folder).
        $this->putFile($request, $item, 'video', 'video', $old);

        if ($request->hasFile('video')) {
            // A new video makes the old thumbnail stale — delete it from wherever it lived.
            $thumbCat = $old ?: (int) $item->category_id;
            Media::delete($this->catFileRel($thumbCat, 'thumbnail', $item->getOriginal('thumbnail')));
            $item->thumbnail = null;
        } elseif ($old && $old !== (int) $item->category_id && $item->thumbnail) {
            // Category changed without a new video: move the existing thumbnail too.
            Media::move(
                $this->catFileRel($old, 'thumbnail', $item->thumbnail),
                $this->catFileRel((int) $item->category_id, 'thumbnail', $item->thumbnail),
            );
        }

        if ($thumb = $request->input('thumbnail_data')) {
            $oldThumbRel = $this->catFileRel((int) $item->category_id, 'thumbnail', $item->thumbnail);
            $name = Media::storeDataUrl($thumb, $this->dir((int) $item->category_id, 'thumbnail'));
            if ($name) {
                Media::delete($oldThumbRel);
                $item->thumbnail = $name;
            }
        }
        $item->save();
    }

    /** Fallback used by the list page to fill in any missing thumbnail. */
    public function saveThumbnail(Request $request, int $id): JsonResponse
    {
        $request->validate(['thumbnail_data' => 'required|string|max:8000000']);
        $video = AiVideo::with('category')->findOrFail($id);

        $dir  = 'ai-video/' . Media::folder($video->category?->name ?? 'uncategorized') . '/thumbnail';
        $path = Media::storeDataUrl($request->input('thumbnail_data'), $dir);
        abort_unless($path, 422, 'Invalid image data.');

        Media::delete($video->thumbnail);
        $video->update(['thumbnail' => $path]);

        return response()->json(['success' => true]);
    }
}
