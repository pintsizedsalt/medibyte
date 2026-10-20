<h2>MediByte Test</h2>

<input id="email" placeholder="email">
<input id="password" type="password" placeholder="password">
<button onclick="login()">Login</button>

<pre id="output"></pre>

<script>
async function login() {
  const res = await fetch('/api/login', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
    body: JSON.stringify({
      email: document.getElementById('email').value,
      password: document.getElementById('password').value
    })
  });
  const data = await res.json();
  if (data.token) localStorage.setItem('token', data.token);
  document.getElementById('output').textContent = JSON.stringify(data, null, 2);
}
</script>
