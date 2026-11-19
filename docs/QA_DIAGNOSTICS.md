# NightLifeBD — Automated QA and Diagnostics Guide

## 1. Overview and Quality Gate Standards

NightLifeBD enforces strict quality and regression gates across three testing tiers:
- **Unit Tests (`tests/Unit`)**: Isolated algorithmic tests for route scoring, local sentiment/lexicon classification, and safety-score math.
- **Feature Tests (`tests/Feature`)**: Security boundary tests, privacy enforcement, role-based authorization, photo lifecycle, emergency timer/SOS workflows, and demo-mode isolation.
- **Smoke Tests (`tests/Smoke`)**: End-to-end user journeys ensuring that discovery, route comparison, report submission, safety tools, and admin moderation render and function as expected.

---

## 2. Environment Prerequisites

- **PHP Version**: `^8.2.0` (8.2.12 verified)
- **Composer**: `^2.2` (Composer 2.10.3 verified)
- **Node.js**: `^18.0.0` or `^20.0.0` (npm 10+)
- **Database for Testing**: SQLite (`:memory:`)

---

## 3. Diagnosing and Enabling Missing PHP Extensions

NightLifeBD requires specific PHP extensions for image processing, SQLite in-memory testing, and multibyte string handling.

### How to Check Loaded Extensions
Run:
```bash
php -m
```

### Essential Extensions Diagnostic Table

| Extension | Purpose in NightLifeBD | Symptom if Missing | Resolution |
| :--- | :--- | :--- | :--- |
| **`gd`** | Incident photo resizing (320x320 thumbnails), MIME byte verification, EXIF stripping, and test fake images. | `LogicException: GD extension is not installed.` Tests are marked skipped with diagnostic notice. | **Windows (XAMPP)**: Open `php.ini` (`C:\xampp\php\php.ini`), search for `;extension=gd` and remove the leading semicolon.<br>**Ubuntu/Debian**: `sudo apt install php8.2-gd` |
| **`pdo_sqlite`** / **`sqlite3`** | In-memory testing database (`:memory:`) ensuring zero mutations to production. | `PDOException: could not find driver` | **Windows**: Uncomment `;extension=pdo_sqlite` in `php.ini`.<br>**Ubuntu/Debian**: `sudo apt install php8.2-sqlite3` |
| **`fileinfo`** | Magic byte inspection of uploaded incident photos. | `Class 'finfo' not found` | Uncomment `;extension=fileinfo` in `php.ini`. |
| **`exif`** | EXIF / GPS stripping for reporter privacy protection. | `Call to undefined function exif_read_data()` | Uncomment `;extension=exif` in `php.ini`. |
| **`curl`** & **`openssl`** | Secure HTTPS communication with OSRM and AI APIs. | `cURL error 60: SSL certificate problem` | Ensure `;extension=curl` and `;extension=openssl` are enabled. |

---

## 4. Local Execution Commands

### Running Automated Test Suites
```bash
# Run full test suite (149 tests across Unit, Feature, Smoke)
php -d extension=gd vendor/phpunit/phpunit/phpunit

# Or run via Composer shortcuts:
composer test:unit       # Fast isolated unit tests
composer test:feature    # Security, auth, and reliability feature tests
composer test:smoke      # 5 principal user journeys smoke tests
```

### Code Formatting and Static Analysis
```bash
# Verify PSR-12 and Laravel code style without modifying files
composer lint

# Automatically fix code style inconsistencies
composer lint:fix

# Verify Blade templates compile without syntax or bracket errors
composer analyze

# Run all checks (lint + analyze + test)
composer check
```

### Frontend Asset Verification
```bash
npm run build
```

---

## 5. Diagnosing Database & Query Incompatibilities

NightLifeBD uses MySQL in production and SQLite in-memory for testing. When writing custom database queries:

1. **Avoid MySQL-Specific Date Functions**:
   Functions like `TIMESTAMPDIFF(HOUR, created_at, verified_at)` fail in SQLite with `no such column: HOUR`. Use database-agnostic calculations:
   ```php
   $driver = DB::connection()->getDriverName();
   $rawExpr = $driver === 'sqlite'
       ? "AVG((julianday(verified_at) - julianday(created_at)) * 24)"
       : "AVG(TIMESTAMPDIFF(HOUR, created_at, verified_at))";
   ```

2. **Verify Migrations on Fresh SQLite Database**:
   ```bash
   php artisan migrate:fresh --database=sqlite
   ```

---

## 6. External Service Isolation & Failure Simulation

All external network dependencies are faked in automated tests to prevent unintended real-world side effects:

- **OSRM / Routing Services**: Mocked via `Http::fake(['router.project-osrm.org/*' => ...])`. Tests simulate `NoRoute` (503), network timeouts (`ConnectionException`), and malformed geometries.
- **Gemini AI Classification**: Mocked via `Http::fake(['generativelanguage.googleapis.com/*' => ...])`. Tests verify prompt-injection shielding and deterministic local fallback (`LocalAnalyzer`).
- **Emergency Notifications & SOS**: Test environment forces `MAIL_MAILER=array` and `services.telegram.token=null`. Real SMS, phone calls, or emails are **never** dispatched during testing. Emergency contacts and 999 dialing are strictly simulated.

