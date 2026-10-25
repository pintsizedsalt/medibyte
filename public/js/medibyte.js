const $ = id => document.getElementById(id);
const publicPages = ['/login', '/register', '/privacy', '/forgot-password'];
const isPublicPage = () => publicPages.includes(location.pathname) || location.pathname.startsWith('/reset-password/');
const API_BASE = '/api/v1';

// Clear bearer tokens left by the earlier client. Web requests now use the
// HttpOnly Laravel session cookie and a CSRF header.
localStorage.removeItem('token');

/* ---------------------------------------------------------
   Small utilities
   --------------------------------------------------------- */
function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[c]));
}

async function api(url, method = 'GET', body) {
  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  };

  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
  }

  const options = { method, headers, credentials: 'same-origin' };
  if (body instanceof FormData) {
    options.body = body;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(API_BASE + url, options);
  } catch (error) {
    return { message: 'Network error. Please check your connection and try again.' };
  }

  if (res.status === 401 && !isPublicPage()) {
    location.replace('/login');
  }

  const text = await res.text();
  if (!text) return {};
  try {
    return JSON.parse(text);
  } catch (error) {
    return { message: text };
  }
}

function errText(data) {
  if (data && data.errors) {
    return Object.values(data.errors).flat().join(' ');
  }
  return (data && data.message) || 'Something went wrong. Please try again.';
}

async function logout() {
  closeAccountMenu();
  try {
    sessionStorage.removeItem('medibyte-sidebar');
  } catch (error) {}
  await api('/logout', 'POST');
  location.replace('/login');
}

function ld(v) {
  if (!v) return '';
  if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return '';
  const p = n => String(n).padStart(2, '0');
  return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
}

function lt(v) {
  if (!v) return '';
  if (typeof v === 'string' && /^\d{2}:\d{2}(:\d{2})?$/.test(v)) return v.slice(0, 5);
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return '';
  const p = n => String(n).padStart(2, '0');
  return p(d.getHours()) + ':' + p(d.getMinutes());
}

function ldt(v) {
  if (!v) return '';
  return ld(v) + 'T' + lt(v);
}

function today() {
  return ld(new Date());
}

function dateLabel(value) {
  if (!value) return '';
  const raw = typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)
    ? value + 'T00:00:00'
    : value;
  const d = new Date(raw);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function dateTimeLabel(value) {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });
}

function relativeTime(value) {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  const seconds = Math.round((d.getTime() - Date.now()) / 1000);
  const ranges = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60]
  ];
  const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
  for (const [unit, size] of ranges) {
    if (Math.abs(seconds) >= size || unit === 'minute') {
      return formatter.format(Math.round(seconds / size), unit);
    }
  }
  return 'just now';
}

function waterLabel(ml) {
  const value = Number(ml || 0);
  return value >= 1000 ? (value / 1000).toFixed(value % 1000 === 0 ? 0 : 1) + ' L' : value + ' ml';
}

function compactNumber(value) {
  const n = Number(value || 0);
  if (n >= 1000000) return (n / 1000000).toFixed(n % 1000000 === 0 ? 0 : 1) + 'm';
  if (n >= 1000) return (n / 1000).toFixed(n % 1000 === 0 ? 0 : 1) + 'k';
  return String(n);
}

/* ---------------------------------------------------------
   Sidebar, account menu, drawer, sheet, confirm dialog, toast
   --------------------------------------------------------- */
function isDesktop() {
  return window.matchMedia('(min-width: 901px)').matches;
}

function setDesktopSidebarCollapsed(collapsed) {
  document.body.classList.toggle('sidebar-collapsed', collapsed);
  try {
    sessionStorage.setItem('medibyte-sidebar', collapsed ? 'hidden' : 'visible');
  } catch (error) {}
}

