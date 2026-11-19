<?php

namespace App\Services;

use App\Models\Report;
use App\Support\Geo;

/**
 * Offline fallback for the AI report analyzer: a Bangla / English / Banglish keyword lexicon
 * plus a hashed character n-gram "embedding". Used when no Gemini key is set or the free quota is hit.
 */
class LocalAnalyzer
{
    public const VERSION = '2.1.0-lexicon';

    /** category => keyword fragments (matched case-insensitively as substrings) */
    private const LEXICON = [
        'harassment' => ['হয়রানি', 'উত্ত্যক্ত', 'ইভটিজিং', 'ইভ টিজিং', 'কটূক্তি', 'অশালীন', 'পিছু নি', 'পিছু ধরে', 'harass', 'eve teas', 'eveteas', 'eve-teasing', 'catcall', 'stalk', 'followed me', 'following me', 'inappropriate', 'molest', 'hoyrani', 'utyokto', 'kharap kotha', 'baje kotha', 'pichu', 'groped', 'touched me'],
        'theft' => ['ছিনতাই', 'চুরি', 'পকেটমার', 'মোবাইল নিয়ে', 'ব্যাগ নিয়ে', 'snatch', 'snatched', 'theft', 'stole', 'stolen', 'pickpocket', 'robbed', 'robbery', 'chintai', 'chintay', 'churi', 'mobile niye', 'bag niye', 'chintaikaari', 'mugger', 'mugging'],
        'violence' => ['মারামারি', 'ছুরি', 'হামলা', 'মারধর', 'অস্ত্র', 'assault', 'attack', 'knife', 'fight', 'beaten', 'beat up', 'gunda', 'maramari', 'hamla', 'weapon', 'gang', 'ganjam', 'churikaaghat', 'churi niye', 'churi diye', 'koptise', 'stabbed', 'physical assault'],
        'dark_road' => ['অন্ধকার', 'আলো নেই', 'আলো কম', 'লাইট নেই', 'বাতি নেই', 'নির্জন', 'dark', 'andhar', 'andhokar', 'streetlight', 'street light', 'no light', 'light nai', 'bati nai', 'batti nai', 'alo nai', 'poor lighting', 'unlit', 'sunsan', 'empty road', 'isolated', 'deserted', 'pitch dark'],
        'overcharging' => ['ভাড়া বেশি', 'বেশি ভাড়া', 'বেশি টাকা', 'অতিরিক্ত টাকা', 'অতিরিক্ত', 'মিটার ছাড়া', 'ঠকাল', 'ঠকিয়ে', 'overcharg', 'extra taka', 'extra charge', 'too expensive', 'vara beshi', 'bhara beshi', 'ভাড়া চাইল', 'বেশি চাইল', 'scam', 'cheated', 'rip off', 'ripped off', 'refused the meter'],
        'positive' => ['নিরাপদ', 'ভালো ছিল', 'ভাল ছিল', 'সুন্দর পরিবেশ', 'গার্ড ছিল', 'নিরাপত্তা কর্মী', 'safe', 'secure', 'well lit', 'well-lit', 'guard was', 'friendly staff', 'comfortable', 'nirapod', 'peaceful', 'felt good', 'great place', 'completely secure'],
    ];

    private const ADVERSARIAL_PATTERNS = [
        '/system override/i',
        '/ignore (all )?(previous )?(instructions|rules|directives)/i',
        '/disregard (all )?(previous )?(instructions|rules|directives)/i',
        '/return category/i',
        '/output category/i',
        '/```(json)?\s*\{.*category/i',
    ];

    private const NEGATED_POSITIVE = ['নিরাপদ নয়', 'নিরাপদ না', 'অনিরাপদ', 'not safe', 'unsafe', 'isn\'t safe', 'wasn\'t safe', 'nirapod noy', 'nirapod na', 'anirapod', 'not secure', 'felt unsafe', 'insecure', 'uncomfortable', 'unfriendly', 'not comfortable'];

    private const SEVERE = ['ছুরি', 'অস্ত্র', 'knife', 'weapon', 'gun', 'rape', 'ধর্ষণ', 'attack', 'হামলা', 'bleeding', 'রক্ত', 'hospital', 'হাসপাতাল'];

    private const BASE_SEVERITY = ['violence' => 5, 'theft' => 4, 'harassment' => 4, 'dark_road' => 2, 'overcharging' => 2, 'positive' => 1, 'other' => 2];

    private const BANGLISH_MARKERS = [' nai', ' ache', ' kora', ' hoyni', ' jabe', ' korlo', ' kore', ' ekta', ' amar', ' rasta', ' raat', ' khub', ' beshi', ' ekdom', 'niye', 'hoyeche', ' tai ', ' kintu'];

