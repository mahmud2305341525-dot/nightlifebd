# NightLifeBD — Stage 4: Production Security, Privacy & Reliability Hardening Audit Report

**Date**: November 24, 2026  
**Auditor**: Senior Laravel Architect & Application Security Engineer  
**Status**: Completed & Verified  
**Test Baseline**: 281 tests, 1,128 assertions  
**Post-Audit Results**: 294 tests, 1,180 assertions (13 new security tests, +52 assertions, 0 failures)

---

## 1. Executive Summary

A comprehensive, defense-in-depth security, privacy, database reliability, and financial integrity audit was conducted across the NightLifeBD Laravel 12 application codebase.

The audit verified authentication mechanisms, session management, access controls, rate limiting, file upload security, PII protection, payment lifecycle integrity, AI failure resiliency, and database concurrency guarantees. All confirmed vulnerabilities and reliability risks have been addressed and validated through dedicated automated regression tests.

---

## 2. Vulnerabilities & Reliability Issues Discovered

| ID | Module / Area | Description | Severity | Status |
| :--- | :--- | :--- | :--- | :--- |
| **SEC-05** | Payments (`MockPaymentGateway`) | Webhook callback replayed for already confirmed bookings created duplicate `payment.confirmed` events. | **Medium** | **Fixed** |
| **SEC-06** | Payments (`MockPaymentGateway`) | Delayed webhooks revived cancelled or expired bookings without capacity checks. | **Critical** | **Fixed** |
| **SEC-07** | Payments (`MockPaymentGateway`) | Missing payment amount and currency verification allowed underpaid callbacks. | **High** | **Fixed** |
| **SEC-08** | Authentication (`PasswordResetLinkController`) | Password reset endpoint lacked route throttling, allowing email spam and SMTP exhaustion. | **Medium** | **Fixed** |
| **SEC-09** | Ticketing (`EventBookingController`) | Event ticket booking and cancellation endpoints lacked request throttling. | **Medium** | **Fixed** |
| **SEC-10** | Information Disclosure (`PasswordResetLinkController`) | SMTP failure message exposed server log paths (`storage/logs/laravel.log`) to users. | **Low** | **Fixed** |
| **SEC-11** | Data Privacy & Inventory (`ProfileController`) | Account deletion cascade-deleted bookings without releasing held ticket tier capacity. | **Medium** | **Fixed** |
| **SEC-12** | Background Inventory (`ExpirePaymentBookings`) | Unclamped decrement could theoretically produce negative inventory counts under drift. | **Low** | **Fixed** |
| **SEC-13** | Gate Security (`EventModerationController`) | Check-in verification had a TOCTOU race condition allowing simultaneous double check-ins. | **High** | **Fixed** (in Stage 3.1) |

---

## 3. Severity Classification & Root Cause Analysis

### SEC-06: Revival of Cancelled/Expired Bookings via Delayed Webhooks (Critical)
- **Root Cause**: `MockPaymentGateway::handleWebhook` checked `if ($booking->payment_status !== 'paid')`. For a booking cancelled by user or expired by `payments:expire` scheduler, this condition evaluated to `true`. When a delayed webhook arrived, it updated `$booking->status = 'confirmed'` and `$booking->payment_status = 'paid'`, effectively issuing a valid ticket after its seat had already been restored to inventory and sold to someone else.
- **Remediation**: Added explicit terminal state verification. If `in_array($booking->status, ['cancelled', 'expired'])` or `in_array($booking->payment_status, ['cancelled', 'expired', 'refund_pending', 'refunded'])`, the webhook is rejected with `HTTP 422`, records a `payment.rejected_late` security event, and refuses to revive the booking.

### SEC-07: Payment Amount & Currency Mismatch (High)
- **Root Cause**: Webhooks verified only the HMAC provider reference signature without checking that the amount paid in the payload matched `$booking->total_price` and that currency matched `$booking->currency`.
- **Remediation**: Added strict financial validation:
  ```php
  if ($request->filled('amount') && (float) $request->input('amount') < (float) $booking->total_price) {
      PaymentEvent::create(['event_type' => 'payment.amount_mismatch', ...]);
      return ['status' => 'rejected', 'message' => 'Payment amount mismatch'];
  }
  ```