function toggleSidebar(force) {
  closeAccountMenu();

  if (isDesktop()) {
    const collapsed = typeof force === 'boolean'
      ? !force
      : !document.body.classList.contains('sidebar-collapsed');
    setDesktopSidebarCollapsed(collapsed);
    document.body.classList.remove('sidebar-open', 'no-scroll');
    document.querySelector('.menu-btn')?.setAttribute('aria-expanded', String(!collapsed));
    return;
  }

  const open = typeof force === 'boolean' ? force : !document.body.classList.contains('sidebar-open');
  document.body.classList.toggle('sidebar-open', open);
  document.body.classList.toggle('no-scroll', open);
  document.querySelector('.menu-btn')?.setAttribute('aria-expanded', String(open));
}

function closeSidebar() {
  if (isDesktop()) {
    setDesktopSidebarCollapsed(true);
    document.body.classList.remove('sidebar-open', 'no-scroll');
    document.querySelector('.menu-btn')?.setAttribute('aria-expanded', 'false');
    return;
  }

  document.body.classList.remove('sidebar-open', 'no-scroll');
  document.querySelector('.menu-btn')?.setAttribute('aria-expanded', 'false');
}

function openAccountMenu() {
  const dropdown = $('accountDropdown');
  const button = document.querySelector('.avatar-button');
  if (!dropdown || !button) return;

  dropdown.hidden = false;
  button.setAttribute('aria-expanded', 'true');
}

function closeAccountMenu() {
  const dropdown = $('accountDropdown');
  const button = document.querySelector('.avatar-button');
  if (!dropdown) return;

  dropdown.hidden = true;
  button?.setAttribute('aria-expanded', 'false');
}

function toggleAccountMenu(event) {
  event?.stopPropagation();
  const dropdown = $('accountDropdown');
  if (!dropdown) return;
  dropdown.hidden ? openAccountMenu() : closeAccountMenu();
}

function openNotificationDrawer() {
  const drawer = $('notificationDrawer');
  if (!drawer) return;
  drawer.classList.add('open');
  drawer.setAttribute('aria-hidden', 'false');
  document.body.classList.add('drawer-open', 'no-scroll');
  renderNotificationDrawer();
  refreshNotificationBadge();
  const close = drawer.querySelector('.drawer-head .icon-btn');
  if (close) close.focus({ preventScroll: true });
}

function closeNotificationDrawer() {
  const drawer = $('notificationDrawer');
  if (!drawer) return;
  drawer.classList.remove('open');
  drawer.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('drawer-open', 'no-scroll');
}

function openQuickSheet() {
  const sheet = $('quickSheet');
  if (!sheet) return;
  sheet.classList.add('open');
  sheet.setAttribute('aria-hidden', 'false');
  document.body.classList.add('no-scroll');
  const first = sheet.querySelector('.quick-action');
  if (first) first.focus({ preventScroll: true });
}

function closeQuickSheet() {
  const sheet = $('quickSheet');
  if (!sheet) return;
  sheet.classList.remove('open');
  sheet.setAttribute('aria-hidden', 'true');
  if (!document.body.classList.contains('drawer-open')) {
    document.body.classList.remove('no-scroll');
  }
}

let confirmResolver = null;

function confirmAction(options = {}) {
  const modal = $('confirmModal');
  if (!modal) return Promise.resolve(window.confirm(options.body || 'Are you sure?'));

  $('confirmTitle').textContent = options.title || 'Are you sure?';
  $('confirmBody').textContent = options.body || 'This action cannot be undone.';
  $('confirmCancel').textContent = options.cancelLabel || 'Cancel';
  $('confirmOk').textContent = options.confirmLabel || 'Delete';
  $('confirmOk').className = options.danger === false ? 'btn btn-primary' : 'btn btn-danger-solid';

  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('no-scroll');

  return new Promise(resolve => {
    confirmResolver = resolve;
    setTimeout(() => $('confirmOk')?.focus({ preventScroll: true }), 60);
  });
}

function closeConfirm(result) {
  const modal = $('confirmModal');
  if (!modal) return;
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');
  if (!document.body.classList.contains('drawer-open') && !document.body.classList.contains('sidebar-open') && !$('quickSheet')?.classList.contains('open')) {
    document.body.classList.remove('no-scroll');
  }
  if (confirmResolver) {
    confirmResolver(Boolean(result));
    confirmResolver = null;
  }
}

let toastTimer = null;

