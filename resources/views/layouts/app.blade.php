<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MediByte</title>
</head>
<body>
  <nav id="nav">
    <a href="/dashboard">Dashboard</a> |
    <a href="/profile-setup">Profile</a> |
    <a href="/water">Water</a> |
    <a href="/sleep">Sleep</a> |
    <a href="/walking">Walking</a> |
    <a href="/meals">Meals</a> |
    <a href="/weight">Weight</a> |
    <a href="/mood">Mood</a> |
    <a href="/symptoms">Symptoms</a> |
    <a href="/vitals">Vitals</a> |
    <a href="/medications">Medications</a> |
    <a href="/appointments">Appointments</a>
    <span id="periodLink" style="display:none"> | <a href="/period">Period</a></span> |
    <a href="#" onclick="logout()">Log out</a>
  </nav>

  @yield('content')

  <script src="/js/medibyte.js"></script>
  @stack('scripts')
</body>
</html>