const $ = id => document.getElementById(id);
const publicPages = ['/login', '/register'];

if (!localStorage.getItem('token') && !publicPages.includes(location.pathname)) {
  location.href = '/login';
}

async function api(url, method = 'GET', body) {
  const token = localStorage.getItem('token');
  const res = await fetch('/api' + url, {
    method,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + token
    },
    body: body ? JSON.stringify(body) : undefined
  });
  if (res.status === 401 && !publicPages.includes(location.pathname)) {
    localStorage.removeItem('token');
    location.href = '/login';
  }
  return res.json();
}

function errText(data) {
  return data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Something went wrong.');
}

async function logout() {
  await api('/logout', 'POST');
  localStorage.removeItem('token');
  location.href = '/login';
}

document.addEventListener('DOMContentLoaded', async () => {
  if (publicPages.includes(location.pathname)) { $('nav').style.display = 'none'; return; }
  const p = await api('/profile');
  if (p && p.show_period_tracker) $('periodLink').style.display = 'inline';
});

function ld(v) {
  if (!v) return '';
  const d = new Date(v), p = n => String(n).padStart(2, '0');
  return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
}
function ldt(v) {
  if (!v) return '';
  const d = new Date(v), p = n => String(n).padStart(2, '0');
  return ld(v) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
}

function logPage(cfg) {
  let rows = [], edit = null;
  const msg = t => $('msg').textContent = t || '';

  function input([n, label, type, opts, def], v) {
    if (v === undefined || v === null) v = def;
    const id = 'f_' + n;
    if (type === 'check') return `<label><input type="checkbox" id="${id}" ${v ? 'checked' : ''}> ${label}</label><br>`;
    if (type === 'select') return `<label>${label} <select id="${id}">${opts.map(o => `<option ${String(o) === String(v) ? 'selected' : ''}>${o}</option>`).join('')}</select></label><br>`;
    if (type === 'list') v = (v || []).join(', ');
    if (type === 'today') v = v ? ld(v) : ld(new Date());
    if (type === 'date') v = ld(v);
    if (type === 'datetime-local') v = ldt(v);
    const htmlType = type === 'list' ? 'text' : type === 'today' ? 'date' : type;
    return `<label>${label} <input id="${id}" type="${htmlType}" step="any" value="${v ?? ''}"></label><br>`;
  }

  function draw() {
    const e = edit || {};
    $('log').innerHTML =
      `<h3>${edit ? 'Edit' : 'Add'}</h3>` +
      cfg.fields.map(f => input(f, e[f[0]])).join('') +
      `<button onclick="save()">${edit ? 'Save changes' : 'Add'}</button>` +
      (edit ? ' <button onclick="cancel()">Cancel</button>' : '') +
      `<h3>Records</h3>` +
      (rows.map(r => `<p>${cfg.show(r)} <button onclick="editRow(${r.id})">Edit</button> <button onclick="del(${r.id})">Delete</button></p>`).join('') || '<p>No records yet.</p>');
  }

  async function load() { rows = await api(cfg.url); draw(); }

  window.save = async () => {
    const body = {};
    cfg.fields.forEach(([n, , t]) => {
      const el = $('f_' + n);
      body[n] = t === 'check' ? el.checked
        : t === 'list' ? el.value.split(',').map(s => s.trim()).filter(Boolean)
        : el.value;
    });
    const data = await api(cfg.url + (edit ? '/' + edit.id : ''), edit ? 'PUT' : 'POST', body);
    if (data.id) { edit = null; msg('Saved.'); load(); } else msg(errText(data));
  };
  window.editRow = id => { edit = rows.find(r => r.id === id); draw(); window.scrollTo(0, 0); };
  window.cancel = () => { edit = null; draw(); };
  window.del = async id => {
    if (!confirm('Delete this record?')) return;
    await api(cfg.url + '/' + id, 'DELETE');
    msg('Deleted.'); load();
  };

  load();
}