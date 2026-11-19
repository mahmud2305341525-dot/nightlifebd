<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportPhoto;
use App\Services\IncidentPhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportPhotoSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(IncidentPhotoService::DISK);
    }

    /**
     * Create a real valid 200x200 JPEG image for testing.
     */
    protected function createValidImage(string $name = 'test.jpg', int $width = 200, int $height = 200): UploadedFile
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The GD PHP extension is required to generate test images and process thumbnails.');
        }

        return UploadedFile::fake()->image($name, $width, $height);
    }

    public function test_unauthenticated_visitor_cannot_access_pending_photo(): void
    {
        $author = $this->userWithRole('user');
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $author->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Testing pending incident report',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $disk = Storage::disk(IncidentPhotoService::DISK);
        $disk->put('photos/sample_pending.jpg', 'fake-image-bytes');
        $disk->put('thumbs/sample_pending_thumb.jpg', 'fake-thumb-bytes');

        $photo = ReportPhoto::create([
            'report_id' => $report->id,
            'path' => 'photos/sample_pending.jpg',
            'thumbnail' => 'thumbs/sample_pending_thumb.jpg',
            'size_bytes' => 100,
        ]);

        // Unauthenticated guest requests thumbnail
        $this->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'thumb']))
            ->assertStatus(403);

        // Unauthenticated guest requests full image
        $this->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'full']))
            ->assertStatus(403);
    }

    public function test_unauthenticated_visitor_cannot_access_rejected_photo(): void
    {
        $author = $this->userWithRole('user');
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $author->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Spam or rejected report',
            'incident_time' => now(),
            'status' => 'rejected',
        ]);

        $disk = Storage::disk(IncidentPhotoService::DISK);
        $disk->put('photos/sample_rejected.jpg', 'fake-image-bytes');

        $photo = ReportPhoto::create([
            'report_id' => $report->id,
            'path' => 'photos/sample_rejected.jpg',
            'thumbnail' => 'photos/sample_rejected.jpg',
            'size_bytes' => 100,
        ]);

        $this->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'full']))
            ->assertStatus(403);
    }

    public function test_ordinary_user_cannot_access_another_users_pending_photo(): void
    {
        $author = $this->userWithRole('user');
        $stranger = $this->userWithRole('user');
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $author->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Private pending report',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $disk = Storage::disk(IncidentPhotoService::DISK);
        $disk->put('photos/private_photo.jpg', 'fake-image-bytes');

        $photo = ReportPhoto::create([
            'report_id' => $report->id,
            'path' => 'photos/private_photo.jpg',
            'thumbnail' => 'photos/private_photo.jpg',
            'size_bytes' => 100,
        ]);

        $this->actingAs($stranger)
            ->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'full']))
            ->assertStatus(403);
    }

    public function test_report_author_can_access_their_own_pending_or_rejected_photo(): void
    {
        $author = $this->userWithRole('user');
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $author->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'My own pending report',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $disk = Storage::disk(IncidentPhotoService::DISK);
        $disk->put('photos/my_photo.jpg', 'image-content-bytes');
        $disk->put('thumbs/my_thumb.jpg', 'thumb-content-bytes');

        $photo = ReportPhoto::create([
            'report_id' => $report->id,
            'path' => 'photos/my_photo.jpg',
            'thumbnail' => 'thumbs/my_thumb.jpg',
            'size_bytes' => 100,
        ]);

        $res = $this->actingAs($author)
            ->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'thumb']))
            ->assertStatus(200)
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('private', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));

        $this->actingAs($author)
            ->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'full']))
            ->assertStatus(200);
    }

    public function test_moderators_and_admins_can_access_pending_and_rejected_photos(): void
    {
        $author = $this->userWithRole('user');
        $safetyAdmin = $this->userWithRole('safety_admin');
        $superAdmin = $this->userWithRole('super_admin');
        $otherUser = $this->userWithRole('user');
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $author->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Needs review',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $disk = Storage::disk(IncidentPhotoService::DISK);
        $disk->put('photos/evidence.jpg', 'evidence-content');

        $photo = ReportPhoto::create([
            'report_id' => $report->id,
            'path' => 'photos/evidence.jpg',
            'thumbnail' => 'photos/evidence.jpg',
            'size_bytes' => 100,
        ]);

        // Authorized safety admin can access
        $this->actingAs($safetyAdmin)
            ->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'full']))
            ->assertStatus(200);

        // Authorized super admin can access
        $this->actingAs($superAdmin)
            ->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'full']))
            ->assertStatus(200);

        // Ordinary citizen who is not the author is denied
        $this->actingAs($otherUser)
            ->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'full']))
            ->assertStatus(403);
    }

    public function test_verified_report_photos_are_accessible_to_public(): void
    {
        $author = $this->userWithRole('user');
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $author->id,
            'venue_id' => $venue->id,
            'kind' => 'review',
            'body' => 'Verified great place and lighting',
            'incident_time' => now(),
            'status' => 'verified',
        ]);

        $disk = Storage::disk(IncidentPhotoService::DISK);
        $disk->put('photos/verified_photo.jpg', 'verified-content');

        $photo = ReportPhoto::create([
            'report_id' => $report->id,
            'path' => 'photos/verified_photo.jpg',
            'thumbnail' => 'photos/verified_photo.jpg',
            'size_bytes' => 100,
        ]);

        // Unauthenticated guest can view verified photo
        $res = $this->get(route('reports.photos.show', ['photo' => $photo, 'variant' => 'full']))
            ->assertStatus(200)
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('public', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400', $res->headers->get('Cache-Control'));
    }

    public function test_invalid_file_content_and_oversized_uploads_are_rejected(): void
    {
        $user = $this->userWithRole('user');
        $venue = $this->makeVenue();

        // 1. Fake text file masquerading as .jpg
        $fakeFile = UploadedFile::fake()->create('malicious.jpg', 50, 'text/plain');

        $this->actingAs($user)->post('/reports', [
            'kind' => 'incident',
            'body' => 'Suspicious activity observed near venue',
            'venue_id' => $venue->id,
            'photos' => [$fakeFile],
        ])->assertSessionHasErrors(['photos.0']);

        // 2. Oversized image > 5 MB
        $hugeFile = UploadedFile::fake()->create('huge.jpg', 5200, 'image/jpeg');

        $this->actingAs($user)->post('/reports', [
            'kind' => 'incident',
            'body' => 'Another incident report with huge file',
            'venue_id' => $venue->id,
            'photos' => [$hugeFile],
        ])->assertSessionHasErrors(['photos.0']);
    }

    public function test_valid_image_is_stored_on_private_disk_with_opaque_identifier(): void
    {
        $user = $this->userWithRole('user');
        $venue = $this->makeVenue();
        $validImage = $this->createValidImage('evidence.jpg', 600, 400);

        $res = $this->actingAs($user)->post('/reports', [
            'kind' => 'incident',
            'body' => 'Valid incident report with real photo evidence',
            'venue_id' => $venue->id,
            'photos' => [$validImage],
        ]);

        $res->assertRedirect('/my-reports');

        $report = Report::latest()->first();
        $this->assertNotNull($report);
        $photo = $report->photos()->first();
        $this->assertNotNull($photo);

        // Path must be opaque and on private disk, never revealing user filename
        $this->assertStringNotContainsString('evidence.jpg', $photo->path);
        $this->assertStringStartsWith('photos/', $photo->path);
        $this->assertStringStartsWith('thumbs/', $photo->thumbnail);

        $disk = Storage::disk(IncidentPhotoService::DISK);
        $this->assertTrue($disk->exists($photo->path));
        $this->assertTrue($disk->exists($photo->thumbnail));

        // Public disk must NOT contain the file
        $this->assertFalse(Storage::disk('public')->exists($photo->path));
    }

    public function test_deleting_photo_removes_physical_files_from_private_storage(): void
    {
        $author = $this->userWithRole('user');
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $author->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Report with photo to be deleted',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $disk = Storage::disk(IncidentPhotoService::DISK);
        $path = 'photos/to_delete.jpg';
        $thumb = 'thumbs/to_delete_thumb.jpg';
        $disk->put($path, 'bytes');
        $disk->put($thumb, 'bytes');

        $photo = ReportPhoto::create([
            'report_id' => $report->id,
            'path' => $path,
            'thumbnail' => $thumb,
            'size_bytes' => 10,
        ]);

        $this->assertTrue($disk->exists($path));
        $this->assertTrue($disk->exists($thumb));

        // Delete photo model
        $photo->delete();

        // Disk files must be purged
        $this->assertFalse($disk->exists($path));
        $this->assertFalse($disk->exists($thumb));
    }

    public function test_deleting_report_cascades_and_removes_stored_files(): void
    {
        $author = $this->userWithRole('user');
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $author->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Report with photo cascade deletion',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $disk = Storage::disk(IncidentPhotoService::DISK);
        $path = 'photos/cascade.jpg';
        $thumb = 'thumbs/cascade_thumb.jpg';
        $disk->put($path, 'cascade-bytes');
        $disk->put($thumb, 'cascade-thumb');

        $photo = ReportPhoto::create([
            'report_id' => $report->id,
            'path' => $path,
            'thumbnail' => $thumb,
            'size_bytes' => 20,
        ]);

        $this->assertTrue($disk->exists($path));

        // Delete the parent report
        $report->delete();

        // Database records and disk files must be cleanly deleted / soft-deleted
        $this->assertSoftDeleted('reports', ['id' => $report->id]);
        $this->assertDatabaseMissing('report_photos', ['id' => $photo->id]);
        $this->assertFalse($disk->exists($path));
        $this->assertFalse($disk->exists($thumb));
    }

    public function test_database_failure_does_not_leave_orphaned_files(): void
    {
        $user = $this->userWithRole('user');
        $venue = $this->makeVenue();
        $validImage = $this->createValidImage('rollback.jpg', 300, 300);

        // Intentionally simulate a database exception during ReportPhoto::create
        $called = false;
        DB::listen(function ($query) use (&$called) {
            if (str_contains($query->sql, 'report_photos') && ! $called) {
                $called = true;
                throw new \RuntimeException('Simulated database write error');
            }
        });

        try {
            $this->actingAs($user)->post('/reports', [
                'kind' => 'incident',
                'body' => 'Report that will fail database write',
                'venue_id' => $venue->id,
                'photos' => [$validImage],
            ]);
        } catch (\RuntimeException $e) {
            // Expected simulated exception
        }

        // Verify that no files were orphaned in private storage
        $disk = Storage::disk(IncidentPhotoService::DISK);
        $files = $disk->allFiles();
        $this->assertEmpty($files, 'No orphaned files should remain in storage after a database failure.');
    }
}
