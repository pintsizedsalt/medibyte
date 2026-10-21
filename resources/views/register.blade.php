@extends('layouts.app')

@section('title', 'Create account · MediByte')
@section('body-class', 'is-public')

@section('content')
  <div class="auth-page auth-split">
    @include('partials.auth-welcome')

    <section class="auth-card">
      <p class="eyebrow">MediByte</p>
      <h2>Create your MediByte account</h2>
      <p class="auth-intro">Use your real name and a unique username. You can fine-tune the rest during setup.</p>

      <p id="msg" class="form-message" role="alert"></p>

      <form onsubmit="event.preventDefault(); doRegister()">
        <div class="form-grid">
          <label class="field">
            <span>First name</span>
            <input id="first_name" type="text" autocomplete="given-name" placeholder="First name" maxlength="80" required>
          </label>

          <label class="field">
            <span>Last name</span>
            <input id="last_name" type="text" autocomplete="family-name" placeholder="Last name" maxlength="80" required>
          </label>
        </div>

        <label class="field">
          <span>Username</span>
          <input id="username" type="text" autocomplete="username" placeholder="letters_numbers_only" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" required oninput="queueUsernameCheck()">
          <small class="form-note" id="username-status">Letters, numbers, and underscores only. Usernames are unique.</small>
        </label>

        <label class="field">
          <span>Email address</span>
          <input id="email" type="email" autocomplete="email" placeholder="you@example.com" required>
        </label>

        <label class="field">
          <span>Password</span>
          <span class="password-wrap">
            <input id="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8" required>
            <button type="button" class="password-toggle" onclick="togglePassword('password', this)">Show</button>
          </span>
        </label>

        <div class="field">
          <span>I am a</span>
          <div class="segmented" role="group" aria-label="Sex">
            <button type="button" class="segment active" data-value="female" onclick="selectSex('female')">Female</button>
            <button type="button" class="segment" data-value="male" onclick="selectSex('male')">Male</button>
          </div>
          <input id="sex" type="hidden" value="female">
          <p class="form-note">This is used only to personalize cycle tracking. You can update it later in your profile.</p>
        </div>

        <label class="check-row check-row-spaced">
          <input type="checkbox" id="consent" required>
          <span>I agree to the collection and storage of my health data under the Data Privacy Act of 2012. Read the <a href="/privacy" target="_blank" rel="noopener">privacy notice</a>.</span>
        </label>

        <button type="submit" class="btn btn-primary auth-submit">Create account</button>
      </form>

      <p class="auth-switch">Already have an account? <a href="/login">Log in</a></p>
    </section>
  </div>
@endsection

@push('scripts')
<script>
let usernameTimer = null;

function togglePassword(id, button) {
  const input = $(id);
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  button.textContent = show ? 'Hide' : 'Show';
}

function selectSex(value) {
  $('sex').value = value;
  document.querySelectorAll('.segment').forEach(segment => {
    segment.classList.toggle('active', segment.dataset.value === value);
  });
}

function queueUsernameCheck() {
  clearTimeout(usernameTimer);
  const username = $('username').value.trim();
  const status = $('username-status');

  if (username.length < 3 || !/^[A-Za-z0-9_]+$/.test(username)) {
    status.textContent = 'Letters, numbers, and underscores only. At least 3 characters.';
    status.className = 'form-note';
    return;
  }

  status.textContent = 'Checking username…';
  status.className = 'form-note';
  usernameTimer = setTimeout(async () => {
    const result = await api('/username-available?username=' + encodeURIComponent(username));
    if (result && typeof result.available === 'boolean') {
      status.textContent = result.message;
      status.className = result.available ? 'form-note' : 'form-note form-error';
    }
  }, 350);
}

async function doRegister() {
  const data = await api('/register', 'POST', {
    first_name: $('first_name').value.trim(),
    last_name: $('last_name').value.trim(),
    username: $('username').value.trim(),
    email: $('email').value.trim(),
    password: $('password').value,
    sex: $('sex').value,
    consent: $('consent').checked
  });

  if (data.user) {
    location.replace('/profile-setup');
  } else {
    $('msg').textContent = errText(data);
  }
}
</script>
@endpush
