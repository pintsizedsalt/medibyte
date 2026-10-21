@extends('layouts.app')

@section('title', 'Forgot password · MediByte')
@section('body-class', 'is-public')

@section('content')
  <div class="auth-page">
    <section class="auth-card">
      <p class="eyebrow">Account help</p>
      <h2>Forgot your password?</h2>
      <p class="auth-intro">Enter the email address on your account and we will send a reset link.</p>

      <p id="msg" class="form-message" role="alert"></p>

      <form onsubmit="event.preventDefault(); requestReset()">
        <label class="field">
          <span>Email address</span>
          <input id="email" type="email" autocomplete="email" placeholder="you@example.com" required>
        </label>

        <button type="submit" class="btn btn-primary auth-submit">Send reset link</button>
      </form>

      <p class="auth-switch"><a href="/login">Back to login</a></p>
    </section>
  </div>
@endsection

@push('scripts')
<script>
async function requestReset() {
  $('msg').textContent = 'Sending…';
  const data = await api('/forgot-password', 'POST', { email: $('email').value.trim() });

  if (data && data.message) {
    $('msg').textContent = data.message;
  } else {
    $('msg').textContent = errText(data);
  }
}
</script>
@endpush
