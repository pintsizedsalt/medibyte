@extends('layouts.app')

@section('title', 'Settings · MediByte')

@section('content')
  <div class="page-heading tone-slate">
    <div class="heading-main">
      <a class="back-link" href="/dashboard" aria-label="Back to dashboard">
        <svg class="icon"><use href="#i-chevron-left"/></svg>
      </a>
      <span class="heading-ic" aria-hidden="true"><svg class="icon"><use href="#i-settings"/></svg></span>
      <div>
        <p class="eyebrow">Your account</p>
        <h2>Settings</h2>
        <p>Notifications, privacy, and account controls.</p>
      </div>
    </div>
  </div>

  <p id="msg" class="form-message" role="alert"></p>

  <div class="settings-layout">
    <form id="settings-form" class="settings-stack" onsubmit="event.preventDefault(); saveSettings()">
      <section class="card reminder-panel">
        <div class="reminder-head">
          <span class="reminder-icon" aria-hidden="true"><svg class="icon"><use href="#i-bell"/></svg></span>
          <div class="reminder-head-text">
            <p class="eyebrow">Notifications</p>
            <h3>Reminders</h3>
            <p>Medication, appointment and check-in notices arrive in the notification inbox.</p>
          </div>
          <label class="switch-row switch-compact">
            <input type="checkbox" id="reminders_enabled" role="switch">
            <span class="switch-track" aria-hidden="true"></span>
            <span class="switch-label sr-only">Reminders</span>
          </label>
        </div>
      </section>

      <div class="reminder-grid">
      <section class="card reminder-panel tone-blue" data-reminder-card="appointment">
        <div class="reminder-head">
          <span class="reminder-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar"/></svg></span>
          <div class="reminder-head-text">
            <h3>Appointments</h3>
            <p>Remind me ahead of the date and time I saved.</p>
          </div>
          <label class="switch-row switch-compact">
            <input type="checkbox" id="appointment_reminders_enabled" role="switch">
            <span class="switch-track" aria-hidden="true"></span>
            <span class="switch-label sr-only">Appointment reminders</span>
          </label>
        </div>

        <div class="reminder-body">
          <div class="lead-group">
            <h4>Early heads up</h4>
            <div class="chip-grid">
              @foreach (\App\Models\User::APPOINTMENT_LEADS as $minutes => $label)
                @continue ($minutes < 1440)
                <label class="chip">
                  <input type="checkbox" class="appointment-lead lead-preset" value="{{ $minutes }}">
                  <span>{{ $label }}</span>
                </label>
              @endforeach
              <label class="chip">
                <input type="checkbox" id="appointment_day_notice" class="lead-preset">
                <span>Morning of the appointment</span>
              </label>
            </div>
          </div>

          <div class="lead-group">
            <h4>Countdown</h4>
            <div class="chip-grid">
              @foreach (\App\Models\User::APPOINTMENT_LEADS as $minutes => $label)
                @continue ($minutes >= 1440)
                <label class="chip">
                  <input type="checkbox" class="appointment-lead lead-preset" value="{{ $minutes }}">
                  <span>{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </div>

          @include('partials.custom-leads', [
            'feature' => 'appointment',
            'units' => ['minutes' => 1, 'hours' => 60, 'days' => 1440],
            'suffix' => 'before the appointment',
            'ceiling' => \App\Models\User::CUSTOM_LEAD_CEILINGS['appointment'],
          ])

          @include('partials.repeat-choice', [
            'feature' => 'appointment',
            'title' => 'Keep reminding until I open it',
            'hint' => 'Repeats the nearest notice until you open it.',
          ])

          <p class="reminder-summary" data-reminder-summary></p>
        </div>
      </section>

      <section class="card reminder-panel tone-plum" data-reminder-card="medication">
        <div class="reminder-head">
          <span class="reminder-icon" aria-hidden="true"><svg class="icon"><use href="#i-pill"/></svg></span>
          <div class="reminder-head-text">
            <h3>Medications</h3>
            <p>Remind me around each time I saved for a medication.</p>
          </div>
          <label class="switch-row switch-compact">
            <input type="checkbox" id="medication_reminders_enabled" role="switch">
            <span class="switch-track" aria-hidden="true"></span>
            <span class="switch-label sr-only">Medication reminders</span>
          </label>
        </div>

        <div class="reminder-body">
          <div class="lead-group">
            <h4>When to remind me</h4>
            <div class="chip-grid">
              @foreach (\App\Models\User::MEDICATION_LEADS as $minutes => $label)
                <label class="chip">
                  <input type="checkbox" class="medication-lead lead-preset" value="{{ $minutes }}">
                  <span>{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </div>

          @include('partials.custom-leads', [
            'feature' => 'medication',
            'units' => ['minutes' => 1, 'hours' => 60],
            'suffix' => 'before the scheduled time',
            'ceiling' => \App\Models\User::CUSTOM_LEAD_CEILINGS['medication'],
          ])

          @include('partials.repeat-choice', [
            'feature' => 'medication',
            'title' => 'Keep reminding until I open it',
            'hint' => 'Repeats the nearest notice until you open it.',
          ])

          <p class="reminder-summary" data-reminder-summary></p>
        </div>
      </section>

      @if (auth()->user()->show_period_tracker)
      <section class="card reminder-panel tone-rose" data-reminder-card="cycle">
        <div class="reminder-head">
          <span class="reminder-icon" aria-hidden="true"><svg class="icon"><use href="#i-cycle"/></svg></span>
          <div class="reminder-head-text">
            <h3>Cycle</h3>
            <p>Remind me around my predicted period and fertile window.</p>
          </div>
          <label class="switch-row switch-compact">
            <input type="checkbox" id="cycle_reminders_enabled" role="switch">
            <span class="switch-track" aria-hidden="true"></span>
            <span class="switch-label sr-only">Cycle reminders</span>
          </label>
        </div>

        <div class="reminder-body">
          <div class="lead-group">
            <h4>Period heads up</h4>
            <fieldset class="seg" id="cycle_lead_days">
              <legend class="sr-only">Days before your period</legend>
              @foreach (\App\Models\User::CYCLE_LEAD_DAYS as $days => $label)
                <label class="seg-item">
                  <input type="radio" name="cycle_lead" value="{{ $days }}">
                  <span>{{ $label }}</span>
                </label>
              @endforeach
            </fieldset>
          </div>

          <div class="lead-group">
            <label class="field">
              <span>Time of day</span>
              <input type="time" id="cycle_reminder_time" aria-label="Cycle reminder time">
            </label>
          </div>

          <div class="lead-group">
            <h4>Also remind me</h4>
            <div class="chip-grid">
              <label class="chip">
                <input type="checkbox" id="cycle_fertile_reminder">
                <span>Fertile window</span>
              </label>
              <label class="chip">
                <input type="checkbox" id="cycle_ovulation_reminder">
                <span>Ovulation day</span>
              </label>
              <label class="chip">
                <input type="checkbox" id="cycle_pms_reminder">
                <span>PMS window</span>
              </label>
              <label class="chip">
                <input type="checkbox" id="cycle_late_reminder">
                <span>Period is late</span>
              </label>
            </div>
          </div>

          <div class="lead-group">
            <h4>Daily and birth control</h4>
            <label class="switch-row">
              <input type="checkbox" id="cycle_daily_log_reminder" role="switch">
              <span class="switch-track" aria-hidden="true"></span>
              <span class="switch-label">Symptom-log nudge</span>
            </label>
            <div class="cycle-time-row" data-cycle-time="daily_log">
              <label class="field">
                <span>Nudge time</span>
                <input type="time" id="cycle_daily_log_time">
              </label>
            </div>
            <label class="switch-row">
              <input type="checkbox" id="cycle_pill_reminder" role="switch">
              <span class="switch-track" aria-hidden="true"></span>
              <span class="switch-label">Birth control pill</span>
            </label>
            <div class="cycle-time-row" data-cycle-time="pill">
              <label class="field">
                <span>Pill time</span>
                <input type="time" id="cycle_pill_time">
              </label>
            </div>
          </div>

          <div class="lead-group">
            <label class="switch-row">
              <input type="checkbox" id="cycle_discreet" role="switch">
              <span class="switch-track" aria-hidden="true"></span>
              <span class="switch-label">Discreet wording</span>
            </label>
            <p class="repeat-hint">Shows “Health reminder” instead of period details in the notification list.</p>
          </div>

          <p class="reminder-summary" data-reminder-summary></p>
        </div>
      </section>
      @endif
      </div>

      <p class="settings-note">Each notice is sent once, and a reminder you throw away does not come back.</p>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <svg class="icon"><use href="#i-check"/></svg>
          Save changes
        </button>
      </div>

      <section class="card">
        <div class="card-head">
          <div>
            <p class="eyebrow">Privacy and data</p>
            <h3>Your health data</h3>
          </div>
        </div>
        <p class="muted">Download a PDF summary of your last seven days — sleep, water, movement, meals, vitals, weight, symptoms, medications with each dose and its unit, appointments, mood, cycle, and documents — laid out for a doctor or nurse to read. A section with nothing logged that week is left out. Document files themselves can be downloaded from the Documents page.</p>
        <div class="form-actions">
          <button type="button" class="btn btn-primary" onclick="downloadWeeklyPdf()">
            <svg class="icon"><use href="#i-file"/></svg>
            Download weekly PDF
          </button>
          <a class="btn btn-ghost" href="/privacy">Privacy notice</a>
        </div>
        <div class="form-actions">
          <button type="button" class="text-button" onclick="downloadExport()">Download all my data (JSON)</button>
        </div>
      </section>

      <section class="card card-danger">
        <div class="card-head">
          <div>
            <p class="eyebrow">Danger zone</p>
            <h3>Delete account</h3>
          </div>
        </div>
        <p class="muted">This permanently deletes your account, health logs, reminders, and private documents. This action cannot be undone.</p>
        <label class="field">
          <span>Confirm your password</span>
          <input id="delete_password" type="password" autocomplete="current-password" placeholder="Enter your password">
        </label>
        <div class="form-actions">
          <button type="button" class="btn btn-danger" onclick="deleteAccount()">Delete my account</button>
        </div>
      </section>
    </form>

    <aside class="settings-side">
      <section class="card account-card">
        <span class="avatar {{ auth()->user()?->avatar_url ? 'has-photo' : '' }}" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()->first_name ?? auth()->user()->name ?? 'M', 0, 1)) }}</span>
        <h3>{{ auth()->user()->name ?? 'MediByte account' }}</h3>
        @if (auth()->user()->username)
          <p class="account-handle">{{ '@'.auth()->user()->username }}</p>
        @endif
        <p class="muted account-email">{{ auth()->user()->email ?? '' }}</p>
        <span class="badge">Private account</span>
        <div class="form-actions account-actions">
          <a class="btn btn-ghost" href="/profile">Open profile</a>
        </div>

        <div class="account-divider" aria-hidden="true"></div>
        <p class="account-eyebrow">Your daily goals</p>
        <ul class="account-goals">
          <li>
            <svg class="icon" aria-hidden="true"><use href="#i-droplet"/></svg>
            <span>Water</span>
            <strong id="goal-water">—</strong>
          </li>
          <li>
            <svg class="icon" aria-hidden="true"><use href="#i-moon"/></svg>
            <span>Sleep</span>
            <strong id="goal-sleep">—</strong>
          </li>
          <li>
            <svg class="icon" aria-hidden="true"><use href="#i-activity"/></svg>
            <span>Steps</span>
            <strong id="goal-steps">—</strong>
          </li>
          <li>
            <svg class="icon" aria-hidden="true"><use href="#i-meal"/></svg>
            <span>Calories</span>
            <strong id="goal-calories">—</strong>
          </li>
        </ul>
        <p id="account-since" class="muted account-since"></p>
      </section>
    </aside>
  </div>
