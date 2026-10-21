# MediByte

MediByte is a mobile-first personal health tracker built with Laravel and Blade. It was created for a health-tracking case study and follows the project flowchart: account setup → guided health onboarding → daily tracking → records and reminders → dashboard → account controls.

The current Blade interface is intentionally simple and mobile-first. It can be replaced by the final visual design without changing the API or data model.

---

## Features

- Guided, step-by-step onboarding with optional sections.
- Secure web authentication using HttpOnly session cookies and CSRF protection.
- Registration and login with a plain split welcome panel beside the form.
- Login with either an email address or username.
- Password reset by email using Laravel's signed reset tokens.
- Strict cycle-tracker eligibility:
  - Visible only for `sex = female` and `period_status = active`.
  - Hidden for male, pre-menarche, menopause, and prefer-not-to-track profiles.
- Daily logs for water, sleep, walking/activity, meals, weight, mood, symptoms, and vitals.
- BMI calculated from the latest weight and height.
- Progress percentages against personal goals.
- Medication schedule and appointment list.
- Health summary dashboard with a daily health score, 7-day progress, key metrics, cycle or mood, next appointment/medication, and a wellbeing check.
- In-app notifications as the single inbox for reminders and account updates; there is no separate alerts feed.
- Private Documents module for prescriptions, lab results, and certificates.
- Separate Profile and Settings pages:
  - Profile: identity, username/email, cycle settings, and daily goals.
  - Settings: reminder preferences, privacy, data export, and account deletion.
- Sidebar profile block under the MediByte brand, with the navigation collapsed by default after login.
- JSON account export and password-confirmed account deletion.
- Privacy notice and DPA consent at registration.
- PWA manifest, install icons, service worker, and offline fallback.
- Demo data seeder with seven days of history for both demo accounts.

---

## Tech Stack

- PHP 8.3+ / Laravel 13
- MySQL
- Blade templates
- Vanilla JavaScript API client
- Laravel Sanctum
- PHPUnit feature tests

---

## Data Model Notes

- Profile fields (`sex`, `period_status`, goals, cycle length, reminder preference, onboarding timestamp) live directly on `users`; there is no separate `profiles` table.
- Water intake is stored in `daily_logs`; the older `water_logs` table is not used.
- Password reset tokens use Laravel's standard `password_reset_tokens` table.

---

## Setup

### 1. Install dependencies

```bash
composer install
```

### 2. Create the environment file

```bash
cp .env.example .env
php artisan key:generate
```

Set the MySQL connection in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=medibyte
DB_USERNAME=root
DB_PASSWORD=
```

Create the database if it does not exist:

```sql
CREATE DATABASE medibyte CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Run migrations and seed demo data

```bash
php artisan migrate --seed
```

### 4. Start the app

```bash
php artisan serve
```

Open `http://localhost:8000`.

### 5. Optional: run the reminder scheduler

The reminder command is scheduled every 15 minutes. For local testing:

```bash
php artisan schedule:work
```

In production, add the standard Laravel cron entry:

```cron
* * * * * cd /path/to/medibyte && php artisan schedule:run >> /dev/null 2>&1
```

---

## Demo Accounts

After `php artisan migrate --seed`:

| Account | Username | Email | Password |
|---|---|---|---|
| Female demo (cycle tracking enabled) | `demo_female` | `demo.female@medibyte.test` | `password` |
| Male demo (cycle tracking hidden) | `demo_male` | `demo.male@medibyte.test` | `password` |

The female demo includes seven days of water, sleep, walking, and mood history, plus goals, cycle history, a medication schedule, an appointment, a sample private document, and an unread notification. The male demo also has seven days of history so the health summary and 7-day chart can be reviewed without creating records.

Re-running the seeder updates the demo records without creating duplicate entries. Sign in again afterward because the demo password hash is refreshed.

---

## Testing

```bash
php artisan test
```

The test suite uses SQLite in-memory, so it does not touch the development MySQL database. Current coverage includes:

- Registration, login, web session auth, onboarding, and cycle gating.
- Dashboard health score, 7-day progress, cycle/mood summary, and metrics.
- Profile identity updates, including username normalization and uniqueness.
- BMI and progress calculations.
- Walking and sleep validation.
- Cycle prediction.
- Reminder preferences and notification scheduling.
- Document upload, download, ownership, validation, and deletion.
- Account export, deletion, and private-file cleanup.

---

## API

All application endpoints are under `/api/v1`.

Public:

- `POST /register`
- `POST /login`
- `POST /forgot-password`
- `POST /reset-password`
- `GET /username-available?username=...`

Authenticated:

- The web client uses an HttpOnly Laravel session cookie plus a CSRF header.
- API clients can use `Authorization: Bearer <token>`; login and registration return a Sanctum token for that purpose.

Routes:

- Account: `/user`, `/logout`, `/account/export`, `DELETE /account`
- Profile: `/profile`, `/profile/complete`
- Logs: `/water-logs`, `/sleep-logs`, `/walking-logs`, `/meal-logs`, `/weight-logs`, `/mood-logs`, `/symptom-logs`, `/vital-sign-logs`, `/period-logs`
- Care: `/medications`, `/appointments`, `/documents`
- Reminders: `/reminders`, `/notifications`
- Insights: `/progress`, `/dashboard`

Document files are downloaded through `GET /api/v1/documents/{id}/download` with authentication.

---

## Project Structure

```
app/
  Console/Commands/SendReminders.php   Reminder scheduler command
  Http/Controllers/                    API and account controllers
  Models/                              Eloquent models
  Notifications/HealthReminder.php     Database notification payload
database/
  migrations/                          Schema
  seeders/DemoDataSeeder.php           Demo accounts and data
docs/
  IMPLEMENTATION_PLAN.md               Current status and roadmap
  VALUE_ANALYSIS.md                    Feature-to-problem mapping
public/
  css/medibyte.css                     Mobile-first UI
  js/medibyte.js                       Shared API client and helpers
  manifest.webmanifest                 PWA metadata
  sw.js                                Service worker
  offline.html                         Offline fallback
  icons/                               PWA icons
resources/views/                       Blade pages
routes/
  api.php                              `/api/v1` routes
  web.php                              Page routes
  console.php                          Scheduler registration
tests/Feature/                         Feature tests
```

---

## Privacy and Security Notes

- Passwords are hashed by Laravel.
- Web sessions use HttpOnly cookies with CSRF protection; API clients can use Sanctum bearer tokens.
- Documents are stored on the private local disk and served only through authenticated routes.
- Users can export their data and delete their account from Settings.
- The privacy notice is available at `/privacy`.

### Known limitations before production

- Serve the app over HTTPS and set `SESSION_SECURE_COOKIE=true` in production.
- Set `APP_DEBUG=false` and configure real SMTP/queue services.
- Sensitive notes are stored in plaintext; field-level encryption is still planned.
- A strict Content-Security-Policy and an external XSS review are still recommended.
- There is no email verification, MFA, or audit log yet. Password reset is available by email.
- Notifications are in-app only; no push/email delivery is configured.
- The PWA caches the app shell but does not queue writes while offline.

See `docs/IMPLEMENTATION_PLAN.md` for the full roadmap.

---

## PWA

The app includes a web app manifest, generated 192 px and 512 px icons, a service worker, and an offline fallback page. Replace the generated demo icons in `public/icons/` with the final brand assets before deployment.

The service worker does not cache `/api/` responses, so health data always comes from the server.
