@extends('layouts.app')

@section('title', 'Profile · MediByte')

@section('content')
  <div class="page-heading">
    <div class="heading-main">
      <a class="back-link" href="/dashboard" aria-label="Back to dashboard">
        <svg class="icon"><use href="#i-chevron-left"/></svg>
      </a>
      <div>
        <p class="eyebrow">Your account</p>
        <h2>Profile</h2>
        <p>Your account details, cycle tracking, and daily targets.</p>
      </div>
    </div>
  </div>

  <p id="msg" class="form-message" role="alert"></p>

  <div class="settings-layout">
    <form id="profile-form" class="settings-stack" onsubmit="event.preventDefault(); saveProfile()">
      <section class="card">
        <div class="card-head">
          <div>
            <p class="eyebrow">Account</p>
            <h3>Profile and goals</h3>
          </div>
        </div>

        <div class="form-grid">
          <label class="field">
            <span>First name</span>
            <input id="first_name" type="text" autocomplete="given-name" maxlength="80" required>
          </label>

          <label class="field">
            <span>Last name</span>
            <input id="last_name" type="text" autocomplete="family-name" maxlength="80" required>
          </label>

          <label class="field">
            <span>Username</span>
            <input id="username" type="text" autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" required>
          </label>

          <label class="field">
            <span>Email address</span>
           <input
              id="email"
              type="email"
              name="email"
              autocomplete="email"
              maxlength="255"
              required
            >

    @error('email')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror
        </label>

        </div>

        <p class="settings-note">Usernames are unique and can contain letters, numbers, and underscores only.</p>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <p class="eyebrow">Health profile</p>
            <h3>Cycle tracking</h3>
          </div>
        </div>

        <div class="form-grid">
          <label class="field">
            <span>Sex</span>
            <select id="sex" onchange="toggleCycleSettings()">
              <option value="female">Female</option>
              <option value="male">Male</option>
            </select>
          </label>

          <label class="field" id="period-status-field">
            <span>Menstrual cycle</span>
            <select id="period_status" onchange="toggleCycleSettings()">
              <option value="active">Yes, I currently have a cycle</option>
              <option value="not_yet">Not yet</option>
              <option value="menopause">No, I am in menopause</option>
              <option value="prefer_not_to_track">I prefer not to track</option>
            </select>
          </label>

          <label class="field" id="cycle-length-field">
            <span>Typical cycle length (days)</span>
            <input id="cycle_length" type="number" min="20" max="45" value="28">
          </label>
        </div>

        <p class="settings-note">Cycle tracking is only available when sex is set to female and the cycle status is active.</p>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <p class="eyebrow">Daily targets</p>
            <h3>Your goals</h3>
          </div>
        </div>

        <div class="form-grid">
          <label class="field">
            <span>Water goal (ml)</span>
            <input id="water_goal_ml" type="number" min="500" step="100" value="2000">
          </label>
          <label class="field">
            <span>Sleep goal (hours)</span>
            <input id="sleep_goal_hours" type="number" min="1" max="16" step="0.5" value="8">
          </label>
          <label class="field">
            <span>Daily steps goal</span>
            <input id="walking_goal_steps" type="number" min="0" step="500" value="8000">
          </label>
          <label class="field">
            <span>Daily calorie goal (optional)</span>
            <input id="calorie_goal" type="number" min="500" placeholder="e.g. 2000">
          </label>
          <label class="field">
            <span>Goal weight (kg, optional)</span>
            <input id="weight_goal" type="number" min="20" max="400" step="0.1" placeholder="e.g. 60">
          </label>
        </div>
      </section>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <svg class="icon"><use href="#i-check"/></svg>
          Save changes
        </button>
        <a class="btn btn-ghost" href="/dashboard">Back to dashboard</a>
      </div>
    </form>

    <aside class="settings-side">
      <section class="card account-card">
        <span class="avatar" id="profileCardAvatar" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()->first_name ?? auth()->user()->name ?? 'M', 0, 1)) }}</span>
        <h3 id="profileCardName">{{ auth()->user()->name ?? 'MediByte account' }}</h3>
        <p class="muted" id="profileCardHandle">{{ auth()->user()->username ? '@'.auth()->user()->username : 'Private account' }}</p>
        <p class="muted" id="profileCardEmail">{{ auth()->user()->email ?? '' }}</p>
        <span class="badge">Private account</span>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <p class="eyebrow">Privacy</p>
            <h3>Your data</h3>
          </div>
        </div>
        <p class="muted">Your health details are private to your account. You can review the privacy notice or manage notifications and data controls in Settings.</p>
        <div class="form-actions">
          <a class="btn btn-ghost" href="/settings">Open settings</a>
          <a class="btn btn-ghost" href="/privacy">Privacy notice</a>
        </div>
      </section>
    </aside>
  </div>
