# 🌙 NightLifeBD — Bangla-first AI Safety Intelligence for Bangladesh's Nights

Laravel 12 web app (installable PWA) that turns what people experience at night — written in **Bangla, English or Banglish** — into usable safety information: a **time-aware, explainable safety score** for every venue, a **safest-route finder**, a **heatmap**, an **AI night planner**, and a free **emergency toolkit** (SOS, live trip link, safety timer, fake call).

**Team Error 404** — Daffodil International University, SE331 Software Engineering Design Capstone (Fall 2026)
Ridwan Mahmud (232-35-525) · Shahed Ahmed (232-35-033) · Maruf Ahmed (232-35-447)

> Safety information is community-sourced and AI-assisted. It is advisory only and never replaces **999**.
> All demo venues, reports and users are **fictional**.

---

## 1. Features (mapped to the proposal)

| Proposal | Feature | Where |
|---|---|---|
| FR-01 | Register / login / profile, roles (user, venue_partner, safety_admin, super_admin) | Breeze + Spatie Permission |
| FR-02 | Incident reports & venue reviews in Bangla / English / Banglish (≤500 chars, anonymous option, map pin) | `/reports/create` |
| FR-03 | **AI Report Analyzer**: category, severity 1-5, language, spam probability, English summary | `ReportAnalyzer`, `GeminiService`, `LocalAnalyzer` |
| FR-04 | **Duplicate detection** with embeddings (cosine similarity; duplicates = corroboration, not spam) | `ReportAnalyzer::markDuplicate` |
| FR-05 | **Explainable time-aware safety score** per venue × 4 time buckets (day / evening / night / late) | `SafetyScoreService`, venue page |
| FR-06 | **Safer route**: fastest vs safest, scored on verified incidents, dark spots and nearby police/hospitals; never >30% longer | `/route`, `RouteService` |
| FR-07 | **AI Night Planner** (RAG): "৩ জন, ৩০০০ টাকা, শান্ত জায়গা, ধানমন্ডি" → 2-3 stop plan from our own DB, budget-validated | `/planner`, `NightPlannerService` |
| FR-08 | **Emergency toolkit**: long-press/double-tap SOS (pre-filled SMS + email/Telegram + 999), near-me help points, live trip link, ride check-in, safety timer, fake call | `/sos`, `/near-me`, `/trips`, `/timer`, `/fake-call` |
| FR-09 | Venue search & filters (category, area, price, safety badge, time of day) + map | `/venues` |
| FR-10 | Moderation: AI-pre-triaged report queue, venue approval, user roles / ban, audit log | `/admin/*` |
| FR-11 | Bilingual UI (English ⇄ বাংলা toggle) | `lang/bn.json` |
| FR-12 | PWA: manifest + service worker, offline SOS page with cached contacts and help points | `public/sw.js` |
| extra | Safety **heatmap** with hour slider · Venue **Safety Coach** for partners · reporter **trust score** · community **dark-spot** pins · age gate for restricted venues | |

### How the AI pipeline works
```
User report → AnalyzeReportJob → Gemini (JSON) ─┐ if no key / quota hit
                                                └→ offline Bangla/Banglish keyword analyzer (flagged for review)
            → embedding (Gemini, or local hashed n-grams) → duplicate check (same venue or ≤200 m, last 48 h)
            → moderation queue sorted by AI severity → moderator verifies/rejects → reporter trust updated
            → safety score recalculated → badges · heatmap · safe route · night planner
```

### Safety score formula — visual breakdown
```
 Venue checklist
 ┌─────────────────────────────────────────────────────────────┐
 │  base = 40 + 20·CCTV + 20·guard + 4·lighting  (max = 100) │
 └─────────────────────────────────────────────────────────────┘
          +  bucket_adjust  →  day +10 / evening 0 / night −5 / late −10

 Community reports (verified, per time bucket)
 ┌────────────────────────────────────────────────────────────────────────────────────┐
 │  risk = Σ [ severity × reporter_trust × e^(−age/30d) × time_weight × dupe_boost ] │
 │  where: time_weight = 1.0 (same bucket) or 0.3 (different bucket)                  │
 │         dupe_boost  = 1 + 0.25 × min(3, corroborating_reports)                     │
 │         reporter_trust = (verified+1) / (verified+rejected+2)   (Laplace smoothed) │
 └────────────────────────────────────────────────────────────────────────────────────┘

 final = clamp(base + bucket_adjust − 5 × risk,  0,  100)
 badge:  ≥70 → safe   ·   ≥45 → moderate   ·   <45 → caution
```

---

## 2. Run it (Windows + XAMPP, ~10 minutes)

Requirements: PHP ≥ 8.2 (with `pdo_mysql`, `mbstring`, `curl`, `intl`, `zip`), Composer, Node 18+, MySQL (XAMPP).

```bash
composer install
npm install
copy .env.example .env          # Mac/Linux: cp .env.example .env
php artisan key:generate
```
1. Start **MySQL** in XAMPP, create an empty database `nightlifebd` (utf8mb4_unicode_ci) in phpMyAdmin.
2. Check `DB_*` in `.env` (defaults: root, empty password).
3. Then:
```bash
php artisan migrate --seed       # tables + 4 test accounts + 42 demo venues + help points + ~60 demo reports
php artisan serve                # terminal 1  → http://127.0.0.1:8000
npm run dev                      # terminal 2  (CSS/JS)
php artisan schedule:work        # terminal 3  (optional: safety timers every minute, daily score refresh)
```
Optional: `php artisan havens:import` downloads **real** police stations / hospitals / 24-7 pharmacies of Dhaka from OpenStreetMap (needs internet). The seeded help-point coordinates are approximate.