@endsection

@push('scripts')
<script>
let currentProfile = null;

async function loadSettings() {
  currentProfile = await api('/profile');
  if (!currentProfile || !currentProfile.id) {
    $('msg').textContent = errText(currentProfile);
    return;
  }

  paintReminders(currentProfile);

  $('goal-water').textContent = currentProfile.water_goal_ml ? waterLabel(currentProfile.water_goal_ml) : '—';
  $('goal-sleep').textContent = currentProfile.sleep_goal_hours ? parseFloat(currentProfile.sleep_goal_hours) + 'h' : '—';
  $('goal-steps').textContent = currentProfile.walking_goal_steps ? compactNumber(currentProfile.walking_goal_steps) : '—';
  $('goal-calories').textContent = currentProfile.calorie_goal ? compactNumber(currentProfile.calorie_goal) + ' kcal' : '—';

  const since = $('account-since');
  if (since && currentProfile.created_at) {
    const created = new Date(currentProfile.created_at);
    if (!Number.isNaN(created.getTime())) {
      since.textContent = 'Member since ' + created.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    }
  }
}

// The server answers a reminder choice that was never made with what the account
// already had, so the form is painted from the settled preferences it returns
// rather than from the raw columns.
function paintReminders(profile) {
  const preferences = profile.reminder_preferences || {};
  const appointment = preferences.appointment || {};
  const medication = preferences.medication || {};

  $('reminders_enabled').checked = Boolean(profile.reminders_enabled);
  $('appointment_reminders_enabled').checked = appointment.enabled !== false;
  $('medication_reminders_enabled').checked = medication.enabled !== false;
  $('appointment_day_notice').checked = Boolean(appointment.day_notice);

  paintLeads('appointment', appointment.leads);
  paintLeads('medication', medication.leads);

  chooseRepeat('appointment_repeat_minutes', appointment.repeat_minutes);
  chooseRepeat('medication_repeat_minutes', medication.repeat_minutes);

  if (document.querySelector('[data-reminder-card="cycle"]')) {
    paintCycle(preferences.cycle || {});
  }

  syncReminders();
}

