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
      <section class="card">
        <div class="card-head">
          <div>
            <p class="eyebrow">Notifications</p>
            <h3>Reminders</h3>
          </div>
        </div>
        <label class="check-row">
          <input id="reminders_enabled" type="checkbox">
          <span>Show medication, appointment, and symptom check-in reminders in the notification inbox.</span>
        </label>
        <p class="settings-note">Notifications are the single inbox for reminders and account updates.</p>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">
            <svg class="icon"><use href="#i-check"/></svg>
            Save changes
          </button>
        </div>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <p class="eyebrow">Privacy and data</p>
            <h3>Your health data</h3>
          </div>
        </div>
        <p class="muted">Download a JSON copy of your profile, logs, medications, appointments, and document details. Document files themselves can be downloaded from the Documents page.</p>
        <div class="form-actions">
          <button type="button" class="btn" onclick="downloadExport()">Download my data</button>
          <a class="btn btn-ghost" href="/privacy">Privacy notice</a>
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

  $('reminders_enabled').checked = Boolean(currentProfile.reminders_enabled);

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

async function saveSettings() {
  $('msg').textContent = 'Saving…';
  const result = await api('/profile', 'POST', {
    reminders_enabled: $('reminders_enabled').checked
  });

  if (result && result.id) {
    currentProfile = result;
    $('msg').textContent = '';
    toast('Your settings have been saved.');
  } else {
    $('msg').textContent = errText(result);
    toast(errText(result), 'error');
  }
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

loadSettings();
</script>
@endpush