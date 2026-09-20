<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Кнопка «Скачать APK» в панели.
 *
 * Без неё приложение попадает на телефон кабелем или мессенджером — а телефон
 * и так открывает панель, чтобы отсканировать код привязки.
 */
class AppDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $apk;

    protected function setUp(): void
    {
        parent::setUp();

        // Настоящий APK весит 14 МБ и есть не на всякой машине: тесту важен
        // сам факт отдачи файла, а не его содержимое.
        $this->apk = storage_path('framework/testing/app-debug.apk');

        @mkdir(dirname($this->apk), 0777, true);
        file_put_contents($this->apk, 'not-a-real-apk');

        config(['gateway.app.apk_path' => $this->apk]);
    }

    protected function tearDown(): void
    {
        @unlink($this->apk);

        parent::tearDown();
    }

    public function test_an_authenticated_user_gets_the_file(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('app.download'));

        $response->assertOk()
            // Без этого типа Android не предложит установку, а браузер
            // попытается открыть файл как неизвестный.
            ->assertHeader('content-type', 'application/vnd.android.package-archive');

        $this->assertStringContainsString(
            'otp-gateway.apk',
            $response->headers->get('content-disposition'),
        );
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        // Приложение внутреннее: его адрес не должен гулять по интернету.
        $this->get(route('app.download'))->assertRedirect(route('login'));
    }

    public function test_a_missing_build_answers_honestly(): void
    {
        config(['gateway.app.apk_path' => 'android-client/app/build/outputs/apk/debug/nothing-here.apk']);

        $this->actingAs(User::factory()->create())
            ->get(route('app.download'))
            ->assertNotFound();
    }

    public function test_the_devices_page_offers_the_download_when_the_build_exists(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->get(route('projects.devices.index', $project))
            ->assertOk()
            ->assertSee('Скачать APK')
            ->assertSee(route('app.download'));
    }

    public function test_the_devices_page_explains_how_to_build_when_there_is_no_apk(): void
    {
        config(['gateway.app.apk_path' => 'nowhere/app-debug.apk']);

        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->get(route('projects.devices.index', $project))
            ->assertOk()
            ->assertDontSee('Скачать APK')
            ->assertSee('assembleDebug');
    }
}