function toast(message, tone = 'success') {
  const el = $('toast');
  if (!el) return;
  el.textContent = message;
  el.classList.toggle('error', tone === 'error');
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3200);
}

async function quickAddWater() {
  const result = await api('/water-logs', 'POST', { amount_ml: 250, log_date: today() });
  if (result && result.id) {
    closeQuickSheet();
    toast('Added 250 ml of water.');
    if (typeof window.loadDashboard === 'function') window.loadDashboard();
  } else {
    toast(errText(result), 'error');
  }
}

/* ---------------------------------------------------------
   Navigation state and cycle visibility
   --------------------------------------------------------- */
function activeNav() {
  document.querySelectorAll('[data-nav]').forEach(link => {
    if (link.getAttribute('href') === location.pathname) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
    }
  });
}

function setCycleVisibility(show) {
  document.querySelectorAll('[data-cycle]').forEach(el => {
    el.hidden = !show;
  });
}

/* ---------------------------------------------------------
   Notifications
   --------------------------------------------------------- */
let notificationCache = [];

function updateNotificationBadges(items) {
  const unread = (items || []).filter(item => !item.read_at).length;

  [$('notifDot'), $('mobileNotifDot')].forEach(dot => {
    if (dot) dot.hidden = unread === 0;
  });
}

async function refreshNotificationBadge() {
  if (document.body.classList.contains('is-public')) return;

  const items = await api('/notifications');
  if (Array.isArray(items)) {
    notificationCache = items;
    updateNotificationBadges(items);
    if (document.body.classList.contains('drawer-open')) renderNotificationDrawer();
  }
}

function notificationIcon(item) {
  const url = String(item?.data?.url || '');
  if (url.includes('medication')) return 'pill';
  if (url.includes('appointment')) return 'calendar';
  return 'bell';
}

function renderNotificationDrawer() {
  const list = $('drawerList');
  if (!list) return;

  const unread = notificationCache.filter(item => !item.read_at).length;
  const summary = $('drawerSummary');
  if (summary) {
    summary.textContent = unread ? unread + ' unread' : 'You are all caught up';
  }

  if (!notificationCache.length) {
    list.innerHTML = `
      <div class="empty-state">
        <span class="empty-orb"><svg class="icon"><use href="#i-bell"/></svg></span>
        <h4>No notifications yet</h4>
        <p>Medication and appointment reminders will appear here when they are due.</p>
      </div>`;
    return;
  }

  list.innerHTML = notificationCache.map(item => {
    const payload = item.data || {};
    return `
      <article class="notification-row ${item.read_at ? '' : 'unread'}">
        <span class="record-icon"><svg class="icon"><use href="#i-${notificationIcon(item)}"/></svg></span>
        <div class="notification-copy">
          <strong>${esc(payload.title || 'Reminder')}</strong>
          <p>${esc(payload.body || '')}</p>
          <small>${esc(relativeTime(item.created_at))}</small>
        </div>
        <div class="notification-actions">
          ${payload.url ? `<button type="button" class="record-row-action" data-open-notification="${esc(item.id)}" data-url="${esc(payload.url)}" aria-label="Open notification"><svg class="icon"><use href="#i-arrow"/></svg></button>` : ''}
          ${item.read_at ? '' : `<button type="button" class="record-row-action" data-read-notification="${esc(item.id)}" aria-label="Mark as read"><svg class="icon"><use href="#i-check"/></svg></button>`}
        </div>
      </article>`;
  }).join('');
}

async function markNotificationRead(id) {
  const result = await api('/notifications/' + encodeURIComponent(id) + '/read', 'POST');
  if (result && result.message === 'Read') {
    await refreshNotificationBadge();
    renderNotificationDrawer();
    if (typeof window.loadNotificationsPage === 'function') window.loadNotificationsPage();
    return true;
  }
  toast(errText(result), 'error');
  return false;
}

async function markAllNotificationsRead() {
  const result = await api('/notifications/read-all', 'POST');
  if (result && result.message === 'All read') {
    toast('All notifications marked as read.');
    await refreshNotificationBadge();
    renderNotificationDrawer();
    if (typeof window.loadNotificationsPage === 'function') window.loadNotificationsPage();
  } else {
    toast(errText(result), 'error');
  }
}

