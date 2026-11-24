<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmergencyContact;
use App\Models\Report;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\ReportAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Stage1CriticalFixesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------
    // BUG-01: Anonymized Report Moderation Crash Tests
    // -------------------------------------------------------------

    public function test_moderating_anonymized_report_with_null_user_succeeds_without_crash(): void
    {
        $admin = $this->userWithRole('safety_admin');
        $venue = $this->makeVenue();

        // Anonymized report where user deleted their account (user_id is null)
        $report = Report::create([
            'user_id' => null,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'category' => 'dark_road',
            'severity' => 2,
            'body' => 'Pitch dark alley near venue with no streetlights',
            'incident_time' => now()->subHours(2),
            'status' => 'pending',
            'is_anonymous' => true,
        ]);

        $this->assertNull($report->user);

        // Verify report as safety admin
        $response = $this->actingAs($admin)->post(route('admin.reports.verify', $report->id), [
            'category' => 'dark_road',
            'severity' => 3,
            'moderator_notes' => 'Confirmed dark spot near alley',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $report->refresh();
        $this->assertSame('verified', $report->status);
        $this->assertSame(3, $report->severity);
        $this->assertSame($admin->id, $report->verified_by);
        $this->assertFalse($report->needs_review);

        // Verify AuditLog recorded the action
        $log = AuditLog::where('action', 'report.verified')->where('user_id', $admin->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($report->id, $log->meta['report_id']);
    }

    public function test_rejecting_anonymized_report_with_null_user_succeeds(): void
    {
        $admin = $this->userWithRole('safety_admin');
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => null,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'category' => 'other',
            'severity' => 1,
            'body' => 'Vague unverified note from deleted user',
            'incident_time' => now()->subDay(),
            'status' => 'pending',
            'is_anonymous' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.reports.reject', $report->id), [
            'moderator_notes' => 'Insufficient details',
        ]);

        $response->assertRedirect();
        $report->refresh();
        $this->assertSame('rejected', $report->status);
        $this->assertSame($admin->id, $report->verified_by);
    }

    // -------------------------------------------------------------
    // BUG-02: Data Privacy Export Schema Tests
    // -------------------------------------------------------------

    public function test_data_privacy_export_returns_valid_json_with_correct_schema(): void
    {
        $user = User::factory()->create([
            'name' => 'Data Subject',
            'email' => 'subject@nightlifebd.com',
            'phone' => '01712345678',
        ]);

        EmergencyContact::create([
            'user_id' => $user->id,
            'name' => 'Primary Contact',
            'phone' => '01812345678',
            'relation' => 'sister',
            'email' => 'sister@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('profile.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $content = $response->getContent();
        $this->assertIsString($content);

        // Validate JSON integrity
        $data = json_decode($content, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Exported data must be strictly valid JSON.');

        // Validate account metadata
        $this->assertSame('NightLifeBD', $data['export_metadata']['application']);
        $this->assertSame($user->id, $data['account']['id']);

        // Validate emergency_contacts schema
        $this->assertCount(1, $data['emergency_contacts']);
        $contact = $data['emergency_contacts'][0];

        $this->assertSame('Primary Contact', $contact['name']);
        $this->assertSame('01812345678', $contact['phone']);
        $this->assertSame('sister', $contact['relation']);
        $this->assertArrayHasKey('created_at', $contact);

        // Confirm non-existent columns are NOT present
        $this->assertArrayNotHasKey('relationship', $contact);
        $this->assertArrayNotHasKey('is_priority', $contact);
    }

    // -------------------------------------------------------------
    // SEC-01: Telegram Webhook Authentication Tests
    // -------------------------------------------------------------

    public function test_telegram_webhook_fails_securely_when_unconfigured(): void
    {
        config(['services.telegram.webhook_secret' => null]);

        $response = $this->postJson(route('webhooks.telegram'), [
            'message' => [
                'chat' => ['id' => 987654321],
                'text' => '/start token123',
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_telegram_webhook_rejects_missing_secret_token_header(): void
    {
        config(['services.telegram.webhook_secret' => 'super-secret-telegram-token-48chars']);

        $response = $this->postJson(route('webhooks.telegram'), [
            'message' => [
                'chat' => ['id' => 987654321],
                'text' => '/start token123',
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_telegram_webhook_rejects_incorrect_secret_token(): void
    {
        config(['services.telegram.webhook_secret' => 'super-secret-telegram-token-48chars']);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret-token',
        ])->postJson(route('webhooks.telegram'), [
            'message' => [
                'chat' => ['id' => 987654321],
                'text' => '/start token123',
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_telegram_webhook_succeeds_with_valid_secret_token(): void
    {
        $secret = 'super-secret-telegram-token-48chars';
        config(['services.telegram.webhook_secret' => $secret]);

        $user = User::factory()->create();
        $token = Str::random(48);
        $contact = EmergencyContact::create([
            'user_id' => $user->id,
            'name' => 'Telegram Contact',
            'phone' => '01912345678',
            'relation' => 'friend',
            'telegram_verification_token' => $token,
            'verification_status' => 'unverified',
        ]);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => $secret,
        ])->postJson(route('webhooks.telegram'), [
            'message' => [
                'chat' => ['id' => 123456789],
                'text' => '/start '.$token,
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $contact->refresh();
        $this->assertSame('123456789', $contact->telegram_chat_id);
        $this->assertSame('verified', $contact->verification_status);
        $this->assertNotNull($contact->verified_at);
        $this->assertNull($contact->telegram_verification_token);
    }

    // -------------------------------------------------------------
    // CONF-01: Gemini Model Configuration & Fallback Integrity Tests
    // -------------------------------------------------------------

    public function test_gemini_model_configuration_defaults(): void
    {
        $this->assertSame('gemini-3.8-flash', config('services.gemini.model'));
        $this->assertSame('gemini-3.5-flash-lite', config('services.gemini.fallback_model'));
        $this->assertSame('gemini-embedding-001', config('services.gemini.embed_model'));
    }

    public function test_gemini_model_configuration_preserves_overrides(): void
    {
        config([
            'services.gemini.model' => 'custom-gemini-primary',
            'services.gemini.fallback_model' => 'custom-gemini-fallback',
            'services.gemini.embed_model' => 'custom-gemini-embed',
        ]);
        $this->assertSame('custom-gemini-primary', config('services.gemini.model'));
        $this->assertSame('custom-gemini-fallback', config('services.gemini.fallback_model'));
        $this->assertSame('custom-gemini-embed', config('services.gemini.embed_model'));
    }

    public function test_gemini_fallback_model_invoked_when_primary_fails(): void
    {
        config([
            'services.gemini.key' => 'test-key-stage1',
            'services.gemini.model' => 'gemini-3.8-flash',
            'services.gemini.fallback_model' => 'gemini-3.5-flash-lite',
        ]);

        $attemptedModels = [];

        Http::fake(function ($request) use (&$attemptedModels) {
            $url = $request->url();
            if (str_contains($url, 'gemini-3.8-flash')) {
                $attemptedModels[] = 'gemini-3.8-flash';

                return Http::response(['error' => ['message' => 'Primary overloaded']], 503);
            }
            if (str_contains($url, 'gemini-3.5-flash-lite')) {
                $attemptedModels[] = 'gemini-3.5-flash-lite';

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => '{"category":"theft","severity":4,"language":"en","spam_probability":0.05,"summary_en":"Theft incident"}'],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response('Not Found', 404);
        });

        $gemini = app(GeminiService::class);
        $result = $gemini->json('User report text');

        $this->assertNotNull($result);
        $this->assertSame('theft', $result['category']);
        $this->assertSame(['gemini-3.8-flash', 'gemini-3.5-flash-lite'], $attemptedModels);
    }

    public function test_gemini_embedding_endpoint_and_configuration(): void
    {
        config([
            'services.gemini.key' => 'test-key-stage1',
            'services.gemini.embed_model' => 'gemini-embedding-001',
        ]);

        $endpointHit = false;

        Http::fake(function ($request) use (&$endpointHit) {
            if (str_contains($request->url(), 'models/gemini-embedding-001:embedContent')) {
                $endpointHit = true;
                $this->assertSame(256, $request['outputDimensionality']);

                return Http::response([
                    'embedding' => [
                        'values' => array_fill(0, 256, 0.123),
                    ],
                ], 200);
            }

            return Http::response('Not Found', 404);
        });

        $gemini = app(GeminiService::class);
        $vector = $gemini->embed('Sample incident text');

        $this->assertTrue($endpointHit);
        $this->assertIsArray($vector);
        $this->assertCount(256, $vector);
    }

    public function test_gemini_unavailable_model_falls_back_without_falsely_claiming_gemini(): void
    {
        config([
            'services.gemini.key' => 'test-key-stage1',
            'services.gemini.model' => 'gemini-nonexistent-model-404',
            'services.gemini.fallback_model' => 'gemini-nonexistent-fallback-404',
        ]);

        // Fake Google API returning 404 Model Not Found
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'models/gemini-nonexistent-model-404 is not found for API version v1beta',
                    'status' => 'NOT_FOUND',
                ],
            ], 404),
        ]);

        $user = $this->userWithRole('user');
        $report = Report::create([
            'user_id' => $user->id,
            'kind' => 'incident',
            'body' => 'Rastay light nai, andhar goli',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $analyzer = app(ReportAnalyzer::class);
        $analyzer->analyze($report);

        $report->refresh();

        // Must classify via local analyzer fallback
        $this->assertSame('dark_road', $report->category);
        // CRITICAL: Must NEVER falsely claim 'gemini' as the analysis source!
        $this->assertSame('keyword', $report->analysis_source);
        $this->assertNotSame('gemini', $report->analysis_source);
        $this->assertTrue($report->needs_review);
    }
}