/* The cycle card only exists for accounts with the period tracker on, so its
   fields are painted from the settled preferences when the card is present. */
function paintCycle(cycle) {
  $('cycle_reminders_enabled').checked = cycle.enabled !== false;
  chooseCycleLead(cycle.lead_days);
  $('cycle_reminder_time').value = cycle.time || '08:00';
  $('cycle_fertile_reminder').checked = Boolean(cycle.fertile);
  $('cycle_ovulation_reminder').checked = Boolean(cycle.ovulation);
  $('cycle_pms_reminder').checked = Boolean(cycle.pms);
  $('cycle_late_reminder').checked = cycle.late !== false;
  $('cycle_daily_log_reminder').checked = Boolean(cycle.daily_log);
  $('cycle_daily_log_time').value = cycle.daily_log_time || '';
  $('cycle_pill_reminder').checked = Boolean(cycle.pill);
  $('cycle_pill_time').value = cycle.pill_time || '';
  $('cycle_discreet').checked = Boolean(cycle.discreet);
}

function chooseCycleLead(days) {
  const value = days ? String(days) : String(2);
  document.querySelectorAll('#cycle_lead_days input').forEach(input => { input.checked = input.value === value; });
}

function cycleLeadDays() {
  const checked = document.querySelector('#cycle_lead_days input:checked');
  return checked ? Number(checked.value) : 2;
}

