<?php

namespace App\Models;

use App\Support\Media;

/**
 * Gallery module: one record per category holding many frames. Each frame is a PAIR
 * of files — a frame image and a thumbnail — stored as:
 *   images = [ {"frame": "rose.webp", "thumbnail": "rose-t.webp"}, ... ]
 * saved under upload/frame/{Category}/frame/... and upload/frame/{Category}/thumbnail/...
 */
class Frame extends GalleryItem
{
    protected static string $categoryModel = FrameCategory::class;

    /** Folder for one of the two sub-kinds ("frame" | "thumbnail"). */
    public function subDir(string $sub, ?string $categoryFolder = null): string
    {
        return 'upload/frame/' . ($categoryFolder ?? $this->categoryFolder()) . '/' . $sub;
    }

    /** @return array<int,array{frame:string,thumbnail:string}> */
    public function pairs(): array
    {
        return array_values(array_filter($this->images ?? [], fn ($p) => is_array($p) && !empty($p['frame'])));
    }

    /** Thumbnail URLs — used for the admin list preview and the "See more" popup. */
    public function imageUrls(): array
    {
        return array_map(fn ($p) => Media::url($this->subDir('thumbnail') . '/' . $p['thumbnail']), $this->pairs());
    }

    /** Every file (frame + thumbnail) for deletion/move. */
    public function imageRelPaths(?string $categoryFolder = null): array
    {
        $paths = [];
        foreach ($this->pairs() as $p) {
            $paths[] = $this->subDir('frame', $categoryFolder) . '/' . $p['frame'];
            $paths[] = $this->subDir('thumbnail', $categoryFolder) . '/' . $p['thumbnail'];
        }

        return $paths;
    }

    /** API output: a frame_url + thumbnail_url for every frame. */
    public function apiImages(): array
    {
        return array_map(fn ($p) => [
            'frame_url'     => Media::url($this->subDir('frame') . '/' . $p['frame']),
            'thumbnail_url' => Media::url($this->subDir('thumbnail') . '/' . $p['thumbnail']),
        ], $this->pairs());
    }

    public function toApiArray(): array
    {
        return [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'frames'      => $this->apiImages(),
        ];
    }
}
