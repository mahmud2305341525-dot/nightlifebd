<?php

namespace Database\Seeders;

use App\Models\DarkSpot;
use App\Models\Report;
use App\Models\User;
use App\Models\Venue;
use App\Services\LocalAnalyzer;
use App\Services\SafetyScoreService;
use Illuminate\Database\Seeder;

/**
 * Realistic-looking DEMO incidents (Bangla / English / Banglish) so scores, heatmap and safe routes visibly differ.
 * Everything here is fictional demo data.
 */
class DemoReportSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(404);
        $local = new LocalAnalyzer;
        $users = User::whereIn('email', array_map(fn ($n) => "{$n}@demo.test", ['rafi', 'nusrat', 'tanvir', 'mim', 'sakib', 'anika']))->orderBy('id')->get();
        $admin = User::where('email', 'safety@test.com')->first();

        // hotspots: [lat, lng, templates...]
        $hot = [
            'Farmgate' => [23.7561, 90.3872],
            'Karwan Bazar' => [23.7513, 90.3931],
            'Mohakhali' => [23.7780, 90.4040],
            'Dhanmondi 27' => [23.7480, 90.3690],
            'Uttara Sector 3' => [23.8680, 90.3900],
            'Mirpur 10' => [23.8070, 90.3690],
        ];
        $texts = [
            'dark_road' => ['রাত ১১টায় রাস্তায় একদম আলো নেই, খুব অন্ধকার ছিল', 'Street lights are broken here after 10 PM, very dark and empty road', 'ei rasta ta raat e khub andhar, light nai', 'The road is unlit and isolated at night, felt unsafe'],
            'theft' => ['রাতে এখানে মোবাইল ছিনতাই হয়েছে, দুজন মোটরসাইকেলে এসেছিল', 'Phone snatched by two guys on a bike around 11 pm', 'amar mobile chintai hoyeche raat 12 tar dike', 'Pickpocket in the crowd near the bus stop at night'],
            'harassment' => ['রিকশায় যাওয়ার সময় কয়েকজন উত্ত্যক্ত করেছে, পিছু নিয়েছিল', 'A group was catcalling and following me near the footbridge at night', 'kichu chele kharap kotha bolche, harass korse', 'Eve teasing near the bus stand after 9 pm'],
            'violence' => ['রাতে মারামারি হচ্ছিল, একজনের হাতে ছুরি ছিল', 'A fight broke out and someone had a knife, I ran away'],
            'overcharging' => ['সিএনজি চালক রাতে দ্বিগুণ ভাড়া চাইল', 'CNG driver demanded double fare late at night, overcharging', 'raat e vara beshi chaise, extra taka dite hoise'],
        ];
        $mix = ['dark_road' => 14, 'theft' => 12, 'harassment' => 12, 'violence' => 4, 'overcharging' => 6];

        $n = 0;
        foreach ($mix as $cat => $count) {
            for ($i = 0; $i < $count; $i++) {
                $spot = array_values($hot)[mt_rand(0, count($hot) - 1)];
                $lat = $spot[0] + mt_rand(-30, 30) / 10000;
                $lng = $spot[1] + mt_rand(-30, 30) / 10000;
                $body = $texts[$cat][mt_rand(0, count($texts[$cat]) - 1)];
                $hour = [21, 22, 23, 0, 1, 2, 23, 22][mt_rand(0, 7)];
                $time = now()->subDays(mt_rand(0, 27))->setTime($hour, mt_rand(0, 59));
                if ($time->isFuture()) {
                    $time = $time->subDay();
                }
                $this->make($users->random(), null, $lat, $lng, $body, $time, $cat, $local, $admin, 'verified');
                $n++;
            }
        }

        // reports tied to venues: some negative, some positive
        $venues = Venue::approved()->get();
        foreach ($venues->random(14) as $v) {
            $cat = ['harassment', 'theft', 'dark_road', 'overcharging'][mt_rand(0, 3)];
            $body = $texts[$cat][mt_rand(0, count($texts[$cat]) - 1)];
            $this->make($users->random(), $v->id, null, null, $body, now()->subDays(mt_rand(1, 20))->setTime(mt_rand(21, 23), 10), $cat, $local, $admin, 'verified');
        }
        $pos = ['খুব নিরাপদ জায়গা, গার্ড ছিল এবং আলো ভালো ছিল', 'Felt very safe here, well-lit and the guard was friendly', 'bhalo poribesh, nirapod laglo'];
        foreach ($venues->random(18) as $v) {
            $this->make($users->random(), $v->id, null, null, $pos[mt_rand(0, 2)], now()->subDays(mt_rand(1, 25))->setTime(mt_rand(19, 23), 0), 'positive', $local, $admin, 'verified');
        }

        // an obvious duplicate pair + a few pending reports for the moderation queue
        $v = $venues->first();
        $a = $this->make($users[0], $v->id, null, null, 'Someone tried to snatch my phone near the entrance at 11 pm', now()->subHours(5), 'theft', $local, $admin, 'pending');
        $b = $this->make($users[1], $v->id, null, null, 'Someone tried to snatch my phone near the entrance at 11 pm', now()->subHours(4), 'theft', $local, $admin, 'pending');
        $b->update(['duplicate_of' => $a->id]);
        $this->make($users[2], null, 23.7561, 90.3872, 'রাত ২টায় ফার্মগেটে রাস্তায় খুব অন্ধকার, ভয় লাগছিল', now()->subHours(8), 'dark_road', $local, $admin, 'pending');
        $this->make($users[3], null, 23.8070, 90.3690, 'Buy cheap followers now!!! http://spam.example', now()->subHours(3), 'other', $local, $admin, 'pending', 0.95);

        foreach ([[23.7558, 90.3880], [23.7517, 90.3935], [23.7785, 90.4048], [23.8676, 90.3895], [23.8066, 90.3694]] as [$la, $ln]) {
            DarkSpot::create(['user_id' => $users->random()->id, 'lat' => $la, 'lng' => $ln, 'note' => 'Unlit stretch (demo)']);
        }

        app(SafetyScoreService::class)->recalcAll();
        $this->command?->info("Seeded demo reports ({$n} free-standing) and recalculated safety scores.");
    }

    private function make(User $user, ?int $venueId, ?float $lat, ?float $lng, string $body, $time, string $cat, LocalAnalyzer $local, $admin, string $status, ?float $spam = null): Report
    {
        $a = $local->analyze($body);
        $category = $cat === 'other' ? $a['category'] : $cat;
        $sev = ['violence' => 5, 'theft' => 4, 'harassment' => 4, 'dark_road' => 2, 'overcharging' => 2, 'positive' => 1][$category] ?? 2;

        return Report::create([
            'user_id' => $user->id, 'venue_id' => $venueId, 'kind' => $category === 'positive' ? 'review' : 'incident',
            'body' => $body, 'lat' => $lat, 'lng' => $lng, 'incident_time' => $time, 'is_anonymous' => (bool) mt_rand(0, 1),
            'status' => $status, 'category' => $category, 'severity' => $sev, 'language' => $a['language'],
            'spam_probability' => $spam ?? $a['spam_probability'], 'summary_en' => ucfirst(strtolower(Report::categoryLabel($category))).' report (demo data).',
            'embedding' => $local->embed($body), 'embedding_source' => 'local', 'analysis_source' => 'seed',
            'needs_review' => ($spam ?? 0) >= 0.6,
            'verified_by' => $status === 'verified' ? $admin?->id : null, 'verified_at' => $status === 'verified' ? $time : null,
            'created_at' => $time, 'updated_at' => $time,
        ]);
    }
}