### SEC-05: Webhook Replay & Duplicate Payment Events (Medium)
- **Root Cause**: Every arriving webhook created a `payment.confirmed` event in `PaymentEvent` prior to checking whether the booking was already marked paid.
- **Remediation**: Implemented idempotency: if `$booking->payment_status === 'paid'`, the gateway returns `['status' => 'confirmed', 'message' => 'Already confirmed']` without appending duplicate event rows.

### SEC-08 & SEC-09: Unthrottled Sensitive State-Changing Endpoints (Medium)
- **Root Cause**: `POST /forgot-password`, `POST /events/{event}/book`, and `POST /events/bookings/{booking}/cancel` lacked route-level rate limiting middleware.
- **Remediation**:
  - Bound `throttle:6,1` to `password.email` in `routes/auth.php`.
  - Bound `throttle:15,1` to `events.book` in `routes/web.php`.
  - Bound `throttle:15,1` to `events.bookings.cancel` in `routes/web.php`.

### SEC-10: Sensitive Server Information Disclosure (Low)
- **Root Cause**: `PasswordResetLinkController.php` caught mail transport exceptions and returned:
  `'Could not deliver reset email. Please verify SMTP credentials or check storage/logs/laravel.log.'`
- **Remediation**: Replaced with user-safe message:
  `'Could not deliver reset email. Please try again later or contact support.'`
  Stack trace and error messages remain strictly confined to `Log::warning(...)`.

### SEC-11: Capacity Stranding on Account Deletion (Medium)
- **Root Cause**: `ProfileController::destroy` disassociated reports and deleted contacts and trips, but foreign key cascade on `event_bookings` deleted booking records directly. Future events retained the incremented `booked_count` on `event_ticket_tiers`, permanently locking out seats.
- **Remediation**: Prior to user deletion, `ProfileController::destroy` iterates through active bookings for future events and safely decrements `booked_count` on affected ticket tiers.

### SEC-12: Unclamped Capacity Decrement in Expiration Command (Low)
- **Root Cause**: `ExpirePaymentBookings::handle` executed direct `$booking->ticketTier()->decrement('booked_count', $booking->quantity)`.
- **Remediation**: Clamped decrement to `min($tier->booked_count, $booking->quantity)` to prevent negative inventory values.

---

## 4. Files Modified

1. `app/Services/MockPaymentGateway.php`:
   - Enforced idempotency on confirmed bookings.
   - Enforced amount and currency matching.
   - Guarded against reviving cancelled or expired bookings.
   - Added `payment.amount_mismatch` and `payment.rejected_late` audit events.
2. `routes/auth.php`:
   - Added `throttle:6,1` rate limiting to `password.email`.
3. `routes/web.php`:
   - Added `throttle:15,1` rate limiting to `events.book` and `events.bookings.cancel`.
4. `app/Http/Controllers/Auth/PasswordResetLinkController.php`:
   - Removed internal log paths and configuration advice from user-facing error strings.
5. `app/Http/Controllers/ProfileController.php`:
   - Safely restored ticket tier capacity for upcoming events upon user account deletion.
6. `app/Console/Commands/ExpirePaymentBookings.php`:
   - Added safe minimum clamp to ticket capacity restoration.
7. `bootstrap/app.php`:
   - Added structured security logging for CSRF token mismatch exceptions (`TokenMismatchException`).

---

## 5. Security Regression Tests (`Stage4ProductionSecurityTest.php`)

A dedicated test suite was created in `tests/Feature/Stage4ProductionSecurityTest.php` with 13 comprehensive tests:

