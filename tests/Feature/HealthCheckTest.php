<?php

namespace Tests\Feature;

use App\Services\IncidentPhotoService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_ok_when_system_is_healthy(): void
    {
        $res = $this->getJson('/health');

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'database' => 'ok',
                    'cache' => 'ok',
                    'storage' => 'ok',
                ],
            ]);

        // Verify no sensitive host/password data is exposed
        $content = $res->getContent();
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('root', $content);
        $this->assertStringNotContainsString('3306', $content);
    }

    public function test_health_check_reports_degraded_when_private_storage_fails(): void
    {
        $mockDisk = Mockery::mock(Filesystem::class);
        $mockDisk->shouldReceive('put')->andReturn(false);

        Storage::shouldReceive('disk')
            ->with(IncidentPhotoService::DISK)
            ->andReturn($mockDisk);

        $res = $this->getJson('/health');

        $res->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => [
                    'storage' => 'unhealthy',
                ],
            ]);
    }
}
