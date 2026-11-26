<?php

namespace App\Support;

use App\Http\Controllers\Admin\AiImageController;
use App\Http\Controllers\Admin\AiVideoController;
use App\Http\Controllers\Admin\BabyBackdropController;
use App\Http\Controllers\Admin\DynamicFrameController;
use App\Http\Controllers\Admin\FrameController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\VideoTemplateController;
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
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\VideoTemplate;
use App\Models\VideoTemplateCategory;

/**
 * Single source of truth for every content module.
 *
 * Each module is a "Category" + "Item" pair. The admin routes, the sidebar,
 * the dashboard, the public API and the API list screen are all generated
 * from this table, so adding a module means adding one entry here plus its
 * item model/controller/views.
 */
class ModuleRegistry
{
    public static function all(): array
    {
        return [
            'frame' => [
                'title'           => 'Frame',
                'icon'            => 'bi-bounding-box-circles',
                'category_model'  => FrameCategory::class,
                'item_model'      => Frame::class,
                'item_controller' => FrameController::class,
                'item_slug'       => 'frames',
                'item_title'      => 'Frames',
                'item_singular'   => 'Frame',
                'api_items'       => 'getFramesByCategory',
                'gallery'         => true,
            ],
            'template' => [
                'title'           => 'Template',
                'icon'            => 'bi-grid-1x2',
                'category_model'  => TemplateCategory::class,
                'item_model'      => Template::class,
                'item_controller' => TemplateController::class,
                'item_slug'       => 'templates',
                'item_title'      => 'Templates',
                'item_singular'   => 'Template',
                'api_items'       => 'getTemplatesByCategory',
                'gallery'         => true,
            ],
            'dynamic-frame' => [
                'title'           => 'Dynamic Frame',
                'icon'            => 'bi-layers',
                'category_model'  => DynamicFrameCategory::class,
                'item_model'      => DynamicFrame::class,
                'item_controller' => DynamicFrameController::class,
                'item_slug'       => 'frames',
                'item_title'      => 'Dynamic Frames',
                'item_singular'   => 'Dynamic Frame',
                'api_items'       => 'getDynamicFramesByCategory',
            ],
            'baby-backdrop' => [
                'title'                => 'Baby Backdrop',
                'icon'                 => 'bi-balloon-heart',
                'category_model'       => BabyBackdropCategory::class,
                'item_model'           => BabyBackdrop::class,
                'item_controller'      => BabyBackdropController::class,
                'item_slug'            => 'backdrops',
                'item_title'           => 'Backdrops',
                'item_singular'        => 'Backdrop',
                'api_items'            => 'getBackdropsByCategory',
                'category_orientation' => true,
                'gallery'              => true,
            ],
            'video-template' => [
                'title'           => 'Video Template',
                'icon'            => 'bi-camera-reels',
                'category_model'  => VideoTemplateCategory::class,
                'item_model'      => VideoTemplate::class,
                'item_controller' => VideoTemplateController::class,
                'item_slug'       => 'templates',
                'item_title'      => 'Video Templates',
                'item_singular'   => 'Video Template',
                'api_items'       => 'getVideoTemplatesByCategory',
            ],
            'ai-image' => [
                'title'           => 'AI Image',
                'icon'            => 'bi-stars',
                'category_model'  => AiImageCategory::class,
                'item_model'      => AiImage::class,
                'item_controller' => AiImageController::class,
                'item_slug'       => 'images',
                'item_title'      => 'AI Images',
                'item_singular'   => 'AI Image',
                'api_items'       => 'getAiImagesByCategory',
            ],
            'ai-video' => [
                'title'           => 'AI Video',
                'icon'            => 'bi-film',
                'category_model'  => AiVideoCategory::class,
                'item_model'      => AiVideo::class,
                'item_controller' => AiVideoController::class,
                'item_slug'       => 'videos',
                'item_title'      => 'AI Videos',
                'item_singular'   => 'AI Video',
                'api_items'       => 'getAiVideosByCategory',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(static::all());
    }

    /** Module key for a category OR item model class (used to rebuild file paths). */
    public static function moduleFor(string $modelClass): ?string
    {
        foreach (static::all() as $key => $cfg) {
            if ($cfg['category_model'] === $modelClass || $cfg['item_model'] === $modelClass) {
                return $key;
            }
        }

        return null;
    }

    public static function get(string $key): array
    {
        $all = static::all();
        abort_unless(isset($all[$key]), 404);

        return $all[$key] + ['key' => $key];
    }
}
