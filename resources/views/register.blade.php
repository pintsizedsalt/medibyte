@extends('layouts.app')

@section('content')
  <h2>Create account</h2>
  <p id="msg"></p>
  <input id="name" placeholder="name">
  <input id="email" placeholder="email">
  <input id="password" type="password" placeholder="password (8+ characters)">
  <label>I am a
    <select id="sex">
      <option value="female">Girl</option>
      <option value="male">Boy</option>
    </select>
  </label>
  <label>
    <input type="checkbox" id="consent">
    I agree to the collection and storage of my health data (Data Privacy Act of 2012)
  </label>
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
    sex: $('sex').value,
    consent: $('consent').checked
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