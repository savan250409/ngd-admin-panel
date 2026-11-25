<?php

namespace App\Models;

use App\Support\Media;
use App\Support\ModuleRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class BaseItem extends Model
{
    /** @var class-string<BaseCategory> */
    protected static string $categoryModel;

    protected $guarded = ['id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(static::$categoryModel, 'category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', true);
    }

    abstract public function toApiArray(): array;

    // ── File handling (DB stores only the filename; the path is rebuilt here) ──────

    /** Columns that hold a stored filename, mapped to their sub-folder ('' = category root). */
    public function fileFields(): array
    {
        return [];
    }

    public function moduleKey(): string
    {
        return ModuleRegistry::moduleFor(static::class) ?? '';
    }

    public function categoryFolder(?string $name = null): string
    {
        return Media::folder($name ?? $this->category?->name ?? 'uncategorized');
    }

    /** Public-relative path for a file column, e.g. "upload/frame/Birthday/frame/rose.webp". */
    public function fileRelPath(string $column, ?string $name = null, ?string $categoryFolder = null): ?string
    {
        $name ??= $this->{$column};
        if (!$name) {
            return null;
        }
        $sub = $this->fileFields()[$column] ?? '';

        return 'upload/' . $this->moduleKey() . '/' . ($categoryFolder ?? $this->categoryFolder())
            . ($sub !== '' ? '/' . $sub : '') . '/' . $name;
    }

    /** Public URL for a file column. */
    public function fileUrl(string $column): ?string
    {
        return Media::url($this->fileRelPath($column));
    }

    /** Every existing file's public-relative path (for deletion). */
    public function allFileRelPaths(): array
    {
        $paths = [];
        foreach (array_keys($this->fileFields()) as $column) {
            if ($rel = $this->fileRelPath($column)) {
                $paths[] = $rel;
            }
        }

        return $paths;
    }
}
