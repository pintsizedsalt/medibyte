# MediByte — Value Analysis

This table maps the case-study problems to implemented features and the evidence that each feature works.

| # | User problem | MediByte feature | How it works | Evidence / test | Status |
|---|---|---|---|---|---|
| 1 | A student forgets whether they drank enough water, slept enough, or moved during the day. | Daily health summary | Shows a daily health score, 7-day progress, key metrics, cycle or mood, next care items, and a wellbeing check, with full logs one tap away. | Browser smoke test with seeded demo accounts; `DashboardTest`; `HealthTrackingTest::test_progress_returns_goal_percentages_and_bmi` | Done |
| 2 | Logging health data is tedious and easy to abandon. | Guided onboarding and quick actions | One focused section per step, optional skips, back navigation, and dashboard quick-add buttons. | Browser test of complete female and male onboarding; 21 automated tests | Done |
| 3 | Users need a simple way to see medication times and upcoming appointments. | Medication schedule and appointment list | CRUD records plus a “coming up” dashboard section and reminder API. | `NotificationTest`, `HealthTrackingTest::test_reminders_can_be_turned_off_and_on`, browser test | Done |
| 4 | Important health records are scattered or lost. | Private Documents module | PDF/JPG/PNG upload to private local storage; authenticated download; owner-only access and deletion. | `DocumentTest` upload/download/isolation/delete; browser upload/download/delete | Done |
| 5 | Cycle tracking is often shown to users it does not apply to. | Strict cycle eligibility gate | Tracker appears only for `sex = female` and `period_status = active`; other profiles receive `403` from period endpoints. | `AuthAndOnboardingTest` cycle-gating tests; browser menopause check | Done |
| 6 | Users want reminders without being overwhelmed or tracked when they opt out. | Reminder preference | `reminders_enabled` suppresses scheduler notifications and keeps the notification inbox free of reminder entries. | `HealthTrackingTest::test_reminders_can_be_turned_off_and_on`; `NotificationTest::test_the_scheduler_respects_the_reminder_switch` | Done |
| 7 | Health data is sensitive and users need control over it. | Export and delete account | JSON export of records; password-confirmed deletion removes logs, tokens, notifications, and private files. | `AccountDataTest` export, wrong-password, deletion, and file-cleanup tests | Done |
| 8 | Users may not know what data is collected or why. | Privacy notice and consent | Registration records consent; `/privacy` explains collection, purpose, storage, and deletion. | `AccountDataTest::test_the_privacy_notice_is_public`; registration consent test | Done |
| 9 | A website is inconvenient on a phone. | PWA shell | Manifest, install icons, service worker, themed mobile UI, and offline fallback. | Browser check of manifest, service-worker scope, and theme-color | Done |
| 10 | Demo/UAT setup takes too long. | Demo data seeder | Creates female and male demo accounts with logs, goals, cycle history, medication, appointment, document, and notification. | `php artisan db:seed`; browser login to `demo.female@medibyte.test` | Done |
| 11 | Free-text health notes could be exposed if the database is compromised. | Field-level encryption | Planned encrypted casts for notes/reflections. | Not implemented | Planned |
| 12 | Bearer tokens in `localStorage` are exposed to XSS. | HttpOnly session-cookie auth | Web pages now authenticate with an HttpOnly Laravel session cookie and a CSRF header; API clients can still use bearer tokens. | `AuthAndOnboardingTest::test_web_login_creates_a_session`; browser login, POST, download, and logout smoke tests | Done |
| 13 | Users may want reminders even when the app is closed. | Push/email notifications | Currently in-app database notifications only; scheduler runs server-side. | Not implemented | Planned |

## Value summary

MediByte’s core value is not a single tracker; it is the **reduction of friction across the whole daily-health loop**:

1. **Start with context** — onboarding captures goals, cycle eligibility, and baseline metrics.
2. **Log in seconds** — quick actions and one-field forms lower the effort of daily tracking.
3. **See meaning immediately** — the dashboard converts raw entries into progress, BMI, cycle estimates, and reminders.
4. **Keep records safe** — private documents and authenticated access support real health paperwork.
5. **Stay in control** — reminders can be disabled; data can be exported or deleted; the privacy notice is explicit.

The current implementation is suitable for coursework, demos, and UAT. Production use with real patient data should first close the security items in the roadmap (encrypted notes, email verification, backups, and audit logging).
