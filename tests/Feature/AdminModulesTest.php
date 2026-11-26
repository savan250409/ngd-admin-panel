<?php

namespace Tests\Feature;

use App\Models\MobileApp;
use App\Models\Setting;
use App\Models\User;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class AdminModulesTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;
    private User $admin;
    private MobileApp $mobileApp;
    private MobileApp $otherApp;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep test uploads out of the real public/ folder.
        $this->publicDir = sys_get_temp_dir() . '/ngd-public-' . uniqid();
        File::makeDirectory($this->publicDir . '/upload', 0775, true);
        $this->app->usePublicPath($this->publicDir);

        $this->admin    = User::factory()->create();
        $this->mobileApp = MobileApp::create(['app_name' => 'Demo', 'package_name' => 'com.test.demo', 'status' => true]);
        $this->otherApp = MobileApp::create(['app_name' => 'Other', 'package_name' => 'com.test.other', 'status' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function zip(array $files, string $name = 'template.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip  = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $entry => $content) {
            $zip->addFromString($entry, $content);
        }
        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    /** A valid dynamic-frame zip (contains a JSON layout), named as given. */
    private function dynZip(string $name = 'layout.zip'): UploadedFile
    {
        return $this->zip(['layout.json' => json_encode(['layers' => [['color' => '0xff0000']]])], $name);
    }

    /** Pair-gallery payload: N brand-new frame+thumbnail(+count) rows. */
    private function pairNew(array $frames, array $thumbs, ?array $counts = null): array
    {
        $p = ['slots' => [], 'frame_files' => [], 'thumb_files' => []];
        foreach ($frames as $i => $frame) {
            $p['slots'][$i]       = 'new';
            $p['frame_files'][$i] = $frame;
            $p['thumb_files'][$i] = $thumbs[$i];
            if ($counts !== null) {
                $p['counts'][$i] = $counts[$i];
            }
        }

        return $p;
    }

    private function video(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vid') . '.mp4';
        copy(base_path('tests/Fixtures/sample.mp4'), $path);

        return new UploadedFile($path, 'sample.mp4', 'video/mp4', null, true);
    }

    private function pngDataUrl(): string
    {
        $im = imagecreatetruecolor(64, 36);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 50, 90));
        ob_start();
        imagepng($im);

        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    /** A real .webp upload (all image fields are webp-only). */
    private function webp(string $name = 'img'): UploadedFile
    {
        $im = imagecreatetruecolor(300, 300);
        imagefill($im, 0, 0, imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        $path = tempnam(sys_get_temp_dir(), 'webp') . '.webp';
        imagewebp($im, $path);
        imagedestroy($im);

        return new UploadedFile($path, $name . '.webp', 'image/webp', null, true);
    }

    /** Payload that creates one valid item for the module. */
    private function itemPayload(string $module): array
    {
        $img = fn ($n = 'img') => $this->webp(is_string($n) ? $n : 'img');

        return match ($module) {
            'frame'          => ['slots' => ['new', 'new'], 'frame_files' => [$img('f1'), $img('f2')], 'thumb_files' => [$img('t1'), $img('t2')]], // two new pairs
            'template'       => ['slots' => ['new', 'new'], 'frame_files' => [$img('f1'), $img('f2')], 'thumb_files' => [$img('t1'), $img('t2')], 'counts' => [2, 3]], // pairs + count
            'dynamic-frame'  => [
                'aspect_ratio' => '4:5', 'is_paid' => 1, 'thumbnail' => $img(),
                'zip' => $this->zip(['layout.json' => json_encode(['path' => ' x ', 'layers' => [['color' => '0xff0000']]]), 'bg.png' => 'x']),
            ],
            'baby-backdrop'  => ['new_images' => [$img('1.png'), $img('2.png')]],
            'video-template' => [
                'title' => 'Happy Birthday', 'thumbnail' => $img(),
                'zip' => $this->zip([
                    'res/data.json' => '{"v":"5.7.4","fr":30,"ip":0,"op":60,"w":100,"h":100,"layers":[]}',
                    'res/images/img_0.png' => 'png',
                    'template.json' => json_encode(['elements' => [['size' => [1080, 1920], 'editable' => 1], ['size' => [500, 500], 'editable' => 0]]]),
                    'evil.php' => '<?php echo 1;',
                ]),
            ],
            'ai-image'       => ['image' => $img(), 'prompt' => 'A watercolor portrait'],
            'ai-video'       => ['video' => $this->video(), 'prompt' => 'Make it dance', 'thumbnail_data' => $this->pngDataUrl()],
        };
    }

    public static function modules(): array
    {
        return array_map(fn ($k) => [$k], array_combine(ModuleRegistry::keys(), ModuleRegistry::keys()));
    }

    private function bearer(): array
    {
        return ['Authorization' => 'Bearer ' . Setting::apiToken(), 'Accept' => 'application/json'];
    }

    // ── tests ────────────────────────────────────────────────────────────

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/frame/frames')->assertRedirect('/login');
    }

    public function test_app_crud_and_validation(): void
    {
        $this->actingAs($this->admin);

        $this->post('/apps', ['app_name' => 'X', 'package_name' => 'not a package'])->assertSessionHasErrors('package_name');
        $this->post('/apps', ['app_name' => 'X', 'package_name' => 'com.test.demo'])->assertSessionHasErrors('package_name');

        $this->post('/apps', ['app_name' => 'New', 'package_name' => 'com.test.new', 'status' => 1])->assertRedirect('/apps');
        $app = MobileApp::where('package_name', 'com.test.new')->firstOrFail();

        $this->get('/apps?search=new')->assertOk()->assertSee('com.test.new');
        $this->put("/apps/{$app->id}", ['app_name' => 'Renamed', 'package_name' => 'com.test.new', 'status' => 0])->assertRedirect('/apps');
        $this->assertFalse($app->fresh()->status);

        $this->delete("/apps/{$app->id}")->assertSessionHas('success');
        $this->assertNull($app->fresh());
    }

    public function test_deleting_app_cascades_only_its_exclusive_data(): void
    {
        $this->actingAs($this->admin);

        // "Solo" belongs only to the demo app; "Shared" also belongs to the other app.
        $this->post('/ai-image/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Solo', 'status' => 1, 'image' => $this->webp('c')]);
        $this->post('/ai-image/categories', ['app_ids' => [$this->mobileApp->id, $this->otherApp->id], 'name' => 'Shared', 'status' => 1, 'image' => $this->webp('c')]);
        $solo   = \App\Models\AiImageCategory::where('name', 'Solo')->firstOrFail();
        $shared = \App\Models\AiImageCategory::where('name', 'Shared')->firstOrFail();

        $this->post('/ai-image/images', ['category_id' => $solo->id, 'status' => 1, 'prompt' => 'x', 'image' => $this->webp('pic')]);
        $item     = \App\Models\AiImage::firstOrFail();
        $itemPath = $item->fileRelPath('image');
        $soloCover = $solo->imageRelPath();
        $this->assertFileExists(public_path($itemPath));

        // Delete the demo app → Solo (exclusive) is removed with its item + files; Shared survives.
        $this->delete('/apps/' . $this->mobileApp->id)->assertSessionHas('success');
        $this->assertNull($this->mobileApp->fresh());
        $this->assertNull($solo->fresh());                         // exclusive category gone
        $this->assertNull($item->fresh());                         // its item gone
        $this->assertFileDoesNotExist(public_path($itemPath));     // its files gone
        $this->assertFileDoesNotExist(public_path($soloCover));
        $this->assertNotNull($shared->fresh());                    // shared category survives
        $this->assertEqualsCanonicalizing([$this->otherApp->id], $shared->fresh()->apps->pluck('id')->all());
    }

    public function test_deleting_category_cascades_its_items(): void
    {
        $this->actingAs($this->admin);
        $this->post('/ai-image/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Casc', 'status' => 1, 'image' => $this->webp('c')]);
        $cat = \App\Models\AiImageCategory::firstOrFail();
        foreach (['a', 'b', 'c'] as $n) {
            $this->post('/ai-image/images', ['category_id' => $cat->id, 'status' => 1, 'prompt' => 'p', 'image' => $this->webp($n)]);
        }
        $this->assertSame(3, \App\Models\AiImage::count());
        $paths = \App\Models\AiImage::all()->map->fileRelPath('image')->all();

        // Deleting the category removes all 3 items and every file (no "delete items first" block).
        $this->delete('/ai-image/categories/' . $cat->id)->assertSessionHas('success');
        $this->assertSame(0, \App\Models\AiImage::count());
        $this->assertNull($cat->fresh());
        foreach ($paths as $p) {
            $this->assertFileDoesNotExist(public_path($p));
        }
    }

    #[DataProvider('modules')]
    public function test_module_end_to_end(string $module): void
    {
        $this->actingAs($this->admin);
        $cfg  = ModuleRegistry::get($module);
        $slug = $cfg['item_slug'];

        // Category: validation (app + image required), then create for MULTIPLE apps at once.
        $this->post("/{$module}/categories", ['name' => 'Birthday'])->assertSessionHasErrors(['app_ids', 'image']);
        $catData = ['app_ids' => [$this->mobileApp->id, $this->otherApp->id], 'name' => 'Birthday', 'status' => 1, 'image' => $this->webp()];
        if (!empty($cfg['category_orientation'])) {
            $catData['orientation'] = 'landscape';
        }
        $this->post("/{$module}/categories", $catData)->assertRedirect("/{$module}/categories");
        $category = $cfg['category_model']::firstOrFail();
        $this->assertFileExists(public_path($category->imageRelPath()));
        // It is linked to both apps.
        $this->assertEqualsCanonicalizing([$this->mobileApp->id, $this->otherApp->id], $category->apps->pluck('id')->all());

        // Same name is rejected while it shares an app; a third app (no overlap) may reuse it.
        $this->post("/{$module}/categories", array_merge($catData, ['app_ids' => [$this->mobileApp->id], 'image' => $this->webp()]))->assertSessionHasErrors('name');
        $third = MobileApp::create(['app_name' => 'Third', 'package_name' => 'com.test.third', 'status' => true]);
        $this->post("/{$module}/categories", array_merge($catData, ['app_ids' => [$third->id], 'image' => $this->webp()]))->assertSessionDoesntHaveErrors();
        $otherCategory = $cfg['category_model']::whereHas('apps', fn ($a) => $a->where('apps.id', $third->id))->firstOrFail();

        // Item: create.
        $this->post("/{$module}/{$slug}", ['category_id' => $category->id, 'status' => 1] + $this->itemPayload($module))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect("/{$module}/{$slug}");
        $item = $cfg['item_model']::firstOrFail();

        // List page: search + filters + pagination render, and no raw upload paths leak.
        $page = $this->get("/{$module}/{$slug}?search=Birth&app_id={$this->mobileApp->id}&category_id={$category->id}&status=1&per_page=25");
        $page->assertOk()->assertSee('Birthday');
        $this->assertStringNotContainsString('upload/', $page->getContent());
        $catPage = $this->get("/{$module}/categories?search=Birth&app_id={$this->mobileApp->id}")->assertOk()->assertSee('Birthday');
        $this->assertStringNotContainsString('upload/', $catPage->getContent());
        $edit = $this->get("/{$module}/{$slug}/{$item->id}/edit")->assertOk();
        $this->assertStringNotContainsString('upload/', $edit->getContent());
        $this->get("/{$module}/{$slug}?search=zzz-nothing")->assertOk()->assertDontSee('id-badge">' . $item->id . '<', false);

        // API: categories only for the package's app.
        $res = $this->postJson("/api/v1/{$module}/getCategories", ['package_name' => 'com.test.demo'], $this->bearer())->assertOk();
        $res->assertJsonPath('status', true)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $category->id)->assertJsonPath('data.0.items_count', 1);
        $this->assertStringContainsString('/upload/', $res->json('data.0.image_url'));
        // getCategories also previews the category's items (≤ 5).
        $this->assertNotEmpty($res->json('data.0.items'));
        $this->assertLessThanOrEqual(5, count($res->json('data.0.items')));

        // API: items of the category; another app's category is refused.
        $items = $this->postJson("/api/v1/{$module}/{$cfg['api_items']}", ['package_name' => 'com.test.demo', 'category_id' => $category->id], $this->bearer())->assertOk();
        $items->assertJsonPath('status', true);
        $this->postJson("/api/v1/{$module}/{$cfg['api_items']}", ['package_name' => 'com.test.demo', 'category_id' => $otherCategory->id], $this->bearer())->assertNotFound();
        $this->postJson("/api/v1/{$module}/getCategories", ['package_name' => 'com.test.demo'])->assertUnauthorized();

        $this->assertModuleSpecifics($module, $item->fresh(), $items->json());

        // Toggle status off → hidden from the API.
        $this->post("/{$module}/{$slug}/{$item->id}/toggle")->assertJson(['value' => false]);
        $this->postJson("/api/v1/{$module}/{$cfg['api_items']}", ['package_name' => 'com.test.demo', 'category_id' => $category->id], $this->bearer())
            ->assertJsonPath('total', 0);

        // Update without new files keeps them.
        $update = ['category_id' => $category->id, 'status' => 1, '_method' => 'PUT'] + match ($module) {
            'dynamic-frame'  => ['aspect_ratio' => '1:1'],
            'video-template' => ['title' => 'Renamed'],
            'ai-image', 'ai-video' => ['prompt' => 'Updated prompt'],
            'frame', 'template' => ['slots' => [0 => '1']],  // keep only the 2nd saved entry
            'baby-backdrop'  => ['existing' => [1]],          // keep only the 2nd saved image
            default          => [],
        };
        $before          = $item->images ?? null;
        $beforeImageRels = !empty($cfg['gallery']) ? $item->imageRelPaths() : [];
        $this->post("/{$module}/{$slug}/{$item->id}", $update)->assertSessionDoesntHaveErrors()->assertRedirect();
        $item->refresh();
        if (!empty($cfg['gallery'])) {
            $this->assertSame([$before[1]], $item->images);        // only the kept name remains
            $this->assertFileDoesNotExist(public_path($beforeImageRels[0])); // removed file gone
        }
        $this->assertTrue($item->status);
        // Files still exist (paths rebuilt from the stored names).
        $keepRels = !empty($cfg['gallery']) ? $item->imageRelPaths() : $item->allFileRelPaths();
        foreach ($keepRels as $rel) {
            $this->assertFileExists(public_path($rel));
        }

        // Deleting the category CASCADES: it removes the item, every file and the cover.
        $fileRels = !empty($cfg['gallery']) ? $item->imageRelPaths() : $item->allFileRelPaths();
        $catImage = $category->imageRelPath();
        $this->delete("/{$module}/categories/{$category->id}")->assertSessionHas('success');
        $this->assertNull($item->fresh());
        $this->assertNull($category->fresh());
        foreach ($fileRels as $rel) {
            $this->assertFileDoesNotExist(public_path($rel));
        }
        $this->assertFileDoesNotExist(public_path($catImage));
    }

    private function assertModuleSpecifics(string $module, $item, array $api): void
    {
        switch ($module) {
            case 'dynamic-frame':
                $this->assertSame('#FF0000', strtoupper(json_decode($item->json, true)['layers'][0]['color']));
                $this->assertSame('x', $api['data'][0]['json']['path']);
                $this->assertTrue($api['data'][0]['is_paid']);
                break;
            case 'template':
                $this->assertCount(2, $item->images);                      // two template entries
                $this->assertCount(2, $api['data']);
                $this->assertArrayHasKey('frame_url', $api['data'][0]);
                $this->assertArrayHasKey('thumbnail_url', $api['data'][0]);
                $this->assertSame(2, $api['data'][0]['count']);            // first entry's count
                break;
            case 'frame':
                $this->assertCount(2, $item->images);                      // two frame pairs in one record
                $this->assertCount(2, $api['data']);                       // API returns the flat pair list
                $this->assertArrayHasKey('frame_url', $api['data'][0]);    // each frame has BOTH urls
                $this->assertArrayHasKey('thumbnail_url', $api['data'][0]);
                break;
            case 'baby-backdrop':
                $this->assertCount(2, $item->images);
                $this->assertCount(2, $api['data']);
                $this->assertSame('landscape', $api['category']['orientation']);
                break;
            case 'video-template':
                $this->assertSame('res/data.html', $item->video_html_path);
                $this->assertFileExists(public_path($item->extract_dir . '/res/data.html'));
                $this->assertFileDoesNotExist(public_path($item->extract_dir . '/evil.php'));
                $this->assertSame('1080', $item->width);
                $this->assertSame(1, $item->total_editable);
                $this->assertStringEndsWith('/res/data.html', $api['data'][0]['video_url']);
                // Admin preview goes through the proxy; traversal outside the folder is refused.
                $this->get("/video-template/templates/{$item->id}/player/res/data.html")->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
                $this->get("/video-template/templates/{$item->id}/player/res/data.json")->assertOk();
                $this->get("/video-template/templates/{$item->id}/player/../../../../.env")->assertNotFound();
                break;
            case 'ai-video':
                $this->assertNotNull($item->thumbnail);
                $this->assertFileExists(public_path($item->fileRelPath('thumbnail')));
                $this->assertNotNull($api['data'][0]['thumbnail_url']);
                break;
            case 'ai-image':
                $this->assertSame('A watercolor portrait', $api['data'][0]['prompt']);
                break;
        }
    }

    public function test_video_template_zip_without_player_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $cat = \App\Models\VideoTemplateCategory::create(['name' => 'X', 'image' => 'upload/x.webp', 'status' => true]);
        $cat->apps()->attach($this->mobileApp->id);

        $this->post('/video-template/templates', [
            'category_id' => $cat->id, 'title' => 'T', 'thumbnail' => $this->webp(),
            'zip' => $this->zip(['readme.txt' => 'hi']),
        ])->assertSessionHasErrors('zip');
    }

    public function test_category_can_belong_to_multiple_apps(): void
    {
        $this->actingAs($this->admin);

        // One category assigned to BOTH apps at once.
        $this->post('/frame/categories', [
            'app_ids' => [$this->mobileApp->id, $this->otherApp->id],
            'name'    => 'Shared', 'status' => 1, 'image' => $this->webp(),
        ])->assertSessionDoesntHaveErrors();

        $cat = \App\Models\FrameCategory::firstOrFail();
        $this->assertEqualsCanonicalizing([$this->mobileApp->id, $this->otherApp->id], $cat->apps->pluck('id')->all());

        // Both apps see it through the API.
        foreach (['com.test.demo', 'com.test.other'] as $pkg) {
            $this->postJson('/api/v1/frame/getCategories', ['package_name' => $pkg], $this->bearer())
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Shared');
        }

        // Re-assign to only one app → the other app stops seeing it.
        $this->put("/frame/categories/{$cat->id}", ['app_ids' => [$this->mobileApp->id], 'name' => 'Shared', 'status' => 1])
            ->assertSessionDoesntHaveErrors();
        $this->assertEqualsCanonicalizing([$this->mobileApp->id], $cat->fresh()->apps->pluck('id')->all());
        $this->postJson('/api/v1/frame/getCategories', ['package_name' => 'com.test.other'], $this->bearer())->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/frame/getCategories', ['package_name' => 'com.test.demo'], $this->bearer())->assertJsonCount(1, 'data');

        // At least one app is required.
        $this->post('/frame/categories', ['app_ids' => [], 'name' => 'NoApp', 'status' => 1, 'image' => $this->webp()])
            ->assertSessionHasErrors('app_ids');
    }

    public function test_db_stores_only_the_filename_path_is_rebuilt(): void
    {
        $this->actingAs($this->admin);

        // Category: DB keeps only "logo.webp"; the rebuilt path points under the category folder.
        $this->post('/dynamic-frame/categories', [
            'app_ids' => [$this->mobileApp->id], 'name' => 'My Frames', 'status' => 1, 'image' => $this->webp('logo'),
        ])->assertSessionDoesntHaveErrors();
        $cat = \App\Models\DynamicFrameCategory::firstOrFail();
        $this->assertSame('logo.webp', $cat->image); // name only, not a path
        $this->assertSame('upload/dynamic-frame/My Frames/logo.webp', $cat->imageRelPath());
        $this->assertFileExists(public_path($cat->imageRelPath()));

        // Item files: DB holds the name, rebuilt path is upload/{module}/{Category}/{field}/{name}.
        $this->post('/dynamic-frame/frames', [
            'category_id' => $cat->id, 'status' => 1, 'aspect_ratio' => '4:5', 'zip' => $this->dynZip('rose.zip'), 'thumbnail' => $this->webp('rose-thumb'),
        ])->assertSessionDoesntHaveErrors();
        $dyn = \App\Models\DynamicFrame::firstOrFail();
        $this->assertSame('rose.zip', $dyn->zip);
        $this->assertSame('rose-thumb.webp', $dyn->thumbnail);
        $this->assertSame('upload/dynamic-frame/My Frames/zip/rose.zip', $dyn->fileRelPath('zip'));
        $this->assertSame('upload/dynamic-frame/My Frames/thumbnail/rose-thumb.webp', $dyn->fileRelPath('thumbnail'));
        $this->assertFileExists(public_path($dyn->fileRelPath('zip')));
        $this->assertFileExists(public_path($dyn->fileRelPath('thumbnail')));

        // Same name in the same category → a timestamp is appended; the first file survives.
        $this->post('/dynamic-frame/frames', [
            'category_id' => $cat->id, 'status' => 1, 'aspect_ratio' => '4:5', 'zip' => $this->dynZip('rose.zip'), 'thumbnail' => $this->webp('rose'),
        ])->assertSessionDoesntHaveErrors();
        $second = \App\Models\DynamicFrame::latest('id')->firstOrFail();
        $this->assertNotSame('rose.zip', $second->zip);
        $this->assertStringStartsWith('rose_', $second->zip);
        $this->assertFileExists(public_path($dyn->fileRelPath('zip')));
        $this->assertFileExists(public_path($second->fileRelPath('zip')));

        // AI-image is stored directly in the category folder (no field sub-folder).
        $this->post('/ai-image/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Portraits', 'status' => 1, 'image' => $this->webp('c')]);
        $aiCat = \App\Models\AiImageCategory::firstOrFail();
        $this->post('/ai-image/images', ['category_id' => $aiCat->id, 'status' => 1, 'prompt' => 'x', 'image' => $this->webp('sunset')]);
        $ai = \App\Models\AiImage::firstOrFail();
        $this->assertSame('sunset.webp', $ai->image);
        $this->assertSame('upload/ai-image/Portraits/sunset.webp', $ai->fileRelPath('image'));
    }

    public function test_edit_replaces_old_file_and_delete_removes_it(): void
    {
        $this->actingAs($this->admin);
        $this->post('/dynamic-frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Edit Cat', 'status' => 1, 'image' => $this->webp('cover')]);
        $cat = \App\Models\DynamicFrameCategory::firstOrFail();

        $this->post('/dynamic-frame/frames', ['category_id' => $cat->id, 'status' => 1, 'aspect_ratio' => '1:1', 'zip' => $this->dynZip('old.zip'), 'thumbnail' => $this->webp('oldthumb')]);
        $dyn     = \App\Models\DynamicFrame::firstOrFail();
        $oldPath = $dyn->fileRelPath('zip');
        $this->assertFileExists(public_path($oldPath));

        // Edit with a new zip: old file deleted, new file present, thumbnail untouched.
        $this->put("/dynamic-frame/frames/{$dyn->id}", ['category_id' => $cat->id, 'status' => 1, 'aspect_ratio' => '1:1', 'zip' => $this->dynZip('new.zip')])
            ->assertSessionDoesntHaveErrors();
        $dyn->refresh();
        $this->assertSame('new.zip', $dyn->zip);
        $this->assertFileDoesNotExist(public_path($oldPath));
        $this->assertFileExists(public_path($dyn->fileRelPath('zip')));
        $this->assertFileExists(public_path($dyn->fileRelPath('thumbnail')));

        // Delete removes all of the item's files.
        $paths = $dyn->allFileRelPaths();
        $this->delete("/dynamic-frame/frames/{$dyn->id}")->assertSessionHas('success');
        foreach ($paths as $p) {
            $this->assertFileDoesNotExist(public_path($p));
        }
    }

    public function test_changing_category_moves_the_files(): void
    {
        $this->actingAs($this->admin);
        foreach (['Cat A', 'Cat B'] as $name) {
            $this->post('/dynamic-frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => $name, 'status' => 1, 'image' => $this->webp('c')]);
        }
        $a = \App\Models\DynamicFrameCategory::where('name', 'Cat A')->firstOrFail();
        $b = \App\Models\DynamicFrameCategory::where('name', 'Cat B')->firstOrFail();

        $this->post('/dynamic-frame/frames', ['category_id' => $a->id, 'status' => 1, 'aspect_ratio' => '1:1', 'zip' => $this->dynZip('pic.zip'), 'thumbnail' => $this->webp('pic2')]);
        $dyn = \App\Models\DynamicFrame::firstOrFail();
        $this->assertStringContainsString('/Cat A/', $dyn->fileRelPath('zip'));
        $this->assertFileExists(public_path($dyn->fileRelPath('zip')));

        // Move to Cat B without re-uploading: the file physically moves.
        $this->put("/dynamic-frame/frames/{$dyn->id}", ['category_id' => $b->id, 'status' => 1, 'aspect_ratio' => '1:1'])->assertSessionDoesntHaveErrors();
        $dyn->refresh();
        $this->assertSame($b->id, $dyn->category_id);
        $this->assertStringContainsString('/Cat B/', $dyn->fileRelPath('zip'));
        $this->assertFileExists(public_path($dyn->fileRelPath('zip')));
        $this->assertFileDoesNotExist(public_path('upload/dynamic-frame/Cat A/zip/pic.zip'));
    }

    public function test_renaming_category_moves_its_folder(): void
    {
        $this->actingAs($this->admin);
        $this->post('/dynamic-frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Old Name', 'status' => 1, 'image' => $this->webp('cover')]);
        $cat = \App\Models\DynamicFrameCategory::firstOrFail();
        $this->post('/dynamic-frame/frames', ['category_id' => $cat->id, 'status' => 1, 'aspect_ratio' => '1:1', 'zip' => $this->dynZip('a.zip'), 'thumbnail' => $this->webp('b')]);
        $dyn = \App\Models\DynamicFrame::firstOrFail();
        $this->assertFileExists(public_path($dyn->fileRelPath('zip')));

        // Rename the category → the whole folder (cover + item files) follows.
        $this->put("/dynamic-frame/categories/{$cat->id}", ['app_ids' => [$this->mobileApp->id], 'name' => 'New Name', 'status' => 1])
            ->assertSessionDoesntHaveErrors();
        $cat->refresh();
        $dyn->refresh();
        $this->assertSame('New Name', $cat->name);
        $this->assertFileExists(public_path($cat->imageRelPath()));               // cover moved
        $this->assertStringContainsString('/New Name/', $dyn->fileRelPath('zip'));
        $this->assertFileExists(public_path($dyn->fileRelPath('zip')));           // item moved
        $this->assertFileDoesNotExist(public_path('upload/dynamic-frame/Old Name/cover.webp'));
    }

    public function test_get_categories_previews_latest_five_items(): void
    {
        $this->actingAs($this->admin);
        $this->post('/dynamic-frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Preview', 'status' => 1, 'image' => $this->webp('c')]);
        $cat = \App\Models\DynamicFrameCategory::firstOrFail();

        // 7 items (one inactive) → preview should return the 5 latest ACTIVE, newest first.
        for ($i = 1; $i <= 7; $i++) {
            $this->post('/dynamic-frame/frames', [
                'category_id' => $cat->id, 'status' => $i === 2 ? 0 : 1, 'aspect_ratio' => '1:1',
                'zip' => $this->dynZip("f{$i}.zip"), 'thumbnail' => $this->webp("t{$i}"),
            ]);
        }

        $res  = $this->postJson('/api/v1/dynamic-frame/getCategories', ['package_name' => 'com.test.demo'], $this->bearer())->assertOk();
        $prev = $res->json('data.0.items');
        $this->assertCount(5, $prev);                                   // capped at 5
        $this->assertSame($res->json('data.0.items_count'), 6);         // 6 active in total
        $ids = array_column($prev, 'id');
        $this->assertSame($ids, collect($ids)->sortDesc()->values()->all()); // newest first
    }

    public function test_category_order_is_set_from_index_popup_and_used_by_api(): void
    {
        $this->actingAs($this->admin);

        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $this->post('/ai-image/categories', ['app_ids' => [$this->mobileApp->id], 'name' => $name, 'status' => 1, 'image' => $this->webp()]);
        }
        $byName = fn ($n) => \App\Models\AiImageCategory::where('name', $n)->value('id');

        // A new category shows FIRST by default (newest on top).
        $this->assertSame('Gamma', $this->postJson('/api/v1/ai-image/getCategories', ['package_name' => 'com.test.demo'], $this->bearer())->json('data.0.name'));

        // Set a custom order via the Index popup: Beta, Gamma, Alpha.
        $this->postJson('/ai-image/categories/reorder', ['order' => [$byName('Beta'), $byName('Gamma'), $byName('Alpha')]])
            ->assertJson(['success' => true]);

        $names = collect($this->postJson('/api/v1/ai-image/getCategories', ['package_name' => 'com.test.demo'], $this->bearer())->json('data'))->pluck('name')->all();
        $this->assertSame(['Beta', 'Gamma', 'Alpha'], $names);

        // A brand-new category jumps to the front again (sort_order 0 < the ordered ones).
        $this->post('/ai-image/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Fresh', 'status' => 1, 'image' => $this->webp()]);
        $this->assertSame('Fresh', $this->postJson('/api/v1/ai-image/getCategories', ['package_name' => 'com.test.demo'], $this->bearer())->json('data.0.name'));
    }

    public function test_items_list_shows_newest_add_or_edit_first(): void
    {
        $this->actingAs($this->admin);
        $this->post('/ai-image/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'C', 'status' => 1, 'image' => $this->webp()]);
        $cat = \App\Models\AiImageCategory::firstOrFail();

        foreach (['one', 'two', 'three'] as $p) {
            $this->post('/ai-image/images', ['category_id' => $cat->id, 'status' => 1, 'prompt' => $p, 'image' => $this->webp()]);
        }
        $first = \App\Models\AiImage::where('prompt', 'one')->firstOrFail();

        // Newest add is on top.
        $this->assertSame('three', \App\Models\AiImage::query()->orderByDesc('updated_at')->orderByDesc('id')->first()->prompt);

        // Editing the oldest bumps it to the top (updated_at).
        $this->travel(1)->second();
        $this->put('/ai-image/images/' . $first->id, ['category_id' => $cat->id, 'status' => 1, 'prompt' => 'one-edited']);
        $topId = \App\Models\AiImage::query()->orderByDesc('updated_at')->orderByDesc('id')->first()->id;
        $this->assertSame($first->id, $topId);
    }

    public function test_non_webp_images_are_rejected(): void
    {
        $this->actingAs($this->admin);
        $png = UploadedFile::fake()->image('x.png', 100, 100);
        $jpg = UploadedFile::fake()->image('x.jpg', 100, 100);

        // Category image must be webp.
        $this->post('/ai-image/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Cat', 'status' => 1, 'image' => $png])
            ->assertSessionHasErrors('image');

        // AI image (single field) must be webp.
        $cat = \App\Models\AiImageCategory::create(['name' => 'C', 'image' => 'upload/x.webp', 'status' => true]);
        $cat->apps()->attach($this->mobileApp->id);
        $this->post('/ai-image/images', ['category_id' => $cat->id, 'status' => 1, 'prompt' => 'p', 'image' => $png])
            ->assertSessionHasErrors('image');

        // Pair-gallery frames (Frame + Template) must be webp.
        $fc = \App\Models\FrameCategory::create(['name' => 'F', 'image' => 'upload/x.webp', 'status' => true]);
        $fc->apps()->attach($this->mobileApp->id);
        $this->post('/frame/frames', ['category_id' => $fc->id, 'status' => 1] + $this->pairNew([$png], [$this->webp()]))
            ->assertSessionHasErrors('frame_files.0');

        $tc = \App\Models\TemplateCategory::create(['name' => 'T', 'image' => 'upload/x.webp', 'status' => true]);
        $tc->apps()->attach($this->mobileApp->id);
        $this->post('/template/templates', ['category_id' => $tc->id, 'status' => 1] + $this->pairNew([$this->webp()], [$jpg], [1]))
            ->assertSessionHasErrors('thumb_files.0');

        $bc = \App\Models\BabyBackdropCategory::create(['name' => 'B', 'image' => 'upload/x.webp', 'orientation' => 'portrait', 'status' => true]);
        $bc->apps()->attach($this->mobileApp->id);
        $this->post('/baby-backdrop/backdrops', ['category_id' => $bc->id, 'status' => 1, 'new_images' => [$png]])
            ->assertSessionHasErrors('new_images.0');

        // A webp still succeeds (template pair + frame pair).
        $this->post('/template/templates', ['category_id' => $tc->id, 'status' => 1] + $this->pairNew([$this->webp()], [$this->webp()], [3]))
            ->assertSessionDoesntHaveErrors();
        $this->post('/frame/frames', ['category_id' => $fc->id, 'status' => 1] + $this->pairNew([$this->webp()], [$this->webp()]))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_same_asset_name_in_category_gets_timestamp(): void
    {
        $this->actingAs($this->admin);
        $this->post('/frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Dup', 'status' => 1, 'image' => $this->webp('c')]);
        $cat = \App\Models\FrameCategory::firstOrFail();

        // ADD: two frames with the SAME filename in one submit → 2nd gets a timestamp.
        $this->post('/frame/frames', ['category_id' => $cat->id, 'status' => 1]
            + $this->pairNew([$this->webp('rose'), $this->webp('rose')], [$this->webp('t'), $this->webp('t')]))
            ->assertSessionDoesntHaveErrors();

        $frame = \App\Models\Frame::firstOrFail();
        $this->assertSame('rose.webp', $frame->images[0]['frame']);
        $this->assertNotSame('rose.webp', $frame->images[1]['frame']);
        $this->assertMatchesRegularExpression('/^rose_\d{14}/', $frame->images[1]['frame']); // rose_YYYYMMDDHHMMSS
        // Thumbnails with the same name are timestamped too (separate folder).
        $this->assertSame('t.webp', $frame->images[0]['thumbnail']);
        $this->assertMatchesRegularExpression('/^t_\d{14}/', $frame->images[1]['thumbnail']);

        // EDIT: keep the 2 existing + add another "rose.webp" → also timestamped, originals kept.
        $this->put('/frame/frames/' . $frame->id, [
            'category_id' => $cat->id, 'status' => 1,
            'slots' => [0 => '0', 1 => '1', 2 => 'new'],
            'frame_files' => [2 => $this->webp('rose')], 'thumb_files' => [2 => $this->webp('t')],
        ])->assertSessionDoesntHaveErrors();
        $frame->refresh();
        $this->assertCount(3, $frame->images);
        $this->assertSame('rose.webp', $frame->images[0]['frame']);            // original untouched
        $this->assertMatchesRegularExpression('/^rose_\d{14}/', $frame->images[2]['frame']);

        // Every stored file really exists (none overwrote another).
        foreach ($frame->imageRelPaths() as $rel) {
            $this->assertFileExists(public_path($rel));
        }
    }

    public function test_frame_pairs_frame_with_thumbnail_and_api(): void
    {
        $this->actingAs($this->admin);
        $this->post('/frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Pairs', 'status' => 1, 'image' => $this->webp('c')]);
        $cat = \App\Models\FrameCategory::firstOrFail();

        // Add two frames, each with its thumbnail, in one go.
        $this->post('/frame/frames', ['category_id' => $cat->id, 'status' => 1]
            + $this->pairNew([$this->webp('rose'), $this->webp('tulip')], [$this->webp('rose-t'), $this->webp('tulip-t')]))
            ->assertSessionDoesntHaveErrors();

        $frame = \App\Models\Frame::firstOrFail();
        $this->assertCount(2, $frame->images);
        $this->assertSame('rose.webp', $frame->images[0]['frame']);
        $this->assertSame('rose-t.webp', $frame->images[0]['thumbnail']);
        $this->assertFileExists(public_path('upload/frame/Pairs/frame/rose.webp'));
        $this->assertFileExists(public_path('upload/frame/Pairs/thumbnail/rose-t.webp'));

        // A new row with a frame but no thumbnail is rejected.
        $this->post('/frame/frames/' . $frame->id, [
            '_method' => 'PUT', 'category_id' => $cat->id, 'status' => 1,
            'slots' => [0 => '0', 1 => '1', 2 => 'new'], 'frame_files' => [2 => $this->webp('x')], 'thumb_files' => [],
        ])->assertSessionHasErrors('frame_files');

        // API returns frame_url + thumbnail_url for every frame.
        $api = $this->postJson('/api/v1/frame/getFramesByCategory', ['package_name' => 'com.test.demo', 'category_id' => $cat->id], $this->bearer())->assertOk();
        $api->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['frame_url', 'thumbnail_url']]]);
        $this->assertStringContainsString('/frame/Pairs/frame/', $api->json('data.0.frame_url'));
        $this->assertStringContainsString('/frame/Pairs/thumbnail/', $api->json('data.0.thumbnail_url'));

        // Keeping the 2 existing + adding 1 new → 3 entries, originals untouched.
        $this->put('/frame/frames/' . $frame->id, [
            'category_id' => $cat->id, 'status' => 1,
            'slots' => [0 => '0', 1 => '1', 2 => 'new'],
            'frame_files' => [2 => $this->webp('lily')], 'thumb_files' => [2 => $this->webp('lily-t')],
        ])->assertSessionDoesntHaveErrors();
        $frame->refresh();
        $this->assertCount(3, $frame->images); // 2 kept + 1 new
        $this->assertFileExists(public_path('upload/frame/Pairs/frame/rose.webp'));  // originals untouched
        $this->assertFileExists(public_path('upload/frame/Pairs/frame/lily.webp'));  // new one added
    }

    public function test_pair_edit_can_replace_and_reorder(): void
    {
        $this->actingAs($this->admin);
        $this->post('/frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'RR', 'status' => 1, 'image' => $this->webp('c')]);
        $cat = \App\Models\FrameCategory::firstOrFail();
        $this->post('/frame/frames', ['category_id' => $cat->id, 'status' => 1]
            + $this->pairNew([$this->webp('a'), $this->webp('b')], [$this->webp('at'), $this->webp('bt')]));
        $frame = \App\Models\Frame::firstOrFail();
        $this->assertSame(['a.webp', 'b.webp'], array_column($frame->images, 'frame'));

        // Reorder (b, a) and replace entry b's frame with "new".
        $this->put('/frame/frames/' . $frame->id, [
            'category_id' => $cat->id, 'status' => 1,
            'slots' => [0 => '1', 1 => '0'],                 // b first, a second
            'frame_files' => [0 => $this->webp('newb')],     // replace b's frame
        ])->assertSessionDoesntHaveErrors();
        $frame->refresh();
        $this->assertSame(['newb.webp', 'a.webp'], array_column($frame->images, 'frame')); // reordered + replaced
        $this->assertFileDoesNotExist(public_path('upload/frame/RR/frame/b.webp'));        // old frame deleted
        $this->assertFileExists(public_path('upload/frame/RR/frame/newb.webp'));
        $this->assertFileExists(public_path('upload/frame/RR/frame/a.webp'));
    }

    public function test_gallery_display_order_matches_api_order(): void
    {
        $this->actingAs($this->admin);
        $this->post('/frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Seq', 'status' => 1, 'image' => $this->webp('c')]);
        $cat = \App\Models\FrameCategory::firstOrFail();

        // Add two frames in one go: rose, then tulip.
        $this->post('/frame/frames', ['category_id' => $cat->id, 'status' => 1]
            + $this->pairNew([$this->webp('rose'), $this->webp('tulip')], [$this->webp('rose-t'), $this->webp('tulip-t')]))
            ->assertSessionDoesntHaveErrors();

        $frame    = \App\Models\Frame::firstOrFail();
        $stored   = array_column($frame->images, 'frame');            // raw array order (what admin shows)
        $display  = array_map('basename', array_map('urldecode', $frame->frameUrls())); // admin list order

        $items    = $this->postJson('/api/v1/frame/getFramesByCategory', ['package_name' => 'com.test.demo', 'category_id' => $cat->id], $this->bearer())->assertOk();
        $itemSeq  = array_map(fn ($u) => basename(urldecode(parse_url($u, PHP_URL_PATH))), $items->json('data.*.frame_url'));

        $cats     = $this->postJson('/api/v1/frame/getCategories', ['package_name' => 'com.test.demo'], $this->bearer())->assertOk();
        $prevSeq  = array_map(fn ($u) => basename(urldecode(parse_url($u, PHP_URL_PATH))), $cats->json('data.0.items.*.frame_url'));

        // Admin display, items API and getCategories preview are all in the SAME order as the stored array.
        $this->assertSame(['rose.webp', 'tulip.webp'], $stored);
        $this->assertSame($stored, $display, 'admin display order diverged from stored order');
        $this->assertSame($stored, $itemSeq, 'items API order diverged from admin display');
        $this->assertSame($stored, $prevSeq, 'getCategories preview order diverged from admin display');

        // Editing in a new frame at the top (slot order) keeps every surface in lockstep.
        $this->put('/frame/frames/' . $frame->id, [
            'category_id' => $cat->id, 'status' => 1,
            'slots' => [0 => 'new', 1 => '0', 2 => '1'],
            'frame_files' => [0 => $this->webp('lily')], 'thumb_files' => [0 => $this->webp('lily-t')],
        ])->assertSessionDoesntHaveErrors();
        $frame->refresh();

        $itemSeq2 = array_map(fn ($u) => basename(urldecode(parse_url($u, PHP_URL_PATH))),
            $this->postJson('/api/v1/frame/getFramesByCategory', ['package_name' => 'com.test.demo', 'category_id' => $cat->id], $this->bearer())->json('data.*.frame_url'));
        $this->assertSame(['lily.webp', 'rose.webp', 'tulip.webp'], array_column($frame->images, 'frame'));
        $this->assertSame(array_column($frame->images, 'frame'), $itemSeq2); // new frame first, API agrees
    }

    public function test_api_list_has_no_error_response_block(): void
    {
        $this->actingAs($this->admin);
        $this->get('/api-list')->assertOk()->assertDontSee('Error responses');
    }

    public function test_secure_media_streams_by_token_only(): void
    {
        $this->actingAs($this->admin);
        $this->post('/frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Cat', 'status' => 1, 'image' => $this->webp()]);

        $html = $this->get('/frame/categories')->getContent();
        $this->assertMatchesRegularExpression('~/secure-media\?token=[\w-]+~', $html);
        preg_match('~/secure-media\?token=([\w-]+)~', $html, $m);

        $this->get('/secure-media?token=' . $m[1])->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get('/secure-media?token=tampered')->assertForbidden();
        auth()->logout();
        $this->get('/secure-media?token=' . $m[1])->assertRedirect('/login');
    }

    public function test_api_token_can_be_regenerated(): void
    {
        $this->actingAs($this->admin);
        $old = Setting::apiToken();
        $this->get('/api-list')->assertOk()->assertSee('getFramesByCategory');
        $this->post('/api-list/token')->assertSessionHas('success');

        $this->postJson('/api/v1/frame/getCategories', ['package_name' => 'com.test.demo'], ['Authorization' => "Bearer {$old}"])->assertUnauthorized();
        $this->postJson('/api/v1/frame/getCategories', ['package_name' => 'com.test.demo'], $this->bearer())->assertOk();
        $this->get('/api-list/postman')->assertOk()->assertJsonPath('item.0.name', 'Frame');
    }

    public function test_ai_video_missing_thumbnail_can_be_filled_later(): void
    {
        $this->actingAs($this->admin);
        $cat = \App\Models\AiVideoCategory::create(['name' => 'V', 'image' => 'upload/x.webp', 'status' => true]);
        $cat->apps()->attach($this->mobileApp->id);
        $this->post('/ai-video/videos', ['category_id' => $cat->id, 'prompt' => 'p', 'video' => $this->video(), 'status' => 1])->assertSessionDoesntHaveErrors();
        $video = \App\Models\AiVideo::firstOrFail();
        $this->assertNull($video->thumbnail);

        $this->get('/ai-video/videos')->assertSee('data-missing-thumb', false);
        $this->post("/ai-video/videos/{$video->id}/thumbnail", ['thumbnail_data' => $this->pngDataUrl()])->assertJson(['success' => true]);
        $this->assertFileExists(public_path($video->fresh()->fileRelPath('thumbnail')));
    }
}