/* A lead the person typed themselves is on no preset list, so anything the server
   returns that the page does not already offer comes back as a custom chip. */
function paintLeads(feature, leads) {
  const chosen = (Array.isArray(leads) ? leads : []).map(Number);
  const presets = Array.from(document.querySelectorAll('.' + feature + '-lead.lead-preset'));
  const offered = presets.map(input => Number(input.value));

  presets.forEach(input => { input.checked = chosen.includes(Number(input.value)); });

  const host = customHost(feature);
  host.innerHTML = '';
  chosen.filter(minutes => !offered.includes(minutes)).forEach(minutes => host.append(customChip(feature, minutes)));
}

function chosenLeads(className) {
  return Array.from(document.querySelectorAll('.' + className))
    .filter(input => input.checked)
    .map(input => Number(input.value));
}

function customGroup(feature) {
  return document.querySelector('[data-custom-leads="' + feature + '"]');
}

function customHost(feature) {
  return customGroup(feature).querySelector('.custom-chips');
}

function customChip(feature, minutes) {
  const label = document.createElement('label');
  label.className = 'chip chip-custom';
  label.innerHTML = `
    <input type="checkbox" class="${feature}-lead" value="${minutes}" checked>
    <span>${leadText(feature, minutes)}</span>
    <button type="button" class="chip-remove" data-custom-remove aria-label="Remove ${leadText(feature, minutes)}">&times;</button>`;
  return label;
}

