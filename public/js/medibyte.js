const $ = id => document.getElementById(id);
const publicPages = ['/login', '/register', '/privacy', '/forgot-password'];
const isPublicPage = () => publicPages.includes(location.pathname) || location.pathname.startsWith('/reset-password/');
const API_BASE = '/api/v1';

localStorage.removeItem('token');

/* ---------------------------------------------------------
   Mood chips
   --------------------------------------------------------- */
function mountMoodChips(select) {
  if (!select || select.dataset.chips) return;
  select.dataset.chips = '1';

  var groups = {
    'Feeling good': ['happy', 'excited', 'grateful', 'calm', 'content', 'hopeful', 'proud', 'loved', 'energetic', 'motivated', 'relaxed'],
    'In between': ['bored', 'tired', 'neutral', 'confused'],
    'Feeling down': ['lonely', 'sad', 'anxious', 'stressed', 'overwhelmed', 'angry', 'frustrated', 'irritated', 'disappointed', 'guilty', 'embarrassed', 'scared', 'numb']
  };

  var wrap = document.createElement('div');
  var tones = document.createElement('div');
  tones.className = 'mood-tones';
  var panel = document.createElement('div');
  var hint = document.createElement('p');
  hint.className = 'mood-hint';
  hint.textContent = 'Pick a feeling to see more moods.';

  var used = {};
  var sections = [];
  var active = null;

  function fireChange() {
    select.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function markChips() {
    panel.querySelectorAll('.mood-chip').forEach(function (c) {
      c.classList.toggle('on', c.dataset.value === select.value && select.value !== '');
    });
  }

  function show(sec, clear) {
    if (clear && active && active !== sec) {
      select.value = '';
      fireChange();
      markChips();
    }
    sections.forEach(function (s) {
      var on = s === sec;
      s.row.hidden = !on;
      s.btn.classList.toggle('on', on);
      s.btn.setAttribute('aria-pressed', String(on));
    });
    hint.hidden = true;
    active = sec;
  }

  function build(title, labels) {
    var opts = Array.prototype.filter.call(select.options, function (o) {
      return o.value !== '' && labels.indexOf(o.text.trim().toLowerCase()) !== -1;
    });
    if (!opts.length) return;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'mood-tone';
    btn.textContent = title;
    btn.setAttribute('aria-pressed', 'false');

    var row = document.createElement('div');
    row.className = 'mood-row';
    row.hidden = true;

    opts.forEach(function (o) {
      used[o.value] = true;
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'mood-chip';
      b.textContent = o.text;
      b.dataset.value = o.value;
      b.addEventListener('click', function () {
        select.value = o.value;
        fireChange();
        markChips();
      });
      row.appendChild(b);
    });

    var sec = { title: title, opts: opts, btn: btn, row: row };
    btn.addEventListener('click', function () { show(sec, true); });
    sections.push(sec);
    tones.appendChild(btn);
    panel.appendChild(row);
  }

  Object.keys(groups).forEach(function (k) { build(k, groups[k]); });

  var rest = Array.prototype.filter.call(select.options, function (o) {
    return o.value !== '' && !used[o.value];
  }).map(function (o) { return o.text.trim().toLowerCase(); });
  if (rest.length) build('Other', rest);

  wrap.appendChild(tones);
  wrap.appendChild(hint);
  wrap.appendChild(panel);

  select.hidden = true;
  select.insertAdjacentElement('afterend', wrap);

  var current = sections.filter(function (s) {
    return s.opts.some(function (o) { return o.value === select.value; });
  })[0];
  if (current && select.value !== '') {
    show(current, false);
    markChips();
  }
}

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

function formatNameInput(el) {
  const stripped = String(el.value ?? '')
    .replace(/[^\p{L}\p{M}\s]/gu, '')
    .replace(/\s+/gu, ' ');
  const titled = stripped.replace(/\p{L}[\p{L}\p{M}]*/gu, word =>
    word.charAt(0).toLocaleUpperCase() + word.slice(1).toLocaleLowerCase()
  );
  if (el.value !== titled) {
    const caret = el.selectionStart ?? titled.length;
    el.value = titled;
    try { el.setSelectionRange(caret, caret); } catch (error) { /* non-text input */ }
  }
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
  } catch (error) { }
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
   Field-level errors
   --------------------------------------------------------- */
function showFieldError(inputOrId, message, fadeMs = 8000) {
  const input = typeof inputOrId === 'string' ? $(inputOrId) : inputOrId;
  if (!input) return;

  input.classList.add('is-invalid');

  const parent = input.closest('.field') || input.parentElement;
  const prev = parent?.querySelector('.field-error');
  if (prev) prev.remove();

  const err = document.createElement('div');
  err.className = 'field-error';
  err.setAttribute('role', 'alert');
  err.textContent = message;

  const anchor = input.closest('.password-wrap') || input;
  anchor.insertAdjacentElement('afterend', err);

  if (fadeMs > 0) {
    setTimeout(() => {
      err.classList.add('is-fading');
      setTimeout(() => {
        err.remove();
        input.classList.remove('is-invalid');
      }, 400);
    }, fadeMs);
  }
}

function clearFieldError(inputOrId) {
  const input = typeof inputOrId === 'string' ? $(inputOrId) : inputOrId;
  if (!input) return;
  input.classList.remove('is-invalid');
  const parent = input.closest('.field') || input.parentElement;
  parent?.querySelector('.field-error')?.remove();
}

/* ---------------------------------------------------------
   Intensity segmented control (1–5)
   --------------------------------------------------------- */
function enhanceIntensitySlider(inputId, opts = {}) {
  const el = $(inputId);
  if (!el || el.dataset.enhanced === '1') return;

  const min = opts.min ?? 1;
  const max = opts.max ?? 5;
  let value = Number(el.value) || 3;
  if (value < min) value = min;
  if (value > max) value = max;

  const wrap = document.createElement('div');
  wrap.className = 'intensity-seg';
  wrap.setAttribute('role', 'radiogroup');
  wrap.setAttribute('aria-label', 'Intensity from 1 (lowest) to 5 (highest)');

  for (let i = min; i <= max; i++) {
    const id = inputId + '_' + i;
    wrap.innerHTML += `
      <input type="radio" id="${id}" name="${inputId}_seg" value="${i}" ${i === value ? 'checked' : ''}>
      <label for="${id}">${i}</label>
    `;
  }

  el.type = 'hidden';
  el.value = String(value);
  el.dataset.enhanced = '1';
  el.parentNode.insertBefore(wrap, el);

  wrap.querySelectorAll('input').forEach(radio => {
    radio.addEventListener('change', () => {
      el.value = radio.value;
    });
  });
}

/* ---------------------------------------------------------
   Sidebar, account menu, drawer, sheet, confirm, toast
   --------------------------------------------------------- */
function isDesktop() {
  return window.matchMedia('(min-width: 901px)').matches;
}

function setDesktopSidebarCollapsed(collapsed) {
  document.body.classList.toggle('sidebar-collapsed', collapsed);
  try {
    sessionStorage.setItem('medibyte-sidebar', collapsed ? 'hidden' : 'visible');
  } catch (error) { }
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
          ${payload.url ? `<button type="button" class="record-row-action" data-open-notification="${esc(item.id)}" data-url="${esc(payload.url)}" aria-label="Open notification" title="Open notification"><svg class="icon"><use href="#i-arrow"/></svg></button>` : ''}
          ${item.read_at ? '' : `<button type="button" class="record-row-action" data-read-notification="${esc(item.id)}" aria-label="Mark as read" title="Mark as read"><svg class="icon"><use href="#i-check"/></svg></button>`}
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

  bindSidebarTips();
}

/* Floating label for collapsed-sidebar icons (mirrors the dash rail) */
let sidebarTipEl = null;
let sidebarTipTarget = null;
let sidebarTipTimer = null;

function sidebarTipLabel(el) {
  return el.getAttribute('data-tip') || el.getAttribute('title') || el.getAttribute('aria-label') || '';
}

function showSidebarTip(el) {
  if (!isDesktop() || !document.body.classList.contains('sidebar-collapsed')) return;
  const label = sidebarTipLabel(el);
  if (!label) return;

  if (!el.dataset.tip) {
    el.dataset.tip = label;
    el.removeAttribute('title');
  }

  if (!sidebarTipEl) {
    sidebarTipEl = document.createElement('div');
    sidebarTipEl.id = 'sidebarTip';
    sidebarTipEl.className = 'sidebar-tip';
    sidebarTipEl.hidden = true;
    document.body.appendChild(sidebarTipEl);
  }

  sidebarTipTarget = el;
  clearTimeout(sidebarTipTimer);
  sidebarTipTimer = setTimeout(() => {
    if (!sidebarTipTarget) return;
    const rect = sidebarTipTarget.getBoundingClientRect();
    sidebarTipEl.textContent = sidebarTipLabel(sidebarTipTarget);
    sidebarTipEl.hidden = false;
    const tipHeight = sidebarTipEl.offsetHeight;
    let top = rect.top + rect.height / 2 - tipHeight / 2;
    top = Math.max(8, Math.min(top, window.innerHeight - tipHeight - 8));
    sidebarTipEl.style.top = top + 'px';
    sidebarTipEl.style.left = (rect.right + 12) + 'px';
  }, 140);
}

function hideSidebarTip(el) {
  if (el && el.dataset.tip) {
    el.setAttribute('title', el.dataset.tip);
    delete el.dataset.tip;
  }
  sidebarTipTarget = null;
  clearTimeout(sidebarTipTimer);
  if (sidebarTipEl) sidebarTipEl.hidden = true;
}

function bindSidebarTips() {
  document.querySelectorAll('.side-link[title], .sidebar-profile[title], .sidebar-expand[title]').forEach(el => {
    el.addEventListener('mouseenter', () => showSidebarTip(el));
    el.addEventListener('mouseleave', () => hideSidebarTip(el));
    el.addEventListener('focus', () => showSidebarTip(el));
    el.addEventListener('blur', () => hideSidebarTip(el));
    el.addEventListener('click', () => hideSidebarTip(el));
  });

  document.querySelector('.sidebar')?.addEventListener('scroll', () => hideSidebarTip());
  window.addEventListener('resize', () => hideSidebarTip());
}

document.addEventListener('DOMContentLoaded', async () => {
  bindShell();

  document.querySelector('.menu-btn')?.setAttribute(
    'aria-expanded',
    String(!document.body.classList.contains('sidebar-collapsed'))
  );

  if (isPublicPage()) {
    try {
      sessionStorage.removeItem('medibyte-sidebar');
    } catch (error) { }
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

  if (profile && profile.id && !profile.onboarding_completed_at && location.pathname !== '/profile-setup') {
    location.replace('/profile-setup');
  }
});

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

/* ---------------------------------------------------------
   Shared log page (calendar + form + history)
   --------------------------------------------------------- */
function logPage(cfg) {
  let rows = [];
  let edit = null;
  let customMoods = [];

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

  const DAY_FEATURES = [
    { key: 'water', label: 'Water', icon: 'droplet', tone: 'blue', href: '/water', rows: d => (d.water && Number(d.water.total_ml) > 0) ? [d.water] : [] },
    { key: 'sleep', label: 'Sleep', icon: 'moon', tone: 'violet', href: '/sleep', rows: d => d.sleep || [] },
    { key: 'walking', label: 'Activity', icon: 'activity', tone: 'amber', href: '/walking', rows: d => d.walking || [] },
    { key: 'meals', label: 'Meals', icon: 'meal', tone: 'mint', href: '/meals', rows: d => d.meals || [] },
    { key: 'weight', label: 'Weight', icon: 'scale', tone: 'slate', href: '/weight', rows: d => d.weight || [] },
    { key: 'moods', label: 'Mood', icon: 'smile', tone: 'gold', href: '/mood', rows: d => d.moods || [] },
    { key: 'symptoms', label: 'Symptoms', icon: 'thermometer', tone: 'amber', href: '/symptoms', rows: d => d.symptoms || [] },
    { key: 'vitals', label: 'Vitals', icon: 'heart', tone: 'rose', href: '/vitals', rows: d => d.vitals || [] },
    { key: 'period', label: 'Cycle', icon: 'cycle', tone: 'rose', href: '/period', rows: d => d.period || [] },
    { key: 'medications', label: 'Medications', icon: 'pill', tone: 'plum', href: '/medications', rows: d => d.medications || [] },
    { key: 'appointments', label: 'Appointments', icon: 'calendar', tone: 'blue', href: '/appointments', rows: d => d.appointments || [] },
    { key: 'documents', label: 'Documents', icon: 'file', tone: 'plum', href: '/documents', rows: d => d.documents || [] }
  ];

  let calendarOpen = false;

  function dayFeatureHtml(data) {
    const blocks = [];
    DAY_FEATURES.forEach(feature => {
      const entries = feature.rows(data);
      if (!entries.length) return;
      blocks.push(`
        <div class="cal-day-feature">
          <span class="cal-day-feature-ic tone-${feature.tone}"><svg class="icon" aria-hidden="true"><use href="#i-${feature.icon}"/></svg></span>
          <div class="cal-day-feature-body">
            <strong>${esc(feature.label)}</strong>
            ${entries.map(entry => `<span class="record-meta">${esc(entry.label || '—')}</span>`).join('')}
          </div>
          <a class="cal-day-feature-link" href="${esc(feature.href)}" aria-label="Open ${esc(feature.label)}">Open</a>
        </div>`);
    });
    return blocks.length ? `<div class="cal-day-list">${blocks.join('')}</div>` : '';
  }

  async function loadDayPanel(day) {
    const panel = $('calDayPanel');
    if (!panel) return;
    panel.innerHTML = '<p class="muted cal-empty">Loading this day…</p>';

    const data = await api('/records/day?date=' + encodeURIComponent(day));
    if (!data || !data.date) {
      panel.innerHTML = '<p class="muted cal-empty">Could not load records for this day.</p>';
      return;
    }

    const html = dayFeatureHtml(data);
    panel.innerHTML = html || '<p class="muted cal-empty">Nothing logged for this day.</p>';
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

    const legend = '<span><i class="cal-dot"></i>Logged</span>';

    const pickedLabel = new Date(calSel + 'T00:00:00')
      .toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });

    host.innerHTML = `
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
      <div class="cal-legend">${legend}</div>
      <div class="cal-day-panel">
        <div class="card-head">
          <strong>${esc(pickedLabel)}</strong>
        </div>
        <div id="calDayPanel"></div>
      </div>`;

    if (calendarOpen) loadDayPanel(calSel);
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

  const tone = cfg.tone || 'blue';
  const icon = cfg.icon || 'file';
  document.getElementById('main')?.classList.add('tone-' + tone);

  function statsHtml() {
    const tiles = [{ icon, label: 'Total records', value: String(rows.length) }];

    if (dateKey) {
      const monthPrefix = nowDate.getFullYear() + '-' + pad2(nowDate.getMonth() + 1);
      const days = rows.map(r => (r[dateKey] ? ld(r[dateKey]) : '')).filter(Boolean).sort();
      const thisMonth = days.filter(d => d.startsWith(monthPrefix)).length;
      const last = days.length ? days[days.length - 1] : '';

      tiles.push({ icon: 'calendar', label: 'This month', value: String(thisMonth) });
      tiles.push({ icon: 'clock', label: 'Last entry', value: last ? dateLabel(last) : 'None yet' });
    }

    return `<div class="log-stats">${tiles.map(t => `
      <div class="log-stat">
        <span class="log-stat-ic"><svg class="icon" aria-hidden="true"><use href="#i-${esc(t.icon)}"/></svg></span>
        <div><small>${esc(t.label)}</small><strong>${esc(t.value)}</strong></div>
      </div>`).join('')}</div>`;
  }

  const msg = text => {
    const el = $('msg');
    if (el) el.textContent = text || '';
    const formEl = $('formMsg');
    if (formEl) {
      formEl.textContent = text || '';
      formEl.style.display = text ? 'block' : 'none';
    }
  };

  function fieldValue(type, value, def) {
    if (value === undefined || value === null || value === '') {
      if (def !== undefined) return def;
      return type === 'today' ? today() : def;
    }
    if (type === 'check') return Boolean(value);
    if (type === 'list') return Array.isArray(value) ? value.join(', ') : value;
    if (type === 'today' || type === 'date') return ld(value);
    if (type === 'time') return lt(value);
    if (type === 'datetime-local') return ldt(value);
    return value;
  }

  function input([name, label, type, opts, def, placeholder, showsWhen], value, values) {
    const v = fieldValue(type, value, def);
    const id = 'f_' + name;
    const safeLabel = esc(label);

    let html;

    if (type === 'check') {
      html = `<label class="check-row"><input type="checkbox" id="${id}" ${v ? 'checked' : ''}> <span>${safeLabel}</span></label>`;
    } else if (type === 'method') {
      const pairs = Array.isArray(opts) ? opts : [];
      const selected = v === undefined || v === null ? (pairs[0] ? pairs[0][0] : '') : v;
      html = `<label class="field"><span>${safeLabel}</span><select id="${id}">${pairs.map(([val, text]) => `<option value="${esc(String(val))}" ${String(val) === String(selected) ? 'selected' : ''}>${esc(String(text))}</option>`).join('')}</select></label>`;
    } else if (type === 'select') {
      const moodOptions = [...(opts || [])];

      if (
        name === 'mood' &&
        v &&
        v !== '__custom__' &&
        !moodOptions.some(o => String(o) === String(v))
      ) {
        moodOptions.push(v);
      }

      const options = moodOptions.map(o => {
        const optionValue = String(o ?? '');
        const text = optionValue === '' ? 'Choose…' : optionValue.replace(/_/g, ' ');
        return `<option value="${esc(optionValue)}" ${String(o) === String(v) ? 'selected' : ''}>${esc(text)}</option>`;
      });

      if (name === 'mood') {
        options.push(
          `<option value="__custom__" ${v === '__custom__' ? 'selected' : ''}>Other / Add your own mood</option>`
        );
      }

      const selectHTML = `<label class="field"><span>${safeLabel}</span><select id="${id}">${options.join('')}</select></label>`;

      if (name === 'mood') {
        return `
          <div class="mood-field-group">
            ${selectHTML}
            <label class="field" id="customMoodField" style="display:none">
              <span>Enter your own mood</span>
              <input type="text" id="f_custom_mood" maxlength="50" placeholder="Type your mood">
            </label>
          </div>
        `;
      }

      html = selectHTML;
    } else if (type === 'textarea') {
      html = `<label class="field"><span>${safeLabel}</span><textarea id="${id}" rows="3" placeholder="${esc(placeholder || '')}">${esc(v ?? '')}</textarea></label>`;
    } else if (name === 'intensity' || type === 'intensity') {
      html = `<label class="field"><span>${safeLabel}</span><input id="${id}" type="number" min="1" max="5" value="${esc(v ?? def ?? 3)}"></label>`;
    } else {
      const htmlType = type === 'list' ? 'text' : type === 'today' ? 'date' : type;
      const maxAttr = type === 'today' || opts === 'today' ? ` max="${today()}"` : '';
      html = `<label class="field"><span>${safeLabel}</span><input id="${id}" type="${htmlType}" step="any"${maxAttr} value="${esc(v ?? '')}" placeholder="${esc(placeholder || '')}"></label>`;
    }

    if (showsWhen) {
      const show = String((values || {})[showsWhen[0]] ?? '') === String(showsWhen[1]);
      html = `<div data-shows-when="${esc(showsWhen[0])}:${esc(showsWhen[1])}"${show ? '' : ' hidden'}>${html}</div>`;
    }

    return html;
  }

  function syncMethodFields() {
    document.querySelectorAll('#log [data-shows-when]').forEach(wrap => {
      const [name, value] = (wrap.dataset.showsWhen || ':').split(':');
      const control = $('f_' + name);
      if (!control) return;
      if (!control.dataset.methodBound) {
        control.dataset.methodBound = '1';
        control.addEventListener('change', syncMethodFields);
      }
      wrap.hidden = String(control.value) !== value;
    });
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
          <button type="button" class="record-row-action" onclick="window.editLog(${Number(row.id)})" aria-label="Edit record" title="Edit record">
            <svg class="icon"><use href="#i-pencil"/></svg>
          </button>
          <button type="button" class="record-row-action danger" onclick="window.deleteLog(${Number(row.id)})" aria-label="Delete record" title="Delete record">
            <svg class="icon"><use href="#i-trash"/></svg>
          </button>
        </div>
      </div>`;
  }

  function historyCardHtml() {
    const head = `
      <div class="card-head">
        <div>
          <p class="eyebrow">History</p>
          <h3>Your records</h3>
        </div>
        <div class="card-head-tools">
          ${showCalendar ? `<button type="button" class="btn btn-small btn-ghost" onclick="window.openLogCalendar()">
            <svg class="icon"><use href="#i-calendar"/></svg>View calendar
          </button>` : ''}
          <span class="history-count">${rows.length}</span>
        </div>
      </div>`;

    if (!rows.length) {
      return `<section class="card">
        ${head}
        <div class="empty-state">
          <span class="empty-orb"><svg class="icon"><use href="#i-${esc(icon)}"/></svg></span>
          <h4>No records yet</h4>
          <p>Add your first entry and it will appear here.</p>
          <button type="button" class="btn btn-primary" onclick="window.addLogRecord()">
            <svg class="icon"><use href="#i-plus"/></svg>
            Add record
          </button>
        </div>
      </section>`;
    }

    return `<section class="card">
      ${head}
      <div class="record-list">${rows.map(rowHtml).join('')}</div>
    </section>`;
  }

  function customMoodsCardHtml() {
    return `
      <section class="card" id="customMoodsCard">
        <div class="card-head">
          <div>
            <p class="eyebrow">Personalize</p>
            <h3>Saved custom moods</h3>
          </div>
          <div class="card-head-tools">
            <button type="button" class="card-add-btn" onclick="window.addCustomMood()" aria-label="Add custom mood" title="Add custom mood">
              <svg class="icon"><use href="#i-plus"/></svg>
            </button>
            <span class="history-count">${customMoods.length}</span>
          </div>
        </div>
        ${customMoods.length ? `
          <div class="record-list">
            ${customMoods.map(cm => `
              <div class="record">
                <span class="record-icon tone-rose"><svg class="icon"><use href="#i-smile"/></svg></span>
                <div class="record-main">
                  <strong>${esc(cm.name)}</strong>
                </div>
                <div class="record-actions">
                  <button type="button" class="record-row-action" onclick="event.preventDefault(); event.stopPropagation(); window.renameCustomMood(event, ${Number(cm.id)}); return false;" aria-label="Rename custom mood" title="Rename custom mood">
                    <svg class="icon" style="pointer-events: none;"><use href="#i-pencil"/></svg>
                  </button>
                  <button type="button" class="record-row-action danger" onclick="event.preventDefault(); event.stopPropagation(); window.deleteCustomMood(event, ${Number(cm.id)}); return false;" aria-label="Delete custom mood" title="Delete custom mood">
                    <svg class="icon" style="pointer-events: none;"><use href="#i-trash"/></svg>
                  </button>
                </div>
              </div>
            `).join('')}
          </div>
        ` : `
          <div class="empty-state" style="padding: 20px;">
            <p class="muted" style="margin: 0;">No custom moods saved yet. Select "Other / Add your own mood" in the add form to create one.</p>
          </div>
        `}
      </section>`;
  }

  function formModalHtml(form) {
    return `
      <div id="logFormModal" class="log-modal" hidden>
        <div class="log-modal-backdrop" onclick="window.closeLogForm()"></div>
        <div class="log-modal-dialog log-form-dialog glassy" role="dialog" aria-modal="true" aria-labelledby="logFormTitle">
          <header class="log-modal-head">
            <div>
              <p class="eyebrow">${edit ? 'Editing record' : 'New record'}</p>
              <h3 id="logFormTitle">${edit ? 'Update this entry' : (cfg.formTitle || 'Add a new entry')}</h3>
            </div>
            <button type="button" class="icon-btn" onclick="window.closeLogForm()" aria-label="Close form">
              <svg class="icon"><use href="#i-close"/></svg>
            </button>
          </header>
          <div class="log-modal-body">
            <p class="form-card-note">${esc(cfg.formNote || 'Fields left blank are saved as empty. Only the required details are needed.')}</p>
            <p id="formMsg" class="form-message" role="alert" style="display:none"></p>
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
          </div>
        </div>
      </div>`;
  }

  function calendarModalHtml() {
    return `
      <div id="logCalendarModal" class="log-modal" hidden>
        <div class="log-modal-backdrop" onclick="window.closeLogCalendar()"></div>
        <div class="log-modal-dialog log-calendar-dialog glassy" role="dialog" aria-modal="true" aria-labelledby="logCalendarTitle">
          <header class="log-modal-head">
            <div>
              <p class="eyebrow">Calendar</p>
              <h3 id="logCalendarTitle">${esc(cfg.calendarTitle || cfg.title || 'Records')} · Day review</h3>
            </div>
            <button type="button" class="icon-btn" onclick="window.closeLogCalendar()" aria-label="Close calendar">
              <svg class="icon"><use href="#i-close"/></svg>
            </button>
          </header>
          <div class="log-modal-body">
            <div id="logCalendar"></div>
          </div>
        </div>
      </div>`;
  }

  function draw() {
    const current = edit || {};
    const form = cfg.fields.map(field => input(field, current[field[0]], current)).join('');

    $('log').innerHTML = `
      ${statsHtml()}
      ${historyCardHtml()}
      ${cfg.url === '/mood-logs' ? customMoodsCardHtml() : ''}
      ${rows.length ? `<button type="button" id="logFab" class="fab" onclick="window.addLogRecord()" aria-label="Add record" title="Add record">
        <svg class="icon"><use href="#i-plus"/></svg>
      </button>` : ''}
      ${formModalHtml(form)}
      ${showCalendar ? calendarModalHtml() : ''}`;

    if (showCalendar) drawCalendar();

    if ($('f_intensity')) {
      enhanceIntensitySlider('f_intensity', {
        min: 1,
        max: 5,
        label: 'Intensity (1 = very mild, 5 = very strong)'
      });
    }

    syncMethodFields();

    if (edit && typeof cfg.methodInit === 'function') {
      try { cfg.methodInit(edit); } catch (error) { /* optional hook */ }
      syncMethodFields();
    }

    const moodSelect = $('f_mood');
    const customMoodField = $('customMoodField');

    if (moodSelect && customMoodField) {
      const syncCustomMood = () => {
        const isCustom = moodSelect.value === '__custom__';
        customMoodField.style.display = isCustom ? '' : 'none';

        if (isCustom) {
          $('f_custom_mood')?.focus();
        }
      };

      moodSelect.addEventListener('change', syncCustomMood);
      syncCustomMood();
    }

    mountMoodChips($('f_mood'));
  }

  async function reloadCustomMoods() {
    if (cfg.url !== '/mood-logs') return;

    const cms = await api('/custom-moods');
    customMoods = Array.isArray(cms) ? cms : (cms.data || []);

    const allMoods = await api('/moods');
    const moodField = cfg.fields.find(f => f[0] === 'mood');

    if (moodField && Array.isArray(allMoods)) {
      moodField[3] = ['', ...allMoods];
    }
  }

  async function load() {
    if (cfg.url === '/mood-logs') {
      await reloadCustomMoods();
    }
    const data = await api(cfg.url);
    rows = Array.isArray(data) ? data : (data.data || []);
    draw();
  }

  window.openLogForm = () => {
    const modal = $('logFormModal');
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add('log-modal-open');
    setTimeout(() => modal.querySelector('input, select, textarea')?.focus({ preventScroll: true }), 80);
  };

  window.closeLogForm = () => {
    const modal = $('logFormModal');
    if (modal) modal.hidden = true;
    if (!$('logCalendarModal') || $('logCalendarModal').hidden) {
      document.body.classList.remove('log-modal-open');
    }
  };

  window.openLogCalendar = () => {
    const modal = $('logCalendarModal');
    if (!modal) return;
    calendarOpen = true;
    modal.hidden = false;
    document.body.classList.add('log-modal-open');
    drawCalendar();
    setTimeout(() => modal.querySelector('.icon-btn')?.focus({ preventScroll: true }), 80);
  };

  window.closeLogCalendar = () => {
    calendarOpen = false;
    const modal = $('logCalendarModal');
    if (modal) modal.hidden = true;
    if (!$('logFormModal') || $('logFormModal').hidden) {
      document.body.classList.remove('log-modal-open');
    }
  };

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if ($('logCalendarModal') && !$('logCalendarModal').hidden) window.closeLogCalendar();
    else if ($('logFormModal') && !$('logFormModal').hidden) window.closeLogForm();
  });

  window.addLogRecord = () => {
    if (edit) {
      edit = null;
      msg('');
      draw();
    }
    window.openLogForm();
  };

  window.addCustomMood = () => {
    window.openLogForm();
    const moodSelect = $('f_mood');
    if (!moodSelect) return;
    moodSelect.value = '__custom__';
    moodSelect.dispatchEvent(new Event('change'));
    setTimeout(() => $('f_custom_mood')?.focus({ preventScroll: true }), 120);
  };

  window.renameCustomMood = async (eventOrId, idOrOldName, maybeOldName) => {
    let e = null;
    let id = eventOrId;
    let oldName = idOrOldName;
    if (eventOrId && typeof eventOrId === 'object' && typeof eventOrId.stopPropagation === 'function') {
      e = eventOrId;
      id = idOrOldName;
      oldName = maybeOldName;
    } else if (typeof window !== 'undefined' && window.event) {
      e = window.event;
    }
    if (e) {
      if (typeof e.preventDefault === 'function') e.preventDefault();
      if (typeof e.stopPropagation === 'function') e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    }

    if (!oldName) {
      const found = customMoods.find(cm => Number(cm.id) === Number(id));
      oldName = found ? found.name : '';
    }
    const newName = window.prompt('Rename custom mood:', oldName);
    if (!newName || newName.trim() === '' || newName.trim() === oldName) return;

    const res = await api('/custom-moods/' + id, 'PUT', { name: newName.trim() });
    if (res.id) {
      toast('Custom mood renamed.');
      await reloadCustomMoods();
      draw();
    } else {
      const err = errText(res);
      toast(err, 'error');
    }
  };

  window.deleteCustomMood = async (eventOrId, idOrName, maybeName) => {
    let e = null;
    let id = eventOrId;
    let name = idOrName;
    if (eventOrId && typeof eventOrId === 'object' && typeof eventOrId.stopPropagation === 'function') {
      e = eventOrId;
      id = idOrName;
      name = maybeName;
    } else if (typeof window !== 'undefined' && window.event) {
      e = window.event;
    }
    if (e) {
      if (typeof e.preventDefault === 'function') e.preventDefault();
      if (typeof e.stopPropagation === 'function') e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    }

    if (!name) {
      const found = customMoods.find(cm => Number(cm.id) === Number(id));
      name = found ? found.name : '';
    }
    const ok = await confirmAction({
      title: 'Delete custom mood?',
      body: `"${name}" will be removed from your saved moods. Historical records will keep this mood.`,
      confirmLabel: 'Delete'
    });
    if (!ok) return;

    const res = await api('/custom-moods/' + id, 'DELETE');
    if (res.message) {
      toast('Custom mood deleted.');
      await reloadCustomMoods();
      draw();
    } else {
      const err = errText(res);
      toast(err, 'error');
    }
  };

  window.saveLog = async () => {
    const body = {};

    cfg.fields.forEach(([name, , type]) => {
      const el = $('f_' + name);
      if (!el) return;
      if (el.closest('[data-shows-when]')?.hidden) return;

      if (type === 'check') {
        body[name] = el.checked;
        return;
      }

      if (type === 'list') {
        body[name] = el.value.split(',').map(s => s.trim()).filter(Boolean);
        return;
      }

      if (el.value === '') return;
      body[name] = el.value;
    });

    let createdCustomMood = false;
    if (cfg.url === '/mood-logs' && body.mood === '__custom__') {
      const customInput = $('f_custom_mood');
      const customName = customInput?.value.trim();

      if (!customName) {
        msg('Please enter your own mood.');
        customInput?.focus();
        return;
      }

      const savedMood = await api('/custom-moods', 'POST', {
        name: customName
      });

      if (savedMood.id) {
        body.mood = savedMood.name;
        createdCustomMood = true;
      } else {
        const allMoods = await api('/moods');
        const matched = Array.isArray(allMoods) && allMoods.find(m => m.toLowerCase() === customName.toLowerCase());
        if (matched) {
          body.mood = matched;
        } else {
          const err = errText(savedMood);
          msg(err);
          toast(err, 'error');
          return;
        }
      }
    }

    const data = await api(cfg.url + (edit ? '/' + edit.id : ''), edit ? 'PUT' : 'POST', body);
    if (data.id) {
      const wasEditing = Boolean(edit);
      edit = null;
      msg('');
      toast(wasEditing ? 'Changes saved.' : 'Record added.');
      window.closeLogForm();
      await load();
      if (createdCustomMood && $('customMoodsCard')) {
        $('customMoodsCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    } else {
      const err = errText(data);
      msg(err);
      toast(err, 'error');
      if (data && data.errors) {
        const firstErrKey = Object.keys(data.errors)[0];
        const targetField = $('f_' + firstErrKey) || (firstErrKey === 'log_date' ? $('f_log_date') : null);
        if (targetField) {
          targetField.scrollIntoView({ behavior: 'smooth', block: 'center' });
          targetField.focus({ preventScroll: true });
        }
      }
    }
  };

  window.editLog = id => {
    edit = rows.find(r => Number(r.id) === Number(id)) || null;
    draw();
    window.openLogForm();
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
