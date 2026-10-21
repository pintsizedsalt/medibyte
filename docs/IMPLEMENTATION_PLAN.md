# MediByte — Implementation Plan & Current Status

**Last updated:** 2026-10-20
**Stack:** Laravel 13, Blade, MySQL, vanilla JavaScript, Sanctum API tokens
**UI:** Mobile-first Blade shell intended to be replaced by the final design

---

## 1. Product Summary

MediByte is a personal health tracker for students and young adults. It helps a user:

- Complete a guided health profile and onboarding flow.
- Track daily movement, water, sleep, meals, weight, mood, symptoms, and vitals.
- Keep a medication schedule and appointment list.
- See medication and appointment reminders.
- Store prescriptions, lab results, and medical certificates privately.
- Track menstrual cycles only when the profile is eligible.
- Download or delete their account data.

The app follows the case-study flow:

```
Register / log in
  → guided health setup
  → habits and body metrics
  → illness branch (optional)
  → personal goals
  → dashboard
  → daily logging, records, reminders, and CRUD
  → logout
```

---

## 2. What Was Fixed

| Issue | Resolution |
|---|---|
| Weight logging returned HTTP 500 because `weight_logs.bmi` was required but not filled | Removed the stored BMI column, added a computed `bmi` accessor and `$appends`; API now returns BMI in the weight-log response |
| Rewritten Blade pages used jQuery-style calls against a `getElementById` helper | Replaced with direct DOM accessors and shared helpers |
| API was split across legacy routes | Consolidated to `/api/v1`; old `/api/*` routes removed |
| New accounts could skip onboarding | Added `users.onboarding_completed_at`; new accounts start at `/profile-setup` |
| BMI was stored and became stale after height/weight changes | BMI is now derived from the latest weight and height |
| Reminders ignored the user preference | Reminder API and dashboard respect `reminders_enabled` |
| Public pages showed the authenticated top bar | Public pages hide the authenticated chrome |
| Duplicate/redundant performance indexes | Removed the duplicate daily-log index and the BMI index |

---

## 3. Completed Phases

### Phase 0 — Foundation

- [x] Laravel application runs with MySQL.
- [x] Registration, login, logout, session-cookie web auth, and Sanctum API tokens.
- [x] Login and registration throttling.
- [x] Token expiration configured to 7 days.
- [x] `users` table stores profile fields and onboarding state (the separate `profiles` table was merged into `users`).
- [x] Mobile-first shared Blade layout and a plain black-and-white responsive UI.
- [x] `/api/v1` route namespace.
- [x] Feature-test baseline with SQLite in-memory database.

### Phase 1 — Onboarding and daily tracking

- [x] Guided, one-section-per-step onboarding wizard.
- [x] Back navigation and skip controls on optional steps.
- [x] Cycle eligibility question and strict gating.
- [x] Activity, water, sleep, meals, body/BMI, weight goal, mood, illness branch, symptoms, vitals, medications, reminders, and goals.
- [x] Female users must choose a cycle status before completing onboarding.
- [x] Male users complete onboarding without a cycle step.
- [x] Post-onboarding Profile page for identity, cycle settings, and goals.
- [x] Separate Settings page for reminders, privacy, export, and account deletion.
- [x] Notifications as the single inbox for reminders and updates (the separate Alerts concept was removed).
- [x] Registration captures first name, last name, and a unique username.
- [x] Login with either email address or username.
- [x] Forgot-password request and reset-password flow.
- [x] Dashboard focused on upcoming appointments, recent symptoms, active medications, and unread reminders.
- [x] CRUD for all daily logs and health records through the generic API client.
- [x] Server-side validation for walking logs, sleep logs, weight logs, and reminder preferences.
- [x] Documents module for private PDF/JPG/PNG uploads, authenticated download, and delete.
- [x] In-app notifications for due medications and upcoming appointments.
- [x] Account JSON export and password-confirmed account deletion.
- [x] Privacy notice and DPA consent copy.
- [x] PWA shell: manifest, generated icons, service worker, and offline fallback.
- [x] Demo data seeder.

### Phase 2 — Partial

- [x] Private document storage.
- [x] Database notifications and scheduler command.
- [x] Account export/delete controls.
- [x] PWA install shell.
- [x] Secure HttpOnly session-cookie web auth with CSRF protection.
- [ ] Push notifications (web push / email / SMS).
- [ ] Field-level encryption for sensitive free-text fields.
- [ ] Final front-end design replacement.
- [ ] Production deployment hardening and backups.

---

## 4. Cycle Tracker Eligibility

The tracker is deliberately narrow. It is shown only when both conditions are true:

```php
$profile->sex === 'female' && $profile->period_status === 'active'
```

| Sex | Cycle status | Tracker shown? |
|---|---|---|
| Female | `active` | Yes |
| Female | `not_yet` | No |
| Female | `menopause` | No |
| Female | `prefer_not_to_track` | No |
| Male | Any value | No |

This prevents the tracker from appearing for men, pre-menarche users, and users who are in menopause. The API also returns `403` from period endpoints when the profile is ineligible.

**Wording decision:** The onboarding wording is direct and clinical — “Your menstrual cycle,” “Yes, I currently have a cycle,” “Not yet,” “No, I am in menopause,” and “I prefer not to track.” The earlier inclusive “track your cycle?” recommendation was rejected and is **not** part of the implementation.