async function openNotification(id, url) {
  await markNotificationRead(id);
  if (url) location.href = url;
}

function bindShell() {
  const drawerList = $('drawerList');
  if (drawerList) {
    drawerList.addEventListener('click', event => {
      const openButton = event.target.closest('[data-open-notification]');
      if (openButton) {
        openNotification(openButton.dataset.openNotification, openButton.dataset.url);
        return;
      }
      const readButton = event.target.closest('[data-read-notification]');
      if (readButton) markNotificationRead(readButton.dataset.readNotification);
    });
  }

  document.querySelectorAll('.side-link, .sidebar-profile, .mobile-nav a').forEach(link => {
    link.addEventListener('click', () => {
      if (!isDesktop()) closeSidebar();
    });
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('#accountMenu, [data-account-toggle]')) closeAccountMenu();
  });

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if (!$('accountDropdown')?.hidden) closeAccountMenu();
    else if ($('confirmModal')?.classList.contains('open')) closeConfirm(false);
    else if ($('quickSheet')?.classList.contains('open')) closeQuickSheet();
    else if ($('notificationDrawer')?.classList.contains('open')) closeNotificationDrawer();
    else if (document.body.classList.contains('sidebar-open')) closeSidebar();
  });
}

/* ---------------------------------------------------------
   Boot
   --------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', async () => {
  bindShell();

  document.querySelector('.menu-btn')?.setAttribute(
    'aria-expanded',
    String(!document.body.classList.contains('sidebar-collapsed'))
  );

  if (isPublicPage()) {
    try {
      sessionStorage.removeItem('medibyte-sidebar');
    } catch (error) {}
    document.body.classList.add('is-public');
    return;
  }

  if (location.pathname === '/profile-setup') {
    document.body.classList.add('is-onboarding');
  } else {
    activeNav();
  }

  const profile = await api('/profile');
  if (profile && profile.show_period_tracker) {
    setCycleVisibility(true);
  }

  if (location.pathname !== '/profile-setup') {
    refreshNotificationBadge();
  }

  // New accounts finish the guided setup before using the dashboard.
  if (profile && profile.id && !profile.onboarding_completed_at && location.pathname !== '/profile-setup') {
    location.replace('/profile-setup');
  }
});

/* ---------------------------------------------------------
   Generic record page
   --------------------------------------------------------- */
/* ---------------------------------------------------------
   Calendar helpers (used by logPage)
   --------------------------------------------------------- */
const MOOD_EMOJI = {
  happy: '😄', excited: '🤩', grateful: '🙏', calm: '😌', content: '😊',
  hopeful: '🌈', proud: '🏆', loved: '🥰', energetic: '⚡', motivated: '💪',
  relaxed: '🧘', bored: '😑', tired: '😴', neutral: '😐', confused: '😕',
  lonely: '🥺', sad: '😢', anxious: '😰', stressed: '😣', overwhelmed: '😵',
  angry: '😠', frustrated: '😤', irritated: '😒', disappointed: '😞',
  guilty: '😔', embarrassed: '😳', scared: '😨', numb: '😶'
};

function moodEmoji(row) {
  if (!row || !row.mood) return '';
  return MOOD_EMOJI[String(row.mood).toLowerCase()] || '🙂';
}

