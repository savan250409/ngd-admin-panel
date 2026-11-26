<?php

namespace App\Models;

use App\Support\Media;
use App\Support\ModuleRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Every module's category: name + image + the app it belongs to.
 * Concrete classes only set $table and $itemModel.
 */
abstract class BaseCategory extends Model
{
    /** @var class-string<BaseItem> */
    protected static string $itemModel;

    protected $fillable = ['name', 'image', 'orientation', 'status', 'sort_order'];

    protected $casts = ['status' => 'boolean', 'sort_order' => 'integer'];

    /** Manual order first (0 = unordered → newest first). */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderByDesc('id');
    }

    /** The apps this category is served to (one category can belong to many apps). */
    public function apps(): MorphToMany
    {
        return $this->morphToMany(MobileApp::class, 'category', 'app_category', 'category_id', 'app_id')
            ->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(static::$itemModel, 'category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', true);
    }

    public function moduleKey(): string
    {
        return ModuleRegistry::moduleFor(static::class) ?? '';
    }

    public function folder(?string $name = null): string
    {
        return Media::folder($name ?? $this->name);
    }

    /** The category image lives in the category's own folder; DB stores only its name. */
    public function imageRelPath(?string $name = null, ?string $folder = null): ?string
    {
        $name ??= $this->image;
        if (!$name) {
            return null;
        }

        return 'upload/' . $this->moduleKey() . '/' . ($folder ?? $this->folder()) . '/' . $name;
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->imageRelPath());
    }

    public function toApiArray(): array
    {
        $data = [
            'id'          => $this->id,
            'name'        => $this->name,
            'image_url'   => $this->imageUrl(),
            'items_count' => (int) ($this->active_items_count ?? 0),
        ];
        if (array_key_exists('orientation', $this->attributes)) {
            $data['orientation'] = $this->orientation ?: 'portrait';
        }

        return $data;
    }
}
