<?php

namespace Tests\Unit;

use App\Services\LocalAnalyzer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalAnalyzerTest extends TestCase
{
    #[DataProvider('samples')]
    public function test_categories(string $text, string $category): void
    {
        $this->assertSame($category, (new LocalAnalyzer)->analyze($text)['category']);
    }

    public static function samples(): array
    {
        return [
            'bangla theft' => ['রাতে এখানে মোবাইল ছিনতাই হয়েছে', 'theft'],
            'bangla dark' => ['রাত ১১টায় রাস্তায় আলো নেই, খুব অন্ধকার', 'dark_road'],
            'bangla harassment' => ['রিকশায় যাওয়ার সময় কয়েকজন উত্ত্যক্ত করেছে', 'harassment'],
            'banglish theft' => ['amar mobile chintai hoyeche raat 12 tar dike', 'theft'],
            'banglish dark' => ['ei rasta ta raat e khub andhar, light nai', 'dark_road'],
            'english violence' => ['A fight broke out and someone had a knife', 'violence'],
            'english overcharge' => ['CNG driver demanded extra taka late at night', 'overcharging'],
            'english positive' => ['Felt very safe here, well-lit and friendly staff', 'positive'],
            'negated safe' => ['This place is not safe at night at all', 'other'],
            'incident beats praise' => ['Nice place and safe inside but someone snatched my bag outside', 'theft'],
        ];
    }

    public function test_severity_and_language(): void
    {
        $a = new LocalAnalyzer;
        $this->assertSame(5, $a->analyze('A fight broke out and someone had a knife')['severity']);
        $this->assertSame('bn', $a->language('রাত ১১টায় রাস্তায় আলো নেই'));
        $this->assertSame('en', $a->language('The road was dark and empty'));
        $this->assertSame('mixed', $a->language('ei rasta ta khub andhar light nai'));
        $this->assertSame('mixed', $a->language('road ta খুব dark'));
    }

    public function test_spam_detection_and_hour_hint(): void
    {
        $a = new LocalAnalyzer;
        $this->assertGreaterThanOrEqual(0.6, $a->spam('Buy followers now!!! http://spam.example'));
        $this->assertLessThan(0.3, $a->spam('A group was following me near the footbridge'));
        $this->assertSame(23, $a->hourHint('happened at 11 pm near gate'));
        $this->assertSame(23, $a->hourHint('রাত ১১টায় ঘটনা'));
    }

    public function test_embedding_similarity(): void
    {
        $a = new LocalAnalyzer;
        $same = LocalAnalyzer::cosine($a->embed('phone snatched near the gate'), $a->embed('phone snatched near the gate'));
        $diff = LocalAnalyzer::cosine($a->embed('phone snatched near the gate'), $a->embed('great coffee and quiet music upstairs'));
        $this->assertEqualsWithDelta(1.0, $same, 0.0001);
        $this->assertLessThan(0.5, $diff);
    }
}