function logPage(cfg) {
  let rows = [];
  let edit = null;

  /* ---- Calendar ------------------------------------------------------
     Shows every record on a month grid. It reads the same `rows` the
     history list uses, so nothing extra is fetched. Set `calendar: false`
     in a page's logPage() config to turn it off for that page.        */
  const dateField = cfg.fields.find(f => ['today', 'date', 'datetime-local'].includes(f[2]));
  const dateKey = dateField ? dateField[0] : null;
  const endKey = cfg.fields.some(f => f[0] === 'end_date') ? 'end_date' : null;
  const showCalendar = cfg.calendar !== false && Boolean(dateKey);
  const pad2 = n => String(n).padStart(2, '0');
  const dayKey = (y, m, d) => y + '-' + pad2(m + 1) + '-' + pad2(d);
  const nowDate = new Date();
  let calYear = nowDate.getFullYear();
  let calMonth = nowDate.getMonth();
  let calSel = today();

  function addDays(day, n) {
    const d = new Date(day + 'T00:00:00');
    d.setDate(d.getDate() + n);
    return dayKey(d.getFullYear(), d.getMonth(), d.getDate());
  }

  function dayMap() {
    const map = {};
    rows.forEach(row => {
      const first = dateKey && row[dateKey] ? ld(row[dateKey]) : '';
      if (!first) return;
      let last = endKey && row[endKey] ? ld(row[endKey]) : first;
      if (!last || last < first) last = first;
      let day = first;
      let guard = 0;
      while (day <= last && guard++ < 60) {
        (map[day] = map[day] || []).push(row);
        day = addDays(day, 1);
      }
    });
    return map;
  }

  function drawCalendar() {
    const host = $('logCalendar');
    if (!host) return;

    const map = dayMap();
    const t = today();
    const moodMode = cfg.title === 'Mood' || rows.some(r => moodEmoji(r));
    const firstOfMonth = new Date(calYear, calMonth, 1);
    const lead = firstOfMonth.getDay();
    const total = new Date(calYear, calMonth + 1, 0).getDate();
    const title = firstOfMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

    let cells = '';
    for (let i = 0; i < lead; i++) cells += '<span class="cal-day empty" aria-hidden="true"></span>';

    for (let d = 1; d <= total; d++) {
      const key = dayKey(calYear, calMonth, d);
      const list = map[key];
      const emoji = list ? moodEmoji(list[list.length - 1]) : '';
      const classes = ['cal-day'];
      if (moodMode) classes.push('mood-cell');
      if (list && !moodMode) classes.push('on');
      if (key === t) classes.push('today');
      if (key === calSel) classes.push('sel');
      const label = key + (list ? ', ' + list.length + (list.length === 1 ? ' record' : ' records') : '');
      const face = moodMode
        ? `<b>${d}</b>${emoji ? `<i aria-hidden="true">${emoji}</i>` : (list ? '<i aria-hidden="true">•</i>' : '')}`
        : `<span>${d}</span>`;
      cells += `<button type="button" class="${classes.join(' ')}" onclick="window.calPick('${key}')" aria-pressed="${key === calSel}" aria-label="${esc(label)}">${face}</button>`;
    }

    let legend = '<span><i class="cal-dot"></i>Logged</span>';
    if (moodMode) {
      const used = [...new Set(rows.filter(r => r.mood).map(r => String(r.mood).toLowerCase()))];
      legend = used.map(m => `<span>${MOOD_EMOJI[m] || '🙂'} ${esc(m.charAt(0).toUpperCase() + m.slice(1))}</span>`).join('');
    }

    const picked = map[calSel] || [];
    const pickedLabel = new Date(calSel + 'T00:00:00')
      .toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });

    host.innerHTML = `
      <section class="card calendar-card">
        <div class="cal-head">
          <button type="button" class="btn btn-small" onclick="window.calMove(-1)" aria-label="Previous month">‹</button>
          <h3>${esc(title)}</h3>
          <span class="cal-head-actions">
            <button type="button" class="btn btn-small" onclick="window.calToday()">Today</button>
            <button type="button" class="btn btn-small" onclick="window.calMove(1)" aria-label="Next month">›</button>
          </span>
        </div>
        <div class="cal-grid">
          ${['S', 'M', 'T', 'W', 'T', 'F', 'S'].map(x => `<span class="cal-dow" aria-hidden="true">${x}</span>`).join('')}
          ${cells}
        </div>
        ${legend ? `<div class="cal-legend">${legend}</div>` : ''}
        <div class="cal-day-panel">
          <div class="card-head">
            <strong>${esc(pickedLabel)}</strong>
            <button type="button" class="btn btn-small btn-primary" onclick="window.calAddForDay()">Add for this day</button>
          </div>
          ${picked.length
            ? `<div class="record-list">${picked.map(rowHtml).join('')}</div>`
            : '<p class="muted cal-empty">Nothing logged for this day.</p>'}
        </div>
      </section>`;
  }

  window.calMove = n => {
    const d = new Date(calYear, calMonth + n, 1);
    calYear = d.getFullYear();
    calMonth = d.getMonth();
    drawCalendar();
  };

  window.calToday = () => {
    const n = new Date();
    calYear = n.getFullYear();
    calMonth = n.getMonth();
    calSel = today();
    drawCalendar();
  };

  window.calPick = day => {
    calSel = day;
    drawCalendar();
  };

  window.calAddForDay = () => {
    if (edit) {
      edit = null;
      msg('');
      draw();
    }
    const el = $('f_' + dateKey);
    if (el) {
      el.value = el.type === 'datetime-local'
        ? calSel + 'T' + ((el.value || '').slice(11, 16) || '09:00')
        : calSel;
    }
    window.addLogRecord();
  };

  const tone = cfg.tone || 'blue';
  const icon = cfg.icon || 'file';
  const msg = text => {
    const el = $('msg');
    if (el) el.textContent = text || '';
  };

  function fieldValue(type, value, def) {
    if (value === undefined || value === null || value === '') return def;
    if (type === 'check') return Boolean(value);
    if (type === 'list') return Array.isArray(value) ? value.join(', ') : value;
    if (type === 'today' || type === 'date') return ld(value);
    if (type === 'time') return lt(value);
    if (type === 'datetime-local') return ldt(value);
    return value;
  }

  function input([name, label, type, opts, def, placeholder], value) {
    const v = fieldValue(type, value, def);
    const id = 'f_' + name;
    const safeLabel = esc(label);

    if (type === 'check') {
      return `<label class="check-row"><input type="checkbox" id="${id}" ${v ? 'checked' : ''}> <span>${safeLabel}</span></label>`;
    }

    if (type === 'select') {
      const options = (opts || []).map(o => {
        const optionValue = String(o ?? '');
        const text = optionValue === '' ? 'Choose…' : optionValue.replace(/_/g, ' ');
        return `<option value="${esc(optionValue)}" ${String(o) === String(v) ? 'selected' : ''}>${esc(text)}</option>`;
      }).join('');
      return `<label class="field"><span>${safeLabel}</span><select id="${id}">${options}</select></label>`;
    }

    if (type === 'textarea') {
      return `<label class="field"><span>${safeLabel}</span><textarea id="${id}" rows="3" placeholder="${esc(placeholder || '')}">${esc(v ?? '')}</textarea></label>`;
    }

    const htmlType = type === 'list' ? 'text' : type === 'today' ? 'date' : type;
    return `<label class="field"><span>${safeLabel}</span><input id="${id}" type="${htmlType}" step="any" value="${esc(v ?? '')}" placeholder="${esc(placeholder || '')}"></label>`;
  }

  function rowLabel(row) {
    try {
      if (typeof cfg.value === 'function') return cfg.value(row);
      return cfg.show(row);
    } catch (error) {
      return 'Record #' + row.id;
    }
  }

  function rowMeta(row) {
    try {
      return typeof cfg.meta === 'function' ? cfg.meta(row) : '';
    } catch (error) {
      return '';
    }
  }

  function rowHtml(row) {
    const label = rowLabel(row);
    const meta = rowMeta(row);
    return `<div class="record">
      <span class="record-icon tone-${esc(tone)}"><svg class="icon"><use href="#i-${esc(icon)}"/></svg></span>
      <div class="record-main">
        <strong>${esc(label)}</strong>
        ${meta ? `<span class="record-meta">${esc(meta)}</span>` : ''}
      </div>
      <div class="record-actions">
        <button type="button" class="record-row-action" onclick="window.editLog(${Number(row.id)})" aria-label="Edit record">
          <svg class="icon"><use href="#i-pencil"/></svg>
        </button>
        <button type="button" class="record-row-action danger" onclick="window.deleteLog(${Number(row.id)})" aria-label="Delete record">
          <svg class="icon"><use href="#i-trash"/></svg>
        </button>
      </div>
    </div>`;
  }

  function draw() {
    const current = edit || {};
    const form = cfg.fields.map(field => input(field, current[field[0]])).join('');

    $('log').innerHTML = `
      ${showCalendar ? '<div id="logCalendar"></div>' : ''}
      <section class="card record-form-card" id="recordFormCard">
        <div class="card-head">
          <div>
            <p class="eyebrow">${edit ? 'Editing record' : 'New record'}</p>
            <h3>${edit ? 'Update this entry' : (cfg.formTitle || 'Add a new entry')}</h3>
          </div>
          ${edit ? '<span class="badge pill-primary">Editing</span>' : ''}
        </div>
        <p class="form-card-note">${esc(cfg.formNote || 'Fields left blank are saved as empty. Only the required details are needed.')}</p>
        <form onsubmit="event.preventDefault(); window.saveLog()">
          <div class="form-grid">${form}</div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">
              <svg class="icon"><use href="#i-${edit ? 'check' : 'plus'}"/></svg>
              ${edit ? 'Save changes' : 'Add record'}
            </button>
            ${edit ? '<button type="button" class="btn btn-ghost" onclick="window.cancelLogEdit()">Cancel</button>' : ''}
          </div>
        </form>
      </section>
      <section class="card">
        <div class="card-head">
          <div>
            <p class="eyebrow">History</p>
            <h3>Your records</h3>
          </div>
          <span class="history-count">${rows.length}</span>
        </div>
        ${rows.length
          ? `<div class="record-list">${rows.map(rowHtml).join('')}</div>`
          : `<div class="empty-state">
               <span class="empty-orb"><svg class="icon"><use href="#i-${esc(icon)}"/></svg></span>
               <h4>No records yet</h4>
               <p>Add your first entry above and it will appear here.</p>
             </div>`}
      </section>`;

    if (showCalendar) drawCalendar();
  }

  async function load() {
    const data = await api(cfg.url);
    rows = Array.isArray(data) ? data : (data.data || []);
    draw();
  }

  window.addLogRecord = () => {
    const card = $('recordFormCard');
    if (!card) return;
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => card.querySelector('input, select, textarea')?.focus({ preventScroll: true }), 260);
  };

  window.saveLog = async () => {
    const body = {};

    cfg.fields.forEach(([name, , type]) => {
      const el = $('f_' + name);
      if (!el) return;

      if (type === 'check') {
        body[name] = el.checked;
        return;
      }

      if (type === 'list') {
        body[name] = el.value.split(',').map(s => s.trim()).filter(Boolean);
        return;
      }

      // Do not send blank optional fields. This is what caused 422 errors on
      // forms such as walking logs, where steps and minutes are both optional
      // but at least one is required.
      if (el.value === '') return;
      body[name] = el.value;
    });

    const data = await api(cfg.url + (edit ? '/' + edit.id : ''), edit ? 'PUT' : 'POST', body);
    if (data.id) {
      const wasEditing = Boolean(edit);
      edit = null;
      msg('');
      toast(wasEditing ? 'Changes saved.' : 'Record added.');
      await load();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      msg(errText(data));
      toast(errText(data), 'error');
    }
  };

  window.editLog = id => {
    edit = rows.find(r => Number(r.id) === Number(id)) || null;
    draw();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => $('recordFormCard')?.querySelector('input, select, textarea')?.focus({ preventScroll: true }), 260);
  };

  window.cancelLogEdit = () => {
    edit = null;
    msg('');
    draw();
  };

  window.deleteLog = async id => {
    const row = rows.find(r => Number(r.id) === Number(id));
    const label = row ? rowLabel(row) : 'This record';
    const ok = await confirmAction({
      title: 'Delete this record?',
      body: label + ' will be removed permanently.',
      confirmLabel: 'Delete'
    });
    if (!ok) return;

    const data = await api(cfg.url + '/' + id, 'DELETE');
    if (data.message === 'Deleted') {
      msg('');
      toast('Record deleted.');
      await load();
    } else {
      msg(errText(data));
      toast(errText(data), 'error');
    }
  };

  load();
}