function leadText(feature, minutes) {
  return amountText(minutes) + ' ' + customGroup(feature).dataset.suffix;
}

/* Read a custom amount in the largest unit that divides it exactly, so ninety
   minutes is "1 hour" territory rather than "90 minutes" on the chip. */
function amountText(minutes) {
  if (minutes >= 1440 && minutes % 1440 === 0) return countAs(minutes, 1440, 'day');
  if (minutes >= 60 && minutes % 60 === 0) return countAs(minutes, 60, 'hour');
  return countAs(minutes, 1, 'minute');
}

function countAs(total, size, noun) {
  const count = total / size;
  return count + ' ' + noun + (count === 1 ? '' : 's');
}

const CUSTOM_UNIT_MINUTES = { minutes: 1, hours: 60, days: 1440 };
const MAX_CUSTOM_LEADS = 5;

function bindCustomLeads() {
  document.querySelectorAll('[data-custom-leads]').forEach(group => {
    const form = group.querySelector('.custom-form');
    const amount = group.querySelector('[data-custom-amount]');
    const unit = group.querySelector('[data-custom-unit]');
    const error = group.querySelector('.custom-error');

    group.querySelector('[data-custom-add]').addEventListener('click', () => {
      error.textContent = '';
      form.hidden = false;
      amount.focus();
    });

    group.querySelector('[data-custom-cancel]').addEventListener('click', () => {
      error.textContent = '';
      form.hidden = true;
    });

    group.querySelector('[data-custom-confirm]').addEventListener('click', () => {
      const minutes = customLeadMinutes(group, error);

      if (minutes === null) {
        amount.focus();
        return;
      }

      customHost(group.dataset.customLeads).append(customChip(group.dataset.customLeads, minutes));
      form.hidden = true;
      amount.value = '15';
      syncReminders();
    });
  });

  $('settings-form').addEventListener('click', event => {
    const remove = event.target.closest('[data-custom-remove]');
    if (!remove) return;

    event.preventDefault();
    remove.closest('.chip').remove();
    syncReminders();
  });
}

function customLeadMinutes(group, error) {
  const feature = group.dataset.customLeads;
  const amount = group.querySelector('[data-custom-amount]');
  const unitName = group.querySelector('[data-custom-unit]').value;
  const value = Number.parseInt(amount.value, 10);

  if (!Number.isInteger(value) || value < 1 || value > 999) {
    error.textContent = 'Enter a whole number between 1 and 999.';
    return null;
  }

  const minutes = value * CUSTOM_UNIT_MINUTES[unitName];

  if (minutes > Number(group.dataset.ceiling)) {
    error.textContent = 'The furthest ahead this goes is ' + amountText(Number(group.dataset.ceiling)) + '.';
    return null;
  }

  if (customHost(feature).children.length >= MAX_CUSTOM_LEADS) {
    error.textContent = 'Five custom reminders is the most this account keeps.';
    return null;
  }

  const taken = Array.from(document.querySelectorAll('.' + feature + '-lead')).map(input => Number(input.value));

  if (taken.includes(minutes)) {
    error.textContent = 'You already have a reminder that far ahead.';
    return null;
  }

  error.textContent = '';
  return minutes;
}