---

## 5. UX and Mobile

- Single-column mobile layout with large touch targets.
- Sticky top bar and horizontally scrollable navigation.
- Cards for metrics, forms, and record lists.
- Inline success/error messages.
- Confirm dialogs before destructive actions.
- Public pages (`/login`, `/register`, `/privacy`) hide authenticated navigation.
- Onboarding hides the main navigation to keep one task per step.
- PWA manifest, themed status bar, installable icons, and offline fallback.

The current Blade UI is intentionally simple. The visual design can be replaced without changing the API or data model.

---

## 6. API Surface (`/api/v1`)

### Public

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/register` | Create account and profile |
| POST | `/login` | Return Sanctum token |

### Authenticated

| Group | Endpoints |
|---|---|
| Account | `GET /user`, `POST /logout`, `GET /account/export`, `DELETE /account` |
| Profile | `GET /profile`, `POST /profile`, `POST /profile/complete` |
| Daily | `GET/POST/PUT/DELETE /water-logs`, `/sleep-logs`, `/walking-logs`, `/meal-logs`, `/weight-logs` |
| Wellbeing | `/moods`, `/mood-logs`, `/symptom-logs`, `/vital-sign-logs` |
| Cycle | `GET /period-predict`, `/period-logs` |
| Care | `/medications`, `/appointments` |
| Documents | `GET/POST /documents`, `GET /documents/{id}/download`, `DELETE /documents/{id}` |
| Reminders | `GET /reminders`, `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all` |
| Insights | `GET /progress`, `GET /dashboard` |

Authenticated routes use `auth:sanctum` and a 120 requests/minute throttle. Browser requests authenticate with the HttpOnly session cookie; external clients can send a bearer token.

---

## 7. Data, Privacy, and Security

### Data controls

- Consent is recorded in `users.consented_at` at registration.
- Users can export profile, logs, medications, appointments, and document metadata as JSON.
- Account deletion requires the current password and removes the user, related records, notifications, tokens, and private document files.
- The privacy notice explains collection, purpose, storage, and deletion.

### Storage

- Documents are stored on the private `local` disk at `storage/app/private/documents/{user_id}`.
- Documents are downloaded only through an authenticated API route.
- JSON export includes document metadata but not the file bytes; users download files separately before deleting.

### Current security limitations

- Web pages use an HttpOnly session cookie with CSRF protection. API clients may still use Sanctum bearer tokens returned by login/registration.
- Free-text health notes are stored in plaintext in the database. Field-level encryption should be added before handling real patient data.
- There is no email verification, MFA, audit log, or rate limit specific to export/delete. Password reset is available by email.
- In-app notifications are database-only; no push/email delivery is configured.

---

## 8. Notifications

`php artisan medibyte:send-reminders` runs every 15 minutes via the scheduler. It:

- Ignores users with `reminders_enabled = false`.
- Creates a database notification for each active medication time inside the next 15 minutes.
- Creates a database notification for scheduled appointments in the next 24 hours.
- Deduplicates by a stored `dedupe_key` so the same reminder is not sent repeatedly.

Run the scheduler locally with:

```bash
php artisan schedule:work
```

In production, configure the system cron entry for `php artisan schedule:run`.

---

## 9. Testing

Current automated coverage:

- 30 tests, 134 assertions, all passing.
- Auth, registration, onboarding, and cycle gating.
- BMI calculation and user isolation.
- Progress percentages.
- Walking and sleep validation.
- Cycle prediction.
- Reminder preference handling.
- Notification scheduler and notification read state.
- Document upload, download, validation, ownership, and deletion.
- Account export, password-confirmed deletion, and private-file cleanup.
- Public page routing.

Run:

```bash
php artisan test
```

---

## 10. Remaining Roadmap

### Before production / UAT

1. Add field-level encryption for sensitive notes and reflections, plus a strict CSP and XSS review.
2. Add email verification.
3. Add audit logging for access to documents and exports.
4. Configure production queue, scheduler, backups, and file storage.
5. Replace generated demo icons with final brand assets.

### Product enhancements

1. Push/email reminders and notification preferences per channel.
2. PDF export of health summaries.
3. Trend charts and weekly insights.
4. Clinician-shared access links with expiry.
5. Offline mutation queue (the PWA currently caches the shell but does not sync writes offline).

---

## 11. Acceptance Checklist

- [x] A new user can register with first name, last name, and a unique username, then complete onboarding.
- [x] Users can log in with an email address or username and reset a forgotten password by email.
- [x] Female active-cycle users see cycle tracking.
- [x] Male, pre-menarche, menopause, and prefer-not-to-track users do not.
- [x] Dashboard focuses on upcoming appointments, recent symptoms, active medications, and unread reminders.
- [x] Weight/BMI logging works without a 500 error.
- [x] Daily logs can be created, edited, and deleted.
- [x] Medications and appointments can be managed.
- [x] Documents upload, download, and delete privately.
- [x] Reminders respect the user’s switch.
- [x] Users can export and delete their data.
- [x] The app is installable as a PWA and has an offline fallback.
- [x] Web sessions use HttpOnly cookies with CSRF protection; logout clears the session.
- [x] Automated tests pass.
