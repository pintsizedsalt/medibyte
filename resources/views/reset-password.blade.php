@extends('layouts.app')

@section('title', 'Reset password · MediByte')
@section('body-class', 'is-public')

@section('content')
  <div class="auth-page">
    <section class="auth-card">
      <p class="eyebrow">Account help</p>
      <h2>Choose a new password</h2>
      <p class="auth-intro">Enter a new password for your MediByte account.</p>

      <p id="msg" class="form-message" role="alert"></p>

      <form id="reset-form" onsubmit="event.preventDefault(); resetPassword()">
        <label class="field">
          <span>Email address</span>
          <input id="email" type="email" autocomplete="email" value="{{ request('email') }}" required>
        </label>

        <label class="field">
          <span>New password</span>
          <input id="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8" required>
        </label>

        <label class="field">
          <span>Confirm new password</span>
          <input id="password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat your new password" minlength="8" required>
        </label>

        <button type="submit" class="btn btn-primary auth-submit">Reset password</button>
      </form>

      <p class="auth-switch"><a href="/login">Back to login</a></p>
    </section>
  </div>
@endsection

@push('scripts')
<script>
const RESET_TOKEN = @json($token);

async function resetPassword() {
  $('msg').textContent = 'Saving…';
  const data = await api('/reset-password', 'POST', {
    token: RESET_TOKEN,
    email: $('email').value.trim(),
    password: $('password').value,
    password_confirmation: $('password_confirmation').value
  });

  if (data && data.message && !data.errors) {
    $('reset-form').hidden = true;
    $('msg').textContent = data.message;
    const link = document.createElement('p');
    link.className = 'auth-switch';
    link.innerHTML = '<a href="/login">Continue to login</a>';
    $('reset-form').after(link);
  } else {
    $('msg').textContent = errText(data);
  }
}
</script>
@endpush
