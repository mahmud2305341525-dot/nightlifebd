# NightLifeBD — Staging Deployment & Production Readiness Guide

This guide establishes the infrastructure requirements, environment specifications, migration safety procedures, security policies, and deployment/rollback protocols for deploying NightLifeBD to a controlled staging environment.

---

## 1. Supported Runtime & System Requirements

### Host & Runtime Specifications
- **Operating System**: Linux (Ubuntu 22.04 LTS or 24.04 LTS recommended)
- **PHP Version**: `8.2.x` (or `8.3.x`)
- **Web Server**: Nginx `^1.22` or Apache `^2.4` with `mod_rewrite` enabled
- **Database**: MySQL `8.0+` or MariaDB `10.6+` (InnoDB engine, `utf8mb4_unicode_ci`)
- **In-Memory Store (Recommended for Production)**: Redis `7.x` for cache, session, and queue management
- **Node.js**: `20.x` LTS (for building frontend assets via Vite during build phase)

### Required PHP Extensions
The PHP CLI and FPM runtimes must have the following extensions enabled:
- `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`.

---

## 2. Required Environment Variables (.env.staging)

```dotenv
# ==============================================================================
# NightLifeBD Staging Environment Configuration
# ==============================================================================

# Core Application Configuration
APP_NAME=NightLifeBD
APP_ENV=staging
APP_KEY=base64:GENERATE_WITH_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://staging.nightlifebd.com
APP_TIMEZONE=Asia/Dhaka
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

# Security & Demo Isolation (MANDATORY FOR STAGING & PRODUCTION)
NIGHTLIFE_DEMO_ENABLED=false
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

# Logging & Observability
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=14

# Database Connection (MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nightlifebd_staging
DB_USERNAME=nightlife_app
DB_PASSWORD=STRONG_RANDOMLY_GENERATED_DB_SECRET

# Session Security (Enforces HTTPS Secure Cookies)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PATH=/
SESSION_DOMAIN=null

# Cache & Queues
CACHE_STORE=database
QUEUE_CONNECTION=database
NIGHTLIFE_USE_QUEUE=true

# Filesystem & Storage
FILESYSTEM_DISK=local

# Mail Dispatch (Staging uses Mailpit/Mailgun/Log)
MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@staging.nightlifebd.com"
MAIL_FROM_NAME="${APP_NAME} Staging"

# Third-Party Integrations (Supplied via Secrets Manager)
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash
GEMINI_EMBED_MODEL=gemini-embedding-001
ORS_API_KEY=
TELEGRAM_BOT_TOKEN=
```

---

## 3. Filesystem Permissions & Private Storage Architecture

In production/staging Linux environments, files must be owned by the web server user (e.g., `www-data`):

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/nightlifebd

# Base permissions: directories 755, files 644
sudo find /var/www/nightlifebd -type d -exec chmod 755 {} \;
sudo find /var/www/nightlifebd -type f -exec chmod 644 {} \;

# Writable directories (Framework cache, logs, sessions, app storage)
sudo chmod -R 775 /var/www/nightlifebd/storage
sudo chmod -R 775 /var/www/nightlifebd/bootstrap/cache