1. `test_user_cannot_view_another_users_digital_pass_or_delete_contacts`: Verifies IDOR protection on digital event passes and emergency contacts.
2. `test_unauthenticated_requests_to_protected_routes_are_redirected_to_login`: Confirms auth middleware guards `/trips`, `/my-bookings`, `/profile`, `/contacts`, and `/my-reports`.
3. `test_regular_user_and_event_organizer_cannot_escalate_roles`: Confirms role escalation attempts via `admin.users.update` return HTTP 403 Forbidden.
4. `test_photo_service_rejects_fake_image_file_with_png_extension`: Validates magic-byte binary MIME verification against disguised executable scripts.
5. `test_forgot_password_endpoint_is_rate_limited`: Confirms rate limiting triggers HTTP 429 after 6 rapid requests.
6. `test_webhook_replay_for_already_paid_booking_does_not_create_duplicate_events`: Verifies webhook idempotency.
7. `test_webhook_cannot_revive_expired_or_cancelled_booking`: Verifies delayed payment callbacks cannot resurrect cancelled/expired tickets.
8. `test_webhook_rejects_tampered_amount`: Verifies payment callbacks with underpaid amounts are rejected.
9. `test_webhook_rejects_unverified_signature`: Verifies forged webhook signatures are rejected and logged.
10. `test_account_deletion_safely_releases_held_seats_for_upcoming_events`: Confirms held ticket capacity is restored when a user account is deleted.
11. `test_unverified_report_photos_are_strictly_inaccessible_to_unauthorized_users`: Verifies private filesystem storage disk enforces authorization headers.
12. `test_password_reset_failure_does_not_reveal_internal_server_paths`: Verifies mail transport errors omit sensitive server paths.
13. `test_report_analyzer_falls_back_deterministically_when_gemini_fails`: Confirms local keyword NLP analyzer activates seamlessly when Gemini is unavailable.

---

## 6. Test Execution Results

```
Command: php artisan test
Exit Code: 0
Total Tests:      294 passed
Total Assertions: 1180 assertions
Duration:         27.88s
Failures:         0
Errors:           0
Skipped:          0
```

### Stage-by-Stage Verification Breakdown:
- **Stage 1 Critical Fixes**: 12/12 passed (anonymized moderation, privacy export schema, Telegram webhook HMAC, Gemini model configuration).
- **Stage 2 Performance**: 5/5 passed (event N+1 elimination, notification scaling, viewport bounding box query).
- **Stage 2.1 Hardening**: 15/15 passed (notification atomicity, index existence, venue aggregate rounding).
- **Stage 3 Authorization**: 17/17 passed (trip view limits, event organizer role, ticket cancellation workflows).
- **Stage 3.1 Deep Audit**: 10/10 passed (expired trip PIN rejection, check-in scanner race condition atomicity).
- **Stage 4 Production Security**: 13/13 passed (IDOR, role escalation, MIME spoofing, webhook idempotency, rate limiting).
- **Core Domain & Smoke Journeys**: 222/222 passed (safety scoring, route samplers, NLP classifiers, emergency timers, user journeys).

---

## 7. Remaining Production Risks & Operational Requirements

1. **Automated Gateway Refund Interface**:
   - The `PaymentGateway` interface does not currently declare a `refund()` method.
   - Cancelled paid tickets transition to `refund_pending` and generate `payment.refund_requested` events.
   - **Production Requirement**: Operational finance teams must manually disburse funds via MFS merchant dashboards (bKash/Nagad) until a dedicated disbursement API is implemented.
2. **Reverse Proxy & HTTPS Configuration**:
   - Ensure production load balancers/proxies (e.g. AWS ALB, Cloudflare, NGINX) pass `X-Forwarded-Proto: https` so `trustProxies(at: '*')` in `bootstrap/app.php` properly enforces HTTPS session cookies (`SESSION_SECURE_COOKIE=true`).
3. **Queue Worker Daemon**:
   - Background jobs (`AnalyzeReportJob`, `NotifyUpcomingEvents`, `ExpirePaymentBookings`) should run under Supervisor with `--tries=3 --timeout=60`.

---

## 8. Database Migration & Rollback Instructions

### Migration Status:
- All Stage 2 and Stage 2.3 performance and integrity migrations are active:
  - `2026_11_24_000001_add_stage2_performance_and_integrity_indexes.php`
  - `2026_11_24_000002_optimize_venue_geo_index.php`
- Stage 4 required **no schema alterations**; all changes operate cleanly within the existing schema.

### Rollback Strategy:
- A pristine database snapshot was generated prior to Stage 4 changes at `database/database.sqlite.stage4_backup`.
- To restore the Stage 4 baseline database if necessary:
  ```powershell
  Copy-Item database/database.sqlite.stage4_backup database/database.sqlite -Force
  ```
- To roll back code changes to the Stage 3.1 checkpoint:
  ```powershell
  git checkout app/Services/MockPaymentGateway.php app/Http/Controllers/ProfileController.php app/Http/Controllers/Auth/PasswordResetLinkController.php app/Console/Commands/ExpirePaymentBookings.php bootstrap/app.php routes/auth.php routes/web.php
  ```
