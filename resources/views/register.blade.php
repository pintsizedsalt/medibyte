@extends('layouts.app')

@section('content')
  <h2>Create account</h2>
  <p id="msg"></p>
  <input id="name" placeholder="name">
  <input id="email" placeholder="email">
  <input id="password" type="password" placeholder="password">
  <input id="password_confirmation" type="password" placeholder="confirm password">
  <button onclick="doRegister()">Register</button>
  <p><a href="/login">I already have an account</a></p>
@endsection

@push('scripts')
<script>
async function doRegister() {
  const data = await api('/register', 'POST', {
    name: $('name').value,
    email: $('email').value,
    password: $('password').value,
    password_confirmation: $('password_confirmation').value
  });
  if (data.token) {
    localStorage.setItem('token', data.token);
    location.href = '/profile-setup';
  } else {
    $('msg').textContent = errText(data);
  }
}
</script>
@endpush