/* A card whose switch is off — or whose inbox is off — is dimmed and its controls
   are disabled, so nothing can be set in a section that will not be sent. */
function syncReminders() {
  const inbox = $('reminders_enabled').checked;

  document.querySelectorAll('[data-reminder-card]').forEach(card => {
    const on = inbox && card.querySelector('.reminder-head input').checked;

    card.classList.toggle('is-muted', !on);
    card.querySelectorAll('.reminder-body input, .reminder-body select, .reminder-body button').forEach(control => {
      control.disabled = !on;
    });

    summarize(card);
  });
}

function summarize(card) {
  if (card.dataset.reminderCard === 'cycle') {
    summarizeCycle(card);
    return;
  }

  const summary = card.querySelector('[data-reminder-summary]');
  const picked = Array.from(card.querySelectorAll('.chip input:checked'))
    .map(input => input.closest('.chip').querySelector('span').textContent.trim());
  const repeat = card.querySelector('.seg input:checked');

  if (!picked.length) {
    summary.textContent = 'No reminders selected yet. Pick at least one time above.';
    return;
  }

  summary.textContent = picked.length + ' reminder' + (picked.length > 1 ? 's' : '') + ': ' + picked.join(', ') + '. '
    + (!repeat || repeat.value === ''
      ? 'Each one is sent once.'
      : 'Repeats ' + lowerFirst(repeat.closest('.seg-item').querySelector('span').textContent.trim()) + ' until you open it.');
}

/* The cycle card's summary is written from its own controls — a single lead-day
   choice, a time, and a handful of optional notices — rather than the chip-plus-
   repeat shape the appointment and medication cards share. */
function summarizeCycle(card) {
  const summary = card.querySelector('[data-reminder-summary]');
  const parts = [];

  const lead = document.querySelector('#cycle_lead_days input:checked');
  if (lead) {
    parts.push('a heads-up ' + lead.closest('.seg-item').querySelector('span').textContent.trim()
      + ' at ' + clockLabel($('cycle_reminder_time').value));
  }

  const extras = [];
  if ($('cycle_fertile_reminder').checked) extras.push('fertile window');
  if ($('cycle_ovulation_reminder').checked) extras.push('ovulation day');
  if ($('cycle_pms_reminder').checked) extras.push('PMS window');
  if ($('cycle_late_reminder').checked) extras.push('late period');
  if (extras.length) {
    parts.push(extras.join(', ') + ' notice' + (extras.length > 1 ? 's' : ''));
  }
  if ($('cycle_daily_log_reminder').checked) parts.push('a daily symptom-log nudge');
  if ($('cycle_pill_reminder').checked) parts.push('a birth-control pill reminder');

  summary.textContent = parts.length
    ? 'You get ' + parts.join(', ') + '.'
    : 'No cycle reminders selected yet. Turn on at least one above.';
}

/* An "08:00" time value reads back as a twelve-hour clock so the summary does not
   borrow the raw input's 24-hour format. */
function clockLabel(value) {
  if (!value || !/^([01]\d|2[0-3]):[0-5]\d$/.test(value)) return value || '08:00';
  const [h, m] = value.split(':').map(Number);
  const period = h < 12 ? 'am' : 'pm';
  const hour = h % 12 === 0 ? 12 : h % 12;
  return hour + (m ? ':' + String(m).padStart(2, '0') : '') + period;
}

function lowerFirst(text) {
  return text.charAt(0).toLowerCase() + text.slice(1);
}

function chooseRepeat(id, minutes) {
  const value = minutes ? String(minutes) : '';
  document.querySelectorAll('#' + id + ' input').forEach(input => { input.checked = input.value === value; });
}

