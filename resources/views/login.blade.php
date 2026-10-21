@extends('layouts.app')

@section('title', 'Log in · MediByte')
@section('body-class', 'is-public')

@section('content')
  <div class="auth-page auth-split">
    @include('partials.auth-welcome')

    <section class="auth-card">
      <p class="eyebrow">MediByte</p>
      <h2>Log in to MediByte</h2>
      <p class="auth-intro">Use your email address or username to continue.</p>

      <p id="msg" class="form-message" role="alert"></p>

      <form onsubmit="event.preventDefault(); doLogin()">
        <label class="field">
          <span>Email or username</span>
          <input id="email" type="text" autocomplete="username" placeholder="you@example.com or username" required>
        </label>

        <label class="field">
          <span>Password</span>
          <span class="password-wrap">
            <input id="password" type="password" autocomplete="current-password" placeholder="Your password" required>
            <button type="button" class="password-toggle" onclick="togglePassword('password', this)">Show</button>
          </span>
        </label>

        <div class="auth-row">
          <a href="/forgot-password">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary auth-submit">Log in</button>
      </form>

      <p class="auth-switch">New to MediByte? <a href="/register">Create an account</a></p>
      <p class="auth-legal">Private by design. Read our <a href="/privacy">privacy notice</a>.</p>
    </section>
  </div>
@endsection

@push('scripts')
<script>
function togglePassword(id, button) {
  const input = $(id);
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  button.textContent = show ? 'Hide' : 'Show';
}

async function doLogin() {
  const data = await api('/login', 'POST', {
    email: $('email').value.trim(),
    password: $('password').value
  });

  if (data.user) {
    const profile = await api('/profile');
    location.replace(profile && profile.id && !profile.onboarding_completed_at ? '/profile-setup' : '/dashboard');
  } else {
    $('msg').textContent = errText(data);
  }
}
</script>
@endpush