### Test accounts (password `password`, local development only)
| Email | Role |
|---|---|
| user@test.com | user |
| partner@test.com | venue_partner (owns the demo venues) |
| safety@test.com | safety_admin (moderation) |
| admin@test.com | super_admin (also manages users) |

### Optional free keys (`.env`) — the app works without all of them
| Key | Breaks? | Fallback behaviour |
|---|---|---|
| `GEMINI_API_KEY` ([aistudio.google.com/apikey](https://aistudio.google.com/apikey)) | ❌ No | Built-in Bangla/Banglish keyword analyzer + local hashed-n-gram similarity + rule-based planner/tips |
| `ORS_API_KEY` ([openrouteservice.org](https://openrouteservice.org)) | ❌ No | Public OSRM demo server (prototype-grade, may be slow) |
| `TELEGRAM_BOT_TOKEN` (@BotFather) | ❌ No | Email alerts only (`MAIL_MAILER=log` writes to `storage/logs/laravel.log`) |
| `APP_URL` (tunnel via cloudflared/ngrok) | ⚠️ Partial | Live trip links work on the same LAN only; contacts off-network cannot open them |

Model names and free-tier limits change — verify them in Google AI Studio before the demo. Set `NIGHTLIFE_USE_QUEUE=true` and run `php artisan queue:work` if you prefer a real queue.

### Testing on a phone
Geolocation, the service worker and "Add to Home Screen" need **HTTPS** (or localhost). Expose your laptop with `cloudflared tunnel --url http://127.0.0.1:8000` or `ngrok http 8000`; set `APP_URL` to the tunnel URL so live-trip links work for your contacts.

---

## 3. 5-minute demo script

> **Before repeating the demo**: run `php artisan demo:reset` to clear trips, timers, dark spots and reset trust scores without re-seeding. Use `--yes` to skip the confirmation prompt.

1. **Explore** (`/venues`) — switch the time selector from *Evening* to *Late night*: badges change. Open a venue → "Why this score?" per time bucket.
2. Log in as `user@test.com` → **Report** in Bangla: `রাত ১১টায় ধানমন্ডি ২৭ এর কাছে আলো নেই, ছিনতাইয়ের চেষ্টা হয়েছে` → *My reports* shows the AI category/severity.
3. Log in as `safety@test.com` → **Moderate reports**: queue sorted by severity with AI summary, spam %, duplicates. Verify → the venue score and reporter trust update.
4. **Safe Route** — click A in Gulshan, B in Dhanmondi, time *Night* → grey fastest vs green safest, with reasons. Try *Report a dark spot*.
5. **Heatmap** — drag the hour slider. **Night Planner** — `৩ জন, ৩০০০ টাকা, শান্ত জায়গা, ধানমন্ডি`.
6. **SOS** — hold the red button 3 s (or double-tap), **Live trip**, **Safety timer**, **Fake call**. Turn off Wi-Fi: `/offline` still offers SOS + call 999.
7. `partner@test.com` → *My venues* → **Venue Safety Coach**.

---

## 4. Tests
```bash
php artisan test
```
Covers auth/roles, contact limit & phone validation, venue approval flow and age gate, report validation (500 chars), offline AI classification (Bangla, Banglish, English), duplicate detection, score time-awareness & explanation, moderation → trust update, safe-route scoring (sampling, incidents, dark spots, havens, 30% rule), routing-API failure fallback, night-planner budget/caution rules, trip link expiry (410), timers (dead-man switch), SOS audit log and nearest help points. Tests use in-memory SQLite and never call external APIs.

## 5. Project structure
```
app/Services/      GeminiService · LocalAnalyzer · ReportAnalyzer · SafetyScoreService · RouteService
                   NightPlannerService · VenueCoachService · AlertService
app/Console/Commands/  havens:import · safety:recalculate · safety:check-timers · trips:purge
app/Jobs/AnalyzeReportJob.php
app/Http/Controllers/  (Admin/, Partner/, …)       resources/views/ (Blade + Tailwind + Alpine)
public/sw.js · public/manifest.webmanifest · public/js/{sos,nl-map}.js     lang/bn.json
database/seeders/      Roles/accounts · Venues · SafeHavens · DemoReports
```

## 6. Honest limitations
* **No open crime dataset**: Risk comes from **community-verified reports + OpenStreetMap help points**. Sparse data = weaker scores — the explainability text says so explicitly.
* **Browsers cannot send SMS silently**: SOS opens a **pre-filled SMS** per contact; email/Telegram are sent automatically. No SMS gateway, payments, native apps or 99.99 % uptime target.
* **Live trip sharing**: Only works while the owner's browser tab stays open. Locations are deleted 72 h after a trip ends.
* **Free AI quota fragility**: Gemini free-tier quotas can run out mid-demo. The offline `LocalAnalyzer` is the fallback — it is less accurate (reports flagged for review), but the app continues to function.
* **OSRM public server**: For prototypes only; use an ORS key for steadier routing. Routing profile is driving (CNG / car / ride-share).
* **Lexicon bias in `LocalAnalyzer`**: The Bangla/Banglish keyword lexicon was hand-crafted. It will produce false positives/negatives for regional dialects, slang, and new vocabulary not in the list.
* **Reporter trust feedback loop**: A malicious actor who submits many reports that moderators verify gains a high trust score. Their subsequent negative reports then carry more weight in the safety score formula. Mitigated by moderator oversight and the Laplace-smoothed trust formula, but acknowledged as a limitation.
* **Cold start**: The safety score formula is correct but has limited signal without community data. At real launch every venue would initially show a checklist-only score.
* Seed data is fictional. Do not publish incident claims about real businesses without evidence and moderation.
* Test passwords in the seeder are for local development only.

## 7. Git workflow
`main` is protected; one feature per branch (`feature/<name>`), pull request, one reviewer, then merge. Never commit `.env`.
