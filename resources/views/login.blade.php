@extends('layouts.app')

@section('content')
  <h2>Log in</h2>
  <p id="msg"></p>
  <input id="email" placeholder="email">
  <input id="password" type="password" placeholder="password">
  <button onclick="doLogin()">Log in</button>
  <p><a href="/register">Create an account</a></p>
@endsection

@push('scripts')
<script>
async function doLogin() {
  const data = await api('/login', 'POST', {
    email: $('email').value,
    password: $('password').value
  });
  if (data.token) {
    localStorage.setItem('token', data.token);
    location.href = '/dashboard';
  } else {
    $('msg').textContent = errText(data);
  }
}
</script>
@endpush