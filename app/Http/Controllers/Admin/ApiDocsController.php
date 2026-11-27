<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileApp;
use App\Models\Setting;
use App\Support\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** The "API List" screen: every module's two endpoints, the bearer token and a Postman export. */
class ApiDocsController extends Controller
{
    public function index(): View
    {
        return view('api-docs.index', [
            'token'   => Setting::apiToken(),
            'groups'  => $this->endpoints(),
            'apps'    => MobileApp::where('status', true)->orderBy('app_name')->get(),
            'baseUrl' => url('/api/v1'),
        ]);
    }

    public function regenerate(): RedirectResponse
    {
        Setting::regenerateApiToken();

        return back()->with('success', 'New API token generated. Update it in your apps — the old token no longer works.');
    }

    /** Postman v2.1 collection with {{base_url}} and {{token}} variables. */
    public function postman(): JsonResponse
    {
        $folders = [];
        foreach ($this->endpoints() as $group) {
            $folders[] = [
                'name' => $group['title'],
                'item' => array_map(fn ($ep) => [
                    'name'    => $ep['name'],
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'body' => [
                            'mode'     => 'formdata',
                            'formdata' => array_map(fn ($p) => ['key' => $p['key'], 'value' => $p['example'], 'type' => 'text'], $ep['params']),
                        ],
                        'url' => [
                            'raw'  => '{{base_url}}/' . $ep['path'],
                            'host' => ['{{base_url}}'],
                            'path' => explode('/', $ep['path']),
                        ],
                    ],
                ], $group['endpoints']),
            ];
        }

        return response()->json([
            'info'     => [
                'name'   => config('app.name') . ' API',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                ['key' => 'base_url', 'value' => url('/api/v1')],
                ['key' => 'token', 'value' => Setting::apiToken()],
            ],
            'item'     => $folders,
        ], 200, ['Content-Disposition' => 'attachment; filename="ngd-admin-api.postman_collection.json"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function endpoints(): array
    {
        $groups = [];
        foreach (ModuleRegistry::all() as $key => $cfg) {
            $categorySample = ['id' => 1, 'name' => 'Birthday', 'image_url' => 'https://your-domain.com/upload/' . $cfg['title'] . '/Birthday/cover.webp', 'items_count' => 12];
            if (!empty($cfg['category_orientation'])) {
                $categorySample['orientation'] = 'portrait';
            }
            // getCategories also returns each category's latest 5 items.
            $categorySample['items'] = !empty($cfg['gallery'])
                ? [$this->sampleItem($key), '… up to 5 image URLs']
                : [$this->sampleItem($key), '… up to 5 latest items'];

            $groups[] = [
                'key'       => $key,
                'title'     => $cfg['title'],
                'icon'      => $cfg['icon'],
                'endpoints' => [
                    [
                        'name'        => "Get {$cfg['title']} Categories",
                        'path'        => "{$key}/getCategories",
                        'description' => "All active {$cfg['title']} categories of the app that owns this package name. Each category includes a preview of its latest 5 items.",
                        'params'      => [
                            ['key' => 'package_name', 'type' => 'string', 'example' => 'com.example.app', 'required' => true],
                            ['key' => 'platform', 'type' => 'string · android | ios', 'example' => 'android', 'required' => false],
                        ],
                        'response'    => [
                            'status'  => true,
                            'message' => 'Categories fetched successfully.',
                            'app'     => ['app_name' => 'Example App', 'package_name' => 'com.example.app'],
                            'data'    => [$categorySample],
                        ],
                    ],
                    [
                        'name'        => "Get {$cfg['item_title']} By Category",
                        'path'        => "{$key}/{$cfg['api_items']}",
                        'description' => "Active {$cfg['item_title']} of one category, addressed by category id alone (the id comes from getCategories).",
                        'params'      => [
                            ['key' => 'category_id', 'type' => 'integer', 'example' => '1', 'required' => true],
                        ],
                        'response'    => [
                            'status'   => true,
                            'message'  => 'Data fetched successfully.',
                            'category' => ['id' => 1, 'name' => 'Birthday'] + (!empty($cfg['category_orientation']) ? ['orientation' => 'portrait'] : []),
                            'total'    => 1,
                            'data'     => [$this->sampleItem($key)],
                        ],
                    ],
                ],
            ];
        }

        return $groups;
    }

    private function sampleItem(string $module): mixed
    {
        $u = fn (string $p) => 'https://your-domain.com/upload/' . $module . '/' . $p;

        return match ($module) {
            'frame'          => ['frame_url' => $u('Birthday/frame/rose.webp'), 'thumbnail_url' => $u('Birthday/thumbnail/rose.webp')],
            'template'       => ['frame_url' => $u('Birthday/frame/collage.webp'), 'thumbnail_url' => $u('Birthday/thumbnail/collage.webp'), 'count' => 3],
            'dynamic-frame'  => ['id' => 5, 'category_id' => 1, 'aspect_ratio' => '4:5', 'is_paid' => false, 'thumbnail_url' => $u('thumbnails/post.webp'), 'zip_url' => $u('zips/post.zip'), 'json' => ['layers' => ['...']]],
            'baby-backdrop'  => $u('Birthday/backdrop-1.webp'),
            'video-template' => ['id' => 5, 'category_id' => 1, 'title' => 'Happy Birthday', 'is_paid' => false, 'thumbnail_url' => $u('thumbnails/hb.webp'), 'zip_url' => $u('zips/hb.zip'), 'video_url' => $u('extracted/5-abc123/res/data.html'), 'width' => '1080', 'height' => '1920', 'total_image_count' => 4, 'total_editable' => 3, 'json' => ['elements' => ['...']]],
            'ai-image'       => ['id' => 5, 'category_id' => 1, 'image_url' => $u('images/portrait.webp'), 'prompt' => 'A watercolor portrait ...'],
            'ai-video'       => ['id' => 5, 'category_id' => 1, 'video_url' => $u('videos/dance.mp4'), 'thumbnail_url' => $u('thumbnails/thumb.jpg'), 'prompt' => 'Make the person dance ...'],
            default          => [],
        };
    }
}