function repeatChoice(id) {
  const checked = document.querySelector('#' + id + ' input:checked');
  return checked && checked.value !== '' ? Number(checked.value) : null;
}

async function saveSettings() {
  $('msg').textContent = 'Saving…';
  const body = {
    reminders_enabled: $('reminders_enabled').checked,
    appointment_reminders_enabled: $('appointment_reminders_enabled').checked,
    appointment_lead_minutes: chosenLeads('appointment-lead'),
    appointment_day_notice: $('appointment_day_notice').checked,
    appointment_repeat_minutes: repeatChoice('appointment_repeat_minutes'),
    medication_reminders_enabled: $('medication_reminders_enabled').checked,
    medication_lead_minutes: chosenLeads('medication-lead'),
    medication_repeat_minutes: repeatChoice('medication_repeat_minutes')
  };

  if (document.querySelector('[data-reminder-card="cycle"]')) {
    Object.assign(body, {
      cycle_reminders_enabled: $('cycle_reminders_enabled').checked,
      cycle_lead_days: cycleLeadDays(),
      cycle_reminder_time: $('cycle_reminder_time').value || '08:00',
      cycle_fertile_reminder: $('cycle_fertile_reminder').checked,
      cycle_ovulation_reminder: $('cycle_ovulation_reminder').checked,
      cycle_pms_reminder: $('cycle_pms_reminder').checked,
      cycle_late_reminder: $('cycle_late_reminder').checked,
      cycle_daily_log_reminder: $('cycle_daily_log_reminder').checked,
      cycle_daily_log_time: $('cycle_daily_log_reminder').checked ? ($('cycle_daily_log_time').value || null) : null,
      cycle_pill_reminder: $('cycle_pill_reminder').checked,
      cycle_pill_time: $('cycle_pill_reminder').checked ? ($('cycle_pill_time').value || null) : null,
      cycle_discreet: $('cycle_discreet').checked
    });
  }

  const result = await api('/profile', 'POST', body);

  if (result && result.id) {
    currentProfile = result;
    paintReminders(result);
    $('msg').textContent = '';
    toast('Your settings have been saved.');
  } else {
    $('msg').textContent = errText(result);
    toast(errText(result), 'error');
  }
}

async function downloadWeeklyPdf() {
  const response = await fetch('/api/v1/account/export/pdf', {
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    credentials: 'same-origin'
  });

  if (response.status === 401) {
    location.replace('/login');
    return;
  }

  if (!response.ok) {
    $('msg').textContent = 'Could not prepare your weekly PDF. Please try again.';
    toast('Could not prepare your weekly PDF. Please try again.', 'error');
    return;
  }

  const named = (response.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/);
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = named ? named[1] : 'medibyte-weekly-summary-' + today() + '.pdf';
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
  toast('Your weekly summary is downloading.');
}

async function downloadExport() {
  const response = await fetch('/api/v1/account/export', {
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    credentials: 'same-origin'
  });

  if (response.status === 401) {
    location.replace('/login');
    return;
  }

  if (!response.ok) {
    $('msg').textContent = 'Could not prepare your export. Please try again.';
    return;
  }

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'medibyte-export-' + today() + '.json';
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
  toast('Your data export is downloading.');
}

async function deleteAccount() {
  const password = $('delete_password').value;

  if (!password) {
    $('msg').textContent = 'Enter your password to delete your account.';
    return;
  }

  const ok = await confirmAction({
    title: 'Delete your account?',
    body: 'Your profile, health logs, reminders, and private documents will be removed permanently.',
    confirmLabel: 'Delete account'
  });
  if (!ok) return;

  $('msg').textContent = 'Deleting your account…';
  const result = await api('/account', 'DELETE', { password });

  if (result && result.message === 'Account deleted') {
    location.replace('/login');
  } else {
    $('msg').textContent = errText(result);
    toast(errText(result), 'error');
  }
}

$('settings-form').addEventListener('change', syncReminders);
bindCustomLeads();
loadSettings();
</script>
@endpush