# Incident Photo Private Disk Isolation
# NEVER create a public symlink to this directory
sudo chmod -R 770 /var/www/nightlifebd/storage/app/private/incident_photos
```

---

## 4. Background Workers & Task Scheduling

### 1. Task Scheduler (Mandatory for Safety Timers & Purge)
The Laravel Scheduler executes safety-critical tasks, specifically:
- `safety:check-timers` (Runs **every minute** `* * * * *` — dead-man switch alerting contacts when a safety timer expires without confirmation)
- `trips:purge` (Runs **hourly** `0 * * * *` — purges location coordinates and pings older than 72 hours for privacy)
- `safety:recalculate` (Runs **daily** `0 0 * * *` — updates score decay weights across venues)

> [!IMPORTANT]
> Without a running scheduler, overdue safety timers will **never** trigger automatic alerts. Setting up the scheduler is mandatory.

#### Linux Crontab Configuration
Add a single crontab entry for the web server user (`www-data` or `deploy`):
```cron
* * * * * cd /var/www/nightlifebd && php artisan schedule:run >> /dev/null 2>&1
```

#### Windows Task Scheduler Configuration
For Windows Server or local testing environments:
1. Open **Task Scheduler** (`taskschd.msc`) → **Create Task**.
2. Under **General**, name the task `NightLifeBD-Scheduler` and configure to run with highest privileges.
3. Under **Triggers**, set **Begin the task**: *On a schedule*, **Repeat task every**: *1 minute*, **for a duration of**: *Indefinitely*.
4. Under **Actions**, select **Start a program**:
   - **Program/script**: `C:\path\to\php\php.exe`
   - **Add arguments**: `artisan schedule:run`
   - **Start in**: `C:\path\to\nightlifebd`
5. Alternatively, for local demonstration testing in a dedicated terminal:
   ```powershell
   php artisan schedule:work
   ```

---

### 2. Queue Worker Architecture & Requirements (`NIGHTLIFE_USE_QUEUE`)

The application's queue requirements depend on the `NIGHTLIFE_USE_QUEUE` configuration in `.env`:

#### Default Mode (`NIGHTLIFE_USE_QUEUE=false`):
- AI report classification uses `AnalyzeReportJob::dispatchAfterResponse($reportId)`.
- The job executes in the same PHP process immediately after the HTTP response is returned to the user.
- **A continuously running queue worker is NOT required** in this mode.
- Ideal for simplified staging, demo presentations, and resource-constrained environments.

#### Queued Mode (`NIGHTLIFE_USE_QUEUE=true`):
- Asynchronous dispatch pushes jobs to the `jobs` database table.
- **A continuously running worker IS mandatory** (`php artisan queue:work`).
- **Failure Resilience**: If job processing fails (e.g., external API timeout), `AnalyzeReportJob::failed()` automatically catches the exception and marks the report with `needs_review = true` and `analysis_source = 'failed'`, ensuring unclassified reports are sent to human moderators rather than lost.
- **Failed Job Monitoring**:
  - View failed jobs: `php artisan queue:failed`
  - Retry failed jobs: `php artisan queue:retry all` (or specific ID)
  - Clear failed jobs: `php artisan queue:flush`

#### Supervisor Configuration (Linux Production / Queued Mode)
Create `/etc/supervisor/conf.d/nightlifebd-worker.conf`:
```ini
[program:nightlifebd-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nightlifebd/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/nightlifebd-worker.log
stopwaitsecs=3600
```

#### Safety-Critical Operations Independence from Queue:
The following safety-critical workflows operate **synchronously inline or via the scheduler and do NOT depend on queue workers**:
- **SOS Emergency Alerting**: Dispatches immediately within the HTTP request.
- **Safety Timer Creation & Confirmation**: Synchronous atomic database transactions.
- **Dead-Man Switch Escalation**: Handled directly in `CheckSafetyTimers` command via the scheduler.
- **Live Trip Sharing & Pinging**: Immediate synchronous database updates.
- **Trip Expiration & Revocation**: Passive on-access check via `isActive()` (serves HTTP 410 without worker).

---

### 3. External Services & Fallback Architecture

| External Service | Role | Status | Failure / Fallback Behavior |
| :--- | :--- | :---: | :--- |
| **MySQL / MariaDB** | Primary application database | **Mandatory** | Required for all operations. Atomic transactions protect emergency contact and timer limits. |
| **PHP ext-gd** | EXIF stripping, image downscaling, thumbnails | **Mandatory** | Required for private incident photo processing and validation. |
| **Telegram Bot API** (`TELEGRAM_BOT_TOKEN`) | Emergency alert notifications to contacts | **Optional** | If unconfigured or times out, warning is logged; UI provides direct WhatsApp and cellular 999 fallbacks. Request does not crash. |
| **Email Service** (`MAIL_MAILER`) | Electronic emergency notifications | **Optional** | Local dev uses `MAIL_MAILER=log` (`logged_local`); staging/prod uses SMTP. Dispatch errors are caught and logged without breaking SOS. |
| **Google Gemini AI** (`GEMINI_API_KEY`) | Incident text categorization & severity | **Optional** | If API key is missing, network fails, or quota exceeded, seamlessly falls back to local Bangla/Banglish keyword analyzer (`LocalAnalyzer`). |
| **OSRM Routing** (`router.project-osrm.org`) | Multi-route calculation & detour checking | **Optional** | If external routing server fails or times out, `RouteService` falls back to local Euclidean distance and marks route confidence as "Unmonitored" (Grade U). |

---

## 5. Database Migration Safety & Verification

All NightLifeBD migrations have been reviewed:
- **Zero Destructive Column Drops**: Migrations are strictly additive.
- **Foreign Key Constraints**: Uses `constrained()->cascadeOnDelete()`.
- **Composite Indexes**: Composite indexes on `reports(venue_id, status)` and `reports(status, lat, lng)` prevent full-table scans.
- **Rollback Safety**: Every migration implements a corresponding, tested `down()` method.

Execution rule:
```bash
# Run migrations safely in staging
php artisan migrate --force
```

---

## 6. Rollback, Backup, and Disaster Recovery Procedures

### Database Backup
```bash
mysqldump -u nightlife_app -p --single-transaction --quick --routines --triggers nightlifebd_staging > /var/backups/db_backup_$(date +%F_%T).sql
```

### Storage Backup (Private Incident Photos)
```bash
tar -czf /var/backups/incident_photos_$(date +%F_%T).tar.gz /var/www/nightlifebd/storage/app/private/incident_photos
```

### Fast Rollback Procedure
If a deployment fails:
1. **Enter Maintenance Mode**:
   ```bash
   php artisan down --secret="emergency-bypass-token" --render="errors::503"
   ```
2. **Rollback Git Commit / Release Symlink**:
   ```bash
   git checkout <previous-stable-tag-or-commit>
   composer install --no-dev --optimize-autoloader
   npm run build
   ```
3. **Rollback Last Migration Batch (if applicable)**:
   ```bash
   php artisan migrate:rollback --step=1
   ```
4. **Purge Stale Caches**:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   ```
5. **Restore Application Traffic**:
   ```bash
   php artisan up
   ```

## 7. Production Runbook

Before first traffic:

1. Set `APP_ENV=production`, `APP_DEBUG=false`, `NIGHTLIFE_DEMO_ENABLED=false`, `NIGHTLIFE_USE_QUEUE=true`.
2. Configure Redis for session, cache, and queue.
3. Configure a real SMTP/transactional mail driver.
4. Rotate Gemini and ORS credentials; set `GEMINI_MODEL` and `GEMINI_FALLBACK_MODEL`.
5. Run imports in order:
   ```bash
   php artisan havens:import
   php artisan venues:import --area="Dhaka Metropolitan"
   php artisan admin:create --email=admin@example.com
   php artisan migrate --force
   php artisan nightlife:preflight
   ```
6. Install `deployment/cron` and `deployment/nightlifebd-worker.conf`.
7. Test `/health` with a load balancer; alert if it returns 503.
8. Test database restore and private-photo restore before launch.
9. Set up uptime monitoring, structured-log export, and at least one real SMTP/alert channel.

