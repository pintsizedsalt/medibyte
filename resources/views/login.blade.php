@extends('layouts.app')

@section('title', 'Log in · MediByte')
@section('body-class', 'is-public')

@section('content')
  <div class="lg-page">
    <div class="lg-blob lg-blob-a" aria-hidden="true"></div>
    <div class="lg-blob lg-blob-b" aria-hidden="true"></div>

    <div class="lg-wrap">
      <!-- Left side -->
      <section class="lg-left">
        <a class="lg-brand" href="/">MediByte</a>
        <p class="lg-eyebrow">Personal health</p>
        <h1 class="lg-title">A clearer view of your everyday health.</h1>
        <svg class="lg-pulse" width="150" height="12" viewBox="0 0 150 12" aria-hidden="true">
          <path d="M2 7h38l6-5 8 10 7-8 6 3h81"/>
        </svg>
        <p class="lg-copy">
          Keep sleep, hydration, movement, symptoms, medications, appointments, and cycle tracking together without turning your day into a spreadsheet.
        </p>

        <div class="lg-stack" aria-hidden="true">
          <div class="lg-pc lg-pc-1">
            <div class="lg-ring"><span>
              <svg viewBox="0 0 24 24"><use href="#i-droplet"/></svg>
            </span></div>
            <div><strong>1,250 ml</strong><small>Water today</small></div>
          </div>
          <div class="lg-pc lg-pc-2">
            <div class="lg-ib">
              <svg viewBox="0 0 24 24"><use href="#i-moon"/></svg>
            </div>
            <div><strong>7h 45m</strong><small>Sleep last night</small></div>
          </div>
          <div class="lg-pc lg-pc-3">
            <div class="lg-ib">
              <svg viewBox="0 0 24 24"><use href="#i-pill"/></svg>
            </div>
            <div><strong>Vitamin D</strong><small>Reminder at 8:00 pm</small></div>
          </div>
        </div>

        <p class="lg-note">
          MediByte is a personal health journal, not a diagnosis. Read the <a href="/privacy">privacy notice</a>.
        </p>
      </section>

      <!-- Login card -->
      <section class="lg-card" aria-labelledby="lg_title">
        <div class="lg-logo" aria-hidden="true">
          <svg viewBox="0 0 24 24"><use href="#i-activity"/></svg>
        </div>
        <p class="lg-kicker">Welcome back</p>
        <h2 id="lg_title">Log in to MediByte</h2>
        <p class="lg-intro">Use your email address or username to continue.</p>

        <form id="loginForm" novalidate>
          <label class="lg-group">
            <span>Email or username</span>
            <div class="lg-input">
              <svg class="lg-ico" viewBox="0 0 24 24" aria-hidden="true"><use href="#i-user"/></svg>
              <input id="email" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="you@example.com or username">
            </div>
          </label>

          <label class="lg-group">
            <span>Password</span>
            <div class="lg-input">
              <svg class="lg-ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
              <input id="password" type="password" autocomplete="current-password" placeholder="Your password">
              <button type="button" id="lg_toggle" class="lg-toggle" aria-label="Show password" aria-pressed="false">
                <svg id="lg_eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="lg_eye_off" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="M3 3l18 18"/><path d="M10.6 6.1A10.8 10.8 0 0 1 12 6c6.4 0 10 6 10 6a17.6 17.6 0 0 1-3.2 3.9M6.6 6.6A17.4 17.4 0 0 0 2 12s3.6 6 10 6a10 10 0 0 0 4.4-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
              </button>
            </div>
          </label>

          <a class="lg-forgot" href="/forgot-password">Forgot password?</a>

          <p id="msg" class="field-error lg-error" role="alert"></p>

          <button type="submit" id="lg_submit" class="lg-submit">
            <span id="lg_submit_label">Log in</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#i-arrow"/></svg>
          </button>
        </form>

        <p class="lg-switch">New to MediByte? <a href="/register">Create an account</a></p>
      </section>
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function () {
  const form = $('loginForm');
  const idInput = $('email');
  const pw = $('password');
  const toggle = $('lg_toggle');
  const btn = $('lg_submit');
  const label = $('lg_submit_label');
  const general = $('msg');
  let busy = false;

  toggle.addEventListener('click', function () {
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(show));
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    $('lg_eye').hidden = show;
    $('lg_eye_off').hidden = !show;
  });

  [idInput, pw].forEach(function (el) {
    el.addEventListener('input', function () {
      clearFieldError(el);
      general.textContent = '';
    });
  });

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (busy) return;

    clearFieldError(idInput);
    clearFieldError(pw);
    general.textContent = '';

    const id = idInput.value.trim();
    const password = pw.value;

    if (!id) { showFieldError(idInput, 'Enter your email address or username.'); idInput.focus(); return; }
    if (!password) { showFieldError(pw, 'Enter your password.'); pw.focus(); return; }

    busy = true;
    btn.disabled = true;
    label.textContent = 'Logging in…';
    let redirecting = false;

    try {
      const data = await api('/login', 'POST', { email: id, password: password });

      if (data && data.user) {
        redirecting = true;
        const profile = await api('/profile');
        location.replace(profile && profile.id && !profile.onboarding_completed_at ? '/profile-setup' : '/dashboard');
        return;
      }

      general.textContent = errText(data);
    } catch (error) {
      general.textContent = 'Something went wrong. Please check your connection and try again.';
    } finally {
      if (!redirecting) {
        busy = false;
        btn.disabled = false;
        label.textContent = 'Log in';
      }
    }
  });
})();
</script>
@endpush