    /** @return array{category:string,severity:int,language:string,spam_probability:float,summary_en:string,hour_hint:?int} */
    public function analyze(string $text): array
    {
        $lower = mb_strtolower($text);
        $scores = [];
        foreach (self::LEXICON as $cat => $words) {
            foreach ($words as $w) {
                if (mb_strpos($lower, mb_strtolower($w)) !== false) {
                    $scores[$cat] = ($scores[$cat] ?? 0) + 1;
                }
            }
        }

        $negated = false;
        foreach (self::NEGATED_POSITIVE as $n) {
            if (mb_strpos($lower, $n) !== false) {
                $negated = true;
                break;
            }
        }
        if ($negated) {
            unset($scores['positive']);
            $scores['other'] = ($scores['other'] ?? 0) + 1;
        }

        // a positive word next to an incident word ("safe but someone snatched my phone") -> trust the incident
        $incidentScores = array_diff_key($scores, ['positive' => 1]);
        if ($incidentScores) {
            unset($scores['positive']);
        }

        if (! $scores) {
            $category = 'other';
        } else {
            arsort($scores);
            $category = (string) array_key_first($scores);
        }

        $severity = self::BASE_SEVERITY[$category];
        if ($category !== 'positive') {
            foreach (self::SEVERE as $w) {
                if (mb_strpos($lower, $w) !== false) {
                    $severity = min(5, $severity + 1);
                    break;
                }
            }
        }

        $spam = $this->spam($text);
        if ($spam >= 0.80) {
            $category = 'other';
            $severity = 2;
        }

        $label = strtolower(Report::categoryLabel($category));

        return [
            'category' => $category,
            'severity' => $severity,
            'language' => $this->language($text),
            'spam_probability' => $spam,
            'summary_en' => ucfirst($label)." report, severity {$severity}/5 (keyword analysis).",
            'hour_hint' => $this->hourHint($text),
        ];
    }

    public function language(string $text): string
    {
        $letters = preg_match_all('/\p{L}/u', $text);
        if (! $letters) {
            return 'en';
        }
        $bn = preg_match_all('/[\x{0980}-\x{09FF}]/u', $text);
        $ratio = $bn / $letters;
        if ($ratio > 0.6) {
            return 'bn';
        }
        if ($ratio > 0.05) {
            return 'mixed';
        }
        $padded = ' '.mb_strtolower($text).' ';
        foreach (self::BANGLISH_MARKERS as $m) {
            if (mb_strpos($padded, $m) !== false) {
                return 'mixed';
            }
        }

        return 'en';
    }

    public function spam(string $text): float
    {
        $p = 0.05;
        $len = mb_strlen(trim($text));
        if ($len < 12) {
            $p += 0.5;
        }
        if (preg_match('~https?://|www\.|t\.me/|bit\.ly~i', $text)) {
            $p += 0.6;
        }
        if (preg_match('/(.)\1{6,}/u', $text)) {
            $p += 0.4;
        }
        if (preg_match('/^[\d\W]+$/u', trim($text))) {
            $p += 0.4;
        }

        foreach (self::ADVERSARIAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                $p += 0.85;
                break;
            }
        }

        return round(min(0.99, $p), 2);
    }

    /** "11 pm", "রাত ১১টা", "at 2am" -> hour 0-23 */
    public function hourHint(string $text): ?int
    {
        $t = Geo::asciiDigits(mb_strtolower($text));
        if (preg_match('/\b(\d{1,2})(?::\d{2})?\s*(am|pm)\b/', $t, $m)) {
            $h = (int) $m[1] % 12;

            return $m[2] === 'pm' ? $h + 12 : $h;
        }
        if (preg_match('/রাত\s*(\d{1,2})/u', $t, $m)) {
            $h = (int) $m[1] % 12;

            return $h < 6 ? $h : $h + 12;
        }

        return null;
    }

    /** Hashed word + character-trigram vector (256 dims, L2 normalised). Works for Bangla too. */
    public function embed(string $text, int $dims = 256): array
    {
        $vec = array_fill(0, $dims, 0.0);
        $words = preg_split('/[^\p{L}\p{M}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $w) {
            $vec[crc32('w:'.$w) % $dims] += 2.0;
            $padded = '_'.$w.'_';
            $n = mb_strlen($padded);
            for ($i = 0; $i + 3 <= $n; $i++) {
                $vec[crc32('g:'.mb_substr($padded, $i, 3)) % $dims] += 1.0;
            }
        }
        $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vec)));
        if ($norm > 0) {
            $vec = array_map(fn ($v) => $v / $norm, $vec);
        }

        return $vec;
    }

    public static function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        $dot = $na = $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }

        return ($na > 0 && $nb > 0) ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
    }
}
