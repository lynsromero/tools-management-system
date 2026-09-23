<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\Tool;
use App\Models\ToolFile;
use App\Models\User;
use App\Services\ExtensionService;
use App\Services\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function makeExtZipFile(Tool $tool): ToolFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ext');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('background.js', 'console.log("hi");');
        $zip->addFromString('popup.html', '<html></html>');
        $zip->close();

        Storage::disk('local')->putFileAs("tools/{$tool->id}", $tmp, 'bundle.zip');

        return ToolFile::query()->create([
            'tool_id' => $tool->id,
            'file_path' => "tools/{$tool->id}/bundle.zip",
            'file_type' => 'zip',
            'version' => '1.0.0',
            'changelog' => null,
        ]);
    }

    private function buy(Tool $tool, User $user): string
    {
        return (new TokenService)->generate(Purchase::factory()->create([
            'user_id' => $user->id,
            'tool_id' => $tool->id,
            'status' => Purchase::STATUS_ACTIVE,
        ]));
    }

    public function test_admin_can_store_extension_meta_when_creating_tool(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/tools', [
                'name' => 'SyncTube',
                'slug' => 'synctube',
                'description' => 'Syncs your tabs.',
                'type' => Tool::TYPE_EXTENSION,
                'pricing_model' => Tool::PRICING_ONE_TIME,
                'price' => 4.99,
                'device_limit' => 1,
                'extension_meta' => [
                    'browsers' => ['chrome', 'firefox', 'edge'],
                    'manifest_version' => 3,
                    'permissions' => ['storage', 'tabs'],
                ],
            ])->assertStatus(201)
            ->assertJsonPath('data.extension_meta.browsers.0', 'chrome')
            ->assertJsonPath('data.extension_meta.manifest_version', 3)
            ->assertJsonPath('data.extension_meta.permissions.0', 'storage');
    }

    public function test_unsupported_browser_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/tools', [
                'name' => 'SyncTube',
                'slug' => 'synctube',
                'description' => 'Syncs your tabs.',
                'type' => Tool::TYPE_EXTENSION,
                'pricing_model' => Tool::PRICING_ONE_TIME,
                'price' => 4.99,
                'device_limit' => 1,
                'extension_meta' => [
                    'browsers' => ['safari'],
                ],
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['extension_meta.browsers.0']);
    }

    public function test_chrome_manifest_is_version_three(): void
    {
        $tool = Tool::factory()->create([
            'name' => 'TimeSync',
            'slug' => 'timesync',
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => [
                'browsers' => ['chrome', 'firefox'],
                'manifest_version' => 3,
                'permissions' => ['storage'],
            ],
        ]);
        $this->makeExtZipFile($tool);

        $manifest = (new ExtensionService)->manifest($tool, 'chrome');

        $this->assertSame(3, $manifest['manifest_version']);
        $this->assertSame('TimeSync', $manifest['name']);
        $this->assertSame('1.0.0', $manifest['version']);
        $this->assertSame(['storage'], $manifest['permissions']);
        $this->assertSame('background.js', $manifest['background']['service_worker']);
        $this->assertArrayHasKey('action', $manifest);
        $this->assertArrayNotHasKey('browser_specific_settings', $manifest);
    }

    public function test_firefox_manifest_is_version_two_with_gecko_id(): void
    {
        $tool = Tool::factory()->create([
            'name' => 'TimeSync',
            'slug' => 'timesync',
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => [
                'browsers' => ['chrome', 'firefox'],
                'manifest_version' => 3,
                'permissions' => ['storage'],
            ],
        ]);
        $this->makeExtZipFile($tool);

        $manifest = (new ExtensionService)->manifest($tool, 'firefox');

        $this->assertSame(2, $manifest['manifest_version']);
        $this->assertSame('timesync@tools.example', $manifest['browser_specific_settings']['gecko']['id']);
        $this->assertSame(['background.js'], $manifest['background']['scripts']);
        $this->assertArrayHasKey('browser_action', $manifest);
    }

    public function test_download_injects_manifest_and_config_for_extension(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'name' => 'TimeSync',
            'slug' => 'timesync',
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => [
                'browsers' => ['chrome', 'firefox'],
                'manifest_version' => 3,
                'permissions' => ['storage'],
            ],
        ]);
        $this->makeExtZipFile($tool);
        $token = $this->buy($tool, $purchaser);

        $response = $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/download")
            ->assertOk()
            ->assertDownload('bundle.zip');

        $tmp = tempnam(sys_get_temp_dir(), 'dl');
        file_put_contents($tmp, $response->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);

        $config = json_decode($zip->getFromName('config.json'), true);
        $this->assertSame($token, $config['token']);
        $this->assertSame($tool->id, $config['tool_id']);

        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(3, $manifest['manifest_version']);

        $meta = json_decode($zip->getFromName('extension.json'), true);
        $this->assertSame(['chrome', 'firefox'], $meta['browsers']);
        $this->assertSame('console.log("hi");', $zip->getFromName('background.js'));

        $zip->close();
        @unlink($tmp);
    }

    public function test_download_respects_browser_param(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'name' => 'TimeSync',
            'slug' => 'timesync',
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => [
                'browsers' => ['chrome', 'firefox'],
                'manifest_version' => 3,
                'permissions' => [],
            ],
        ]);
        $this->makeExtZipFile($tool);
        $this->buy($tool, $purchaser);

        $response = $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/download?browser=firefox")
            ->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'dl');
        file_put_contents($tmp, $response->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);

        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(2, $manifest['manifest_version']);

        $zip->close();
        @unlink($tmp);
    }

    public function test_package_to_path_writes_zip_with_manifest(): void
    {
        $tool = Tool::factory()->create([
            'name' => 'TimeSync',
            'slug' => 'timesync',
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => [
                'browsers' => ['chrome'],
                'manifest_version' => 3,
                'permissions' => ['storage'],
            ],
        ]);
        $this->makeExtZipFile($tool);

        $path = (new ExtensionService)->packageToPath($tool, 'chrome');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)) === true);
        $this->assertSame(3, json_decode($zip->getFromName('manifest.json'), true)['manifest_version']);
        $this->assertFalse($zip->getFromName('config.json'));
        $zip->close();
    }

    public function test_purchaser_can_download_extension_for_supported_browser(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => [
                'browsers' => ['chrome', 'firefox'],
                'manifest_version' => 3,
                'permissions' => ['storage'],
            ],
        ]);
        $this->makeExtZipFile($tool);
        $token = $this->buy($tool, $purchaser);

        $response = $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/firefox")
            ->assertOk()
            ->assertDownload("{$tool->slug}-firefox.zip");

        $tmp = tempnam(sys_get_temp_dir(), 'extdl');
        file_put_contents($tmp, $response->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);

        $config = json_decode($zip->getFromName('config.json'), true);
        $this->assertSame($token, $config['token']);
        $this->assertSame($tool->id, $config['tool_id']);

        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(2, $manifest['manifest_version']);

        $zip->close();
        @unlink($tmp);
    }

    public function test_extension_download_requires_active_purchase(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        $this->makeExtZipFile($tool);

        $this->actingAs($user, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/chrome")
            ->assertStatus(403)
            ->assertJson(['message' => 'payment_required']);
    }

    public function test_extension_download_rejects_unlisted_browser(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        $this->makeExtZipFile($tool);
        $this->buy($tool, $purchaser);

        $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/firefox")
            ->assertStatus(422);
    }

    public function test_extension_download_returns_404_for_non_extension_tool(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_DESKTOP,
            'extension_meta' => null,
        ]);
        $this->makeExtZipFile($tool);
        $this->buy($tool, $purchaser);

        $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/chrome")
            ->assertNotFound();
    }

    public function test_extension_download_requires_authentication(): void
    {
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        $this->makeExtZipFile($tool);

        $this->getJson("/api/tools/{$tool->id}/extension/chrome")
            ->assertUnauthorized();
    }

    public function test_extension_download_is_rate_limited(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        $this->makeExtZipFile($tool);
        $this->buy($tool, $purchaser);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($purchaser, 'sanctum')
                ->get("/api/tools/{$tool->id}/extension/chrome")
                ->assertOk();
        }

        $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/chrome")
            ->assertStatus(429);
    }

    public function test_extension_download_returns_404_when_no_file(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        $this->buy($tool, $purchaser);

        $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/chrome")
            ->assertNotFound();
    }

    public function test_extension_download_returns_404_for_non_zip_file(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        ToolFile::query()->create([
            'tool_id' => $tool->id,
            'file_path' => "tools/{$tool->id}/setup.exe",
            'file_type' => 'exe',
            'version' => '1.0.0',
            'changelog' => null,
        ]);
        $this->buy($tool, $purchaser);

        $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/chrome")
            ->assertNotFound();
    }
}
