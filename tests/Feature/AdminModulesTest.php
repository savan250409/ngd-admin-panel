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

    private function zip(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip  = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'template.zip', 'application/zip', null, true);
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
            'frame'          => ['new_frames' => [$img('f1'), $img('f2')], 'new_thumbnails' => [$img('t1'), $img('t2')]], // frame+thumbnail pairs
            'template'       => ['frame' => $img(), 'thumbnail' => $img('t.jpg'), 'count' => 3],
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
            'template'       => ['count' => 5],
            'dynamic-frame'  => ['aspect_ratio' => '1:1'],
            'video-template' => ['title' => 'Renamed'],
            'ai-image', 'ai-video' => ['prompt' => 'Updated prompt'],
            'frame', 'baby-backdrop' => ['existing' => [1]], // gallery: keep only the 2nd saved image
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

        // Category with items cannot be deleted; delete item first — its files go too.
        $this->delete("/{$module}/categories/{$category->id}")->assertSessionHas('error');
        $fileRels = !empty($cfg['gallery']) ? $item->imageRelPaths() : $item->allFileRelPaths();
        $this->delete("/{$module}/{$slug}/{$item->id}")->assertSessionHas('success');
        $this->assertNull($item->fresh());
        foreach ($fileRels as $rel) {
            $this->assertFileDoesNotExist(public_path($rel));
        }
        $this->delete("/{$module}/categories/{$category->id}")->assertSessionHas('success');
        $this->assertFileDoesNotExist(public_path($category->imageRelPath()));
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
                $this->assertSame(3, $api['data'][0]['count']);
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
        $this->post('/template/categories', [
            'app_ids' => [$this->mobileApp->id], 'name' => 'My Templates', 'status' => 1, 'image' => $this->webp('logo'),
        ])->assertSessionDoesntHaveErrors();
        $cat = \App\Models\TemplateCategory::firstOrFail();
        $this->assertSame('logo.webp', $cat->image); // name only, not a path
        $this->assertSame('upload/template/My Templates/logo.webp', $cat->imageRelPath());
        $this->assertFileExists(public_path($cat->imageRelPath()));

        // Item files: DB holds the name, rebuilt path is upload/{module}/{Category}/{field}/{name}.
        $this->post('/template/templates', [
            'category_id' => $cat->id, 'status' => 1, 'count' => 2, 'frame' => $this->webp('rose'), 'thumbnail' => $this->webp('rose-thumb'),
        ])->assertSessionDoesntHaveErrors();
        $tpl = \App\Models\Template::firstOrFail();
        $this->assertSame('rose.webp', $tpl->frame);
        $this->assertSame('rose-thumb.webp', $tpl->thumbnail);
        $this->assertSame('upload/template/My Templates/frame/rose.webp', $tpl->fileRelPath('frame'));
        $this->assertSame('upload/template/My Templates/thumbnail/rose-thumb.webp', $tpl->fileRelPath('thumbnail'));
        $this->assertFileExists(public_path($tpl->fileRelPath('frame')));
        $this->assertFileExists(public_path($tpl->fileRelPath('thumbnail')));

        // Same name in the same category → a timestamp is appended; the first file survives.
        $this->post('/template/templates', [
            'category_id' => $cat->id, 'status' => 1, 'count' => 2, 'frame' => $this->webp('rose'), 'thumbnail' => $this->webp('rose'),
        ])->assertSessionDoesntHaveErrors();
        $second = \App\Models\Template::latest('id')->firstOrFail();
        $this->assertNotSame('rose.webp', $second->frame);
        $this->assertStringStartsWith('rose_', $second->frame);
        $this->assertFileExists(public_path($tpl->fileRelPath('frame')));
        $this->assertFileExists(public_path($second->fileRelPath('frame')));

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
        $this->post('/template/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Edit Cat', 'status' => 1, 'image' => $this->webp('cover')]);
        $cat = \App\Models\TemplateCategory::firstOrFail();

        $this->post('/template/templates', ['category_id' => $cat->id, 'status' => 1, 'count' => 1, 'frame' => $this->webp('old'), 'thumbnail' => $this->webp('oldthumb')]);
        $tpl     = \App\Models\Template::firstOrFail();
        $oldPath = $tpl->fileRelPath('frame');
        $this->assertFileExists(public_path($oldPath));

        // Edit with a new frame image: old file deleted, new file present.
        $this->put("/template/templates/{$tpl->id}", ['category_id' => $cat->id, 'status' => 1, 'count' => 1, 'frame' => $this->webp('new')])
            ->assertSessionDoesntHaveErrors();
        $tpl->refresh();
        $this->assertSame('new.webp', $tpl->frame);
        $this->assertFileDoesNotExist(public_path($oldPath));           // old deleted
        $this->assertFileExists(public_path($tpl->fileRelPath('frame'))); // new added
        $this->assertFileExists(public_path($tpl->fileRelPath('thumbnail'))); // untouched

        // Delete removes all of the item's files.
        $paths = $tpl->allFileRelPaths();
        $this->delete("/template/templates/{$tpl->id}")->assertSessionHas('success');
        foreach ($paths as $p) {
            $this->assertFileDoesNotExist(public_path($p));
        }
    }

    public function test_changing_category_moves_the_files(): void
    {
        $this->actingAs($this->admin);
        foreach (['Cat A', 'Cat B'] as $name) {
            $this->post('/template/categories', ['app_ids' => [$this->mobileApp->id], 'name' => $name, 'status' => 1, 'image' => $this->webp('c')]);
        }
        $a = \App\Models\TemplateCategory::where('name', 'Cat A')->firstOrFail();
        $b = \App\Models\TemplateCategory::where('name', 'Cat B')->firstOrFail();

        $this->post('/template/templates', ['category_id' => $a->id, 'status' => 1, 'count' => 1, 'frame' => $this->webp('pic'), 'thumbnail' => $this->webp('pic2')]);
        $tpl = \App\Models\Template::firstOrFail();
        $this->assertStringContainsString('/Cat A/', $tpl->fileRelPath('frame'));
        $this->assertFileExists(public_path($tpl->fileRelPath('frame')));

        // Move to Cat B without re-uploading: the file physically moves.
        $this->put("/template/templates/{$tpl->id}", ['category_id' => $b->id, 'status' => 1, 'count' => 1])->assertSessionDoesntHaveErrors();
        $tpl->refresh();
        $this->assertSame($b->id, $tpl->category_id);
        $this->assertStringContainsString('/Cat B/', $tpl->fileRelPath('frame'));
        $this->assertFileExists(public_path($tpl->fileRelPath('frame')));
        $this->assertFileDoesNotExist(public_path('upload/template/Cat A/frame/pic.webp'));
    }

    public function test_renaming_category_moves_its_folder(): void
    {
        $this->actingAs($this->admin);
        $this->post('/template/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Old Name', 'status' => 1, 'image' => $this->webp('cover')]);
        $cat = \App\Models\TemplateCategory::firstOrFail();
        $this->post('/template/templates', ['category_id' => $cat->id, 'status' => 1, 'count' => 1, 'frame' => $this->webp('a'), 'thumbnail' => $this->webp('b')]);
        $tpl = \App\Models\Template::firstOrFail();
        $this->assertFileExists(public_path($tpl->fileRelPath('frame')));

        // Rename the category → the whole folder (cover + item files) follows.
        $this->put("/template/categories/{$cat->id}", ['app_ids' => [$this->mobileApp->id], 'name' => 'New Name', 'status' => 1])
            ->assertSessionDoesntHaveErrors();
        $cat->refresh();
        $tpl->refresh();
        $this->assertSame('New Name', $cat->name);
        $this->assertFileExists(public_path($cat->imageRelPath()));               // cover moved
        $this->assertStringContainsString('/New Name/', $tpl->fileRelPath('frame'));
        $this->assertFileExists(public_path($tpl->fileRelPath('frame')));         // item moved
        $this->assertFileDoesNotExist(public_path('upload/template/Old Name/cover.webp'));
    }

    public function test_get_categories_previews_latest_five_items(): void
    {
        $this->actingAs($this->admin);
        $this->post('/template/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Preview', 'status' => 1, 'image' => $this->webp('c')]);
        $cat = \App\Models\TemplateCategory::firstOrFail();

        // 7 items (one inactive) → preview should return the 5 latest ACTIVE, newest first.
        for ($i = 1; $i <= 7; $i++) {
            $this->post('/template/templates', [
                'category_id' => $cat->id, 'status' => $i === 2 ? 0 : 1, 'count' => 1,
                'frame' => $this->webp("f{$i}"), 'thumbnail' => $this->webp("t{$i}"),
            ]);
        }

        $res  = $this->postJson('/api/v1/template/getCategories', ['package_name' => 'com.test.demo'], $this->bearer())->assertOk();
        $prev = $res->json('data.0.items');
        $this->assertCount(5, $prev);                                   // capped at 5
        $this->assertSame($res->json('data.0.items_count'), 6);         // 6 active in total
        $ids = array_column($prev, 'id');
        $this->assertSame($ids, collect($ids)->sortDesc()->values()->all()); // newest first
    }

    public function test_non_webp_images_are_rejected(): void
    {
        $this->actingAs($this->admin);
        $png = UploadedFile::fake()->image('x.png', 100, 100);
        $jpg = UploadedFile::fake()->image('x.jpg', 100, 100);

        // Category image must be webp.
        $this->post('/template/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Cat', 'status' => 1, 'image' => $png])
            ->assertSessionHasErrors('image');

        // Template frame + thumbnail must be webp.
        $cat = \App\Models\TemplateCategory::create(['name' => 'C', 'image' => 'upload/x.webp', 'status' => true]);
        $cat->apps()->attach($this->mobileApp->id);
        $this->post('/template/templates', ['category_id' => $cat->id, 'status' => 1, 'count' => 1, 'frame' => $png, 'thumbnail' => $this->webp()])
            ->assertSessionHasErrors('frame');
        $this->post('/template/templates', ['category_id' => $cat->id, 'status' => 1, 'count' => 1, 'frame' => $this->webp(), 'thumbnail' => $jpg])
            ->assertSessionHasErrors('thumbnail');

        // Frame pairs must be webp; backdrop images must be webp.
        $fc = \App\Models\FrameCategory::create(['name' => 'F', 'image' => 'upload/x.webp', 'status' => true]);
        $fc->apps()->attach($this->mobileApp->id);
        $this->post('/frame/frames', ['category_id' => $fc->id, 'status' => 1, 'new_frames' => [$png], 'new_thumbnails' => [$this->webp()]])
            ->assertSessionHasErrors('new_frames.0');

        $bc = \App\Models\BabyBackdropCategory::create(['name' => 'B', 'image' => 'upload/x.webp', 'orientation' => 'portrait', 'status' => true]);
        $bc->apps()->attach($this->mobileApp->id);
        $this->post('/baby-backdrop/backdrops', ['category_id' => $bc->id, 'status' => 1, 'new_images' => [$png]])
            ->assertSessionHasErrors('new_images.0');

        // A webp still succeeds (template item + frame pair).
        $this->post('/template/templates', ['category_id' => $cat->id, 'status' => 1, 'count' => 1, 'frame' => $this->webp(), 'thumbnail' => $this->webp()])
            ->assertSessionDoesntHaveErrors();
        $this->post('/frame/frames', ['category_id' => $fc->id, 'status' => 1, 'new_frames' => [$this->webp()], 'new_thumbnails' => [$this->webp()]])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_frame_pairs_frame_with_thumbnail_and_api(): void
    {
        $this->actingAs($this->admin);
        $this->post('/frame/categories', ['app_ids' => [$this->mobileApp->id], 'name' => 'Pairs', 'status' => 1, 'image' => $this->webp('c')]);
        $cat = \App\Models\FrameCategory::firstOrFail();

        // Add two frames, each with its thumbnail, in one go.
        $this->post('/frame/frames', [
            'category_id'    => $cat->id, 'status' => 1,
            'new_frames'     => [$this->webp('rose'), $this->webp('tulip')],
            'new_thumbnails' => [$this->webp('rose-t'), $this->webp('tulip-t')],
        ])->assertSessionDoesntHaveErrors();

        $frame = \App\Models\Frame::firstOrFail();
        $this->assertCount(2, $frame->images);
        $this->assertSame('rose.webp', $frame->images[0]['frame']);
        $this->assertSame('rose-t.webp', $frame->images[0]['thumbnail']);
        $this->assertFileExists(public_path('upload/frame/Pairs/frame/rose.webp'));
        $this->assertFileExists(public_path('upload/frame/Pairs/thumbnail/rose-t.webp'));

        // Mismatched counts (a frame without its thumbnail) is rejected.
        $this->post('/frame/frames/' . $frame->id, [
            '_method' => 'PUT', 'category_id' => $cat->id, 'status' => 1,
            'existing' => [0, 1], 'new_frames' => [$this->webp('x')], 'new_thumbnails' => [],
        ])->assertSessionHasErrors('new_frames');

        // API returns frame_url + thumbnail_url for every frame.
        $api = $this->postJson('/api/v1/frame/getFramesByCategory', ['package_name' => 'com.test.demo', 'category_id' => $cat->id], $this->bearer())->assertOk();
        $api->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['frame_url', 'thumbnail_url']]]);
        $this->assertStringContainsString('/frame/Pairs/frame/', $api->json('data.0.frame_url'));
        $this->assertStringContainsString('/frame/Pairs/thumbnail/', $api->json('data.0.thumbnail_url'));
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