@endsection

@push('scripts')
<script>
let currentProfile = null;

function toggleCycleSettings() {
  const female = $('sex').value === 'female';
  const active = $('period_status').value === 'active';

  $('period-status-field').style.display = female ? '' : 'none';
  $('cycle-length-field').style.display = female && active ? '' : 'none';
}

function updateProfileChrome(profile) {
  const name = profile.name || [profile.first_name, profile.last_name].filter(Boolean).join(' ') || 'MediByte';
  const initial = (profile.first_name || name).trim().charAt(0).toUpperCase() || 'M';
  const handle = profile.username ? '@' + profile.username : 'Private account';

  if ($('sideUserName')) $('sideUserName').textContent = name;
  if ($('sideUserHandle')) $('sideUserHandle').textContent = handle;
  if ($('sideUserAvatar')) $('sideUserAvatar').textContent = initial;
  if ($('topbarAvatar')) $('topbarAvatar').textContent = initial;
  if ($('profileCardName')) $('profileCardName').textContent = name;
  if ($('profileCardHandle')) $('profileCardHandle').textContent = handle;
  if ($('profileCardEmail')) $('profileCardEmail').textContent = profile.email || '';
  if ($('profileCardAvatar')) $('profileCardAvatar').textContent = initial;
}

async function loadProfile() {
  currentProfile = await api('/profile');
  if (!currentProfile || !currentProfile.id) {
    $('msg').textContent = errText(currentProfile);
    return;
  }

  $('first_name').value = currentProfile.first_name || '';
  $('last_name').value = currentProfile.last_name || '';
  $('username').value = currentProfile.username || '';
  $('email').value = currentProfile.email || '';
  $('sex').value = currentProfile.sex || 'female';
  $('period_status').value = currentProfile.period_status || 'prefer_not_to_track';
  $('cycle_length').value = currentProfile.cycle_length || 28;
  $('water_goal_ml').value = currentProfile.water_goal_ml || 2000;
  $('sleep_goal_hours').value = currentProfile.sleep_goal_hours || 8;
  $('walking_goal_steps').value = currentProfile.walking_goal_steps || 8000;
  $('calorie_goal').value = currentProfile.calorie_goal || '';
  $('weight_goal').value = currentProfile.weight_goal || '';

  toggleCycleSettings();
  updateProfileChrome(currentProfile);
}

async function saveProfile() {
  const female = $('sex').value === 'female';

  const body = {
    first_name: $('first_name').value.trim(),
    last_name: $('last_name').value.trim(),
    username: $('username').value.trim(),
    email: $('email').value.trim(),
    sex: $('sex').value,
    period_status: female ? $('period_status').value : null,
    cycle_length: $('cycle_length').value || 28,
    water_goal_ml: $('water_goal_ml').value,
    sleep_goal_hours: $('sleep_goal_hours').value,
    walking_goal_steps: $('walking_goal_steps').value,
    calorie_goal: $('calorie_goal').value || null,
    weight_goal: $('weight_goal').value || null
  };

  $('msg').textContent = 'Saving…';
  const result = await api('/profile', 'POST', body);

  if (result && result.id) {
    currentProfile = result;
    $('msg').textContent = '';
    updateProfileChrome(result);
    toast('Your profile has been saved.');
  } else {
    $('msg').textContent = errText(result);
    toast(errText(result), 'error');
  }
}

loadProfile();
</script>
@endpush
