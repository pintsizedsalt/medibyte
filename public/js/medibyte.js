const $ = id => document.getElementById(id);
const publicPages = ['/login', '/register', '/privacy', '/forgot-password'];
const isPublicPage = () => publicPages.includes(location.pathname) || location.pathname.startsWith('/reset-password/');
const API_BASE = '/api/v1';
const WEEK_RECORD_THRESHOLD = 7;

function currentTheme() {
  return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
}

function syncThemeButton() {
  const dark = currentTheme() === 'dark';
  const button = $('themeToggle');
  if (button) {
    button.setAttribute('aria-pressed', String(dark));
    button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    button.title = dark ? 'Light mode' : 'Dark mode';
  }
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.setAttribute('content', dark ? '#13132f' : '#f6f9ff');
}

function toggleTheme() {
  const next = currentTheme() === 'dark' ? 'light' : 'dark';
  document.documentElement.dataset.theme = next;
  try {
    localStorage.setItem('medibyte.theme', next);
  } catch (e) {}
  syncThemeButton();
}

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

function optionLabel(value) {
  const text = String(value ?? '').replace(/_/g, ' ').trim();
  if (!text || /^\d/.test(text)) return text;
  return text.replace(/\p{L}[\p{L}\p{M}]*/gu, word =>
    word.charAt(0).toLocaleUpperCase() + word.slice(1).toLocaleLowerCase()
  );
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
    sessionStorage.removeItem('medibyte-sidebar-scroll');
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
   Shared form pieces used by the quick log and the feature pages
   --------------------------------------------------------- */

/* Fields pair up in two columns; a field that would sit alone fills the row. */
function alignFormGrid(grid) {
  if (!grid) return;

  /* A conditional wrapper inherits the full-width intent of the field inside it. */
  const fillsRow = child => child.dataset.gridFull === '1' || Boolean(child.querySelector(':scope > [data-grid-full]'));

  const items = Array.from(grid.children).filter(child =>
    !child.classList.contains('form-grid-action') && !child.classList.contains('form-actions'));
  const visible = items.filter(child => !child.hidden);

  items.forEach(child => child.classList.remove('span-full'));

  let column = 0;
  visible.forEach((child, index) => {
    if (fillsRow(child)) {
      child.classList.add('span-full');
      column = 0;
      return;
    }

    if (column === 1) {
      column = 0;
      return;
    }

    const next = visible[index + 1];
    if (!next || fillsRow(next)) child.classList.add('span-full');
    column = 1;
  });
}

function conditionSatisfied(control, expected) {
  if (!control) return false;
  if (control.type === 'checkbox') return String(control.checked) === expected;
  return String(control.value) === expected;
}

function bindFieldConditionals(root, prefix, grid) {
  if (!root) return;

  root.querySelectorAll('[data-shows-when]').forEach(wrap => {
    const [name, value] = (wrap.dataset.showsWhen || ':').split(':');
    const control = $(prefix + name);
    if (!control) return;
    if (!control.dataset.fieldBound) {
      control.dataset.fieldBound = '1';
      control.addEventListener('change', () => {
        bindFieldConditionals(root, prefix, grid);
      });
    }
    wrap.hidden = !conditionSatisfied(control, value);
  });

  root.querySelectorAll('[data-slider-value]').forEach(slider => {
    const output = $(slider.id + '_out');
    if (!output) return;
    const words = ['', 'Very mild', 'Mild', 'Moderate', 'Strong', 'Very strong'];
    const sync = () => {
      output.textContent = slider.value + ' / 5';
      slider.setAttribute('aria-valuetext', words[Number(slider.value)] || slider.value);
    };
    if (!slider.dataset.sliderBound) {
      slider.dataset.sliderBound = '1';
      slider.addEventListener('input', sync);
    }
    sync();
  });

  alignFormGrid(grid);
}

/* A 1–5 range input with a live value label. */
function intensitySliderHtml(id, safeLabel, value, scale) {
  const parsed = Number(value);
  const current = Number.isFinite(parsed) ? Math.min(5, Math.max(1, Math.round(parsed))) : 3;
  const [lowText, highText] = Array.isArray(scale) ? [scale[0] || 'Very mild', scale[1] || 'Very strong'] : ['Very mild', 'Very strong'];

  return `<div class="field slider-field" data-grid-full="1">
    <span class="slider-head"><span>${safeLabel}</span><output id="${id}_out" for="${id}">${current} / 5</output></span>
    <input id="${id}" type="range" min="1" max="5" step="1" value="${current}" data-slider-value="1">
    <small class="slider-scale"><span>${esc(lowText)}</span><span>${esc(highText)}</span></small>
  </div>`;
}

function switchRowHtml(id, safeLabel, checked) {
  return `<label class="switch-row" data-grid-full="1">
    <input type="checkbox" id="${id}" role="switch" ${checked ? 'checked' : ''}>
    <span class="switch-track" aria-hidden="true"></span>
    <span class="switch-label">${safeLabel}</span>
  </label>`;
}

/* Saved personalised moods are pinned to the top of the picker so they are not
   lost at the bottom of the predefined list. Both add-record forms use this. */
let personalizedMoodNames = [];

function rememberPersonalizedMoods(list) {
  personalizedMoodNames = (Array.isArray(list) ? list : [])
    .map(mood => String(typeof mood === 'string' ? mood : mood?.name ?? '').trim().toLowerCase())
    .filter(Boolean);
}

function moodOptionHtml(moods, selected) {
  const names = (Array.isArray(moods) ? moods : [])
    .map(mood => String(mood ?? '').trim())
    .filter(Boolean);

  const isPersonal = name => personalizedMoodNames.includes(name.toLowerCase());
  const personal = names.filter(isPersonal);
  const predefined = names.filter(name => !isPersonal(name));

  const option = name => `<option value="${esc(name)}" ${name === String(selected ?? '') ? 'selected' : ''}>${esc(optionLabel(name))}</option>`;

  return `<option value="" ${selected ? '' : 'selected'}>Choose…</option>`
    + (personal.length ? `<optgroup label="Your moods">${personal.map(option).join('')}</optgroup>` : '')
    + predefined.map(option).join('');
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

/* The sidebar scrolls independently of the page, so every feature link would
   otherwise send it back to the top. */
function keepSidebarScroll() {
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;

  try {
    const saved = Number(sessionStorage.getItem('medibyte-sidebar-scroll'));
    if (saved) sidebar.scrollTop = saved;
  } catch (error) { }

  sidebar.addEventListener('scroll', () => {
    try {
      sessionStorage.setItem('medibyte-sidebar-scroll', String(sidebar.scrollTop));
    } catch (error) { }
  }, { passive: true });
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

/* A floating, themed single-field question — the app's alternative to prompt(). */
function promptText(options = {}) {
  const host = document.createElement('div');
  host.className = 'log-modal';
  host.innerHTML = `
    <div class="log-modal-backdrop"></div>
    <div class="log-modal-dialog log-form-dialog glassy" role="dialog" aria-modal="true">
      <header class="log-modal-head">
        <div>
          <p class="eyebrow">${esc(options.eyebrow || 'Edit')}</p>
          <h3>${esc(options.title || 'Enter a name')}</h3>
        </div>
      </header>
      <div class="log-modal-body">
        ${options.note ? `<p class="form-card-note">${esc(options.note)}</p>` : ''}
        <p class="form-message" role="alert"></p>
        <form>
          <div class="form-grid">
            <label class="field" data-grid-full="1">
              <span>${esc(options.label || 'Name')}</span>
              <input type="text" maxlength="${Number(options.maxLength) || 50}" autocomplete="off"
                placeholder="${esc(options.placeholder || '')}" value="${esc(options.value || '')}">
            </label>
            <div class="form-grid-action">
              <button type="submit" class="btn btn-primary">
                <svg class="icon"><use href="#i-check"/></svg>
                ${esc(options.confirmLabel || 'Save')}
              </button>
              <button type="button" class="btn btn-ghost">${esc(options.cancelLabel || 'Cancel')}</button>
            </div>
          </div>
        </form>
      </div>
    </div>`;

  document.body.appendChild(host);
  document.body.classList.add('log-modal-open');

  const input = host.querySelector('input');
  const message = host.querySelector('.form-message');
  const form = host.querySelector('form');

  return new Promise(resolve => {
    let settled = false;

    const finish = value => {
      if (settled) return;
      settled = true;
      document.removeEventListener('keydown', onKeydown);
      host.remove();
      if (!document.querySelector('.log-modal:not([hidden])')) {
        document.body.classList.remove('log-modal-open');
      }
      resolve(value);
    };

    const submit = () => {
      const value = input.value.trim();
      if (!value) {
        message.textContent = options.emptyText || 'Please enter a value.';
        input.focus();
        return;
      }
      finish(value);
    };

    const onKeydown = event => { if (event.key === 'Escape') finish(null); };

    form.addEventListener('submit', event => {
      event.preventDefault();
      submit();
    });
    host.querySelector('.log-modal-backdrop').addEventListener('click', () => finish(null));
    host.querySelector('.btn-ghost').addEventListener('click', () => finish(null));
    document.addEventListener('keydown', onKeydown);

    setTimeout(() => {
      input.focus();
      input.select();
    }, 60);
  });
}

/* ---------------------------------------------------------
   Profile photo: upload, floating cropper, and the rounds it fills
   --------------------------------------------------------- */
const AVATAR_EDGE = 256;

function applyAvatarPhoto(url) {
  document.querySelectorAll('.avatar').forEach(avatar => {
    avatar.classList.toggle('has-photo', Boolean(url));
  });

  if (url) {
    document.documentElement.style.setProperty('--avatar-photo', `url('${url}')`);
  } else {
    document.documentElement.style.removeProperty('--avatar-photo');
  }
}

/** Resolves to a square JPEG data URL, or null when the user backs out. */
function openAvatarCropper(file) {
  return new Promise(resolve => {
    const objectUrl = URL.createObjectURL(file);
    const host = document.createElement('div');
    host.className = 'log-modal';
    host.innerHTML = `
      <div class="log-modal-backdrop"></div>
      <div class="log-modal-dialog glassy" role="dialog" aria-modal="true" aria-label="Adjust your photo">
        <header class="log-modal-head">
          <div>
            <p class="eyebrow">Profile photo</p>
            <h3>Adjust your photo</h3>
          </div>
        </header>
        <div class="log-modal-body">
          <div class="form-grid">
            <div class="crop-stage span-full">
              <img class="crop-image" src="${objectUrl}" alt="" draggable="false">
            </div>
            <label class="field slider-field span-full">
              <span class="slider-head"><span>Zoom</span><output>1.0×</output></span>
              <input type="range" min="1" max="3" step="0.02" value="1" aria-label="Zoom">
            </label>
            <p class="form-card-note span-full">Drag the photo to place your face inside the circle.</p>
            <div class="form-grid-action">
              <button type="button" class="btn btn-primary crop-save">
                <svg class="icon"><use href="#i-check"/></svg>
                Save
              </button>
              <button type="button" class="btn btn-ghost">Cancel</button>
            </div>
          </div>
        </div>
      </div>`;

    document.body.appendChild(host);
    document.body.classList.add('log-modal-open');

    const stage = host.querySelector('.crop-stage');
    const img = host.querySelector('.crop-image');
    const zoom = host.querySelector('input[type="range"]');
    const zoomOut = host.querySelector('output');
    const save = host.querySelector('.crop-save');

    let side = 0;
    let width = 0;
    let height = 0;
    let fit = 1;
    let zoomLevel = 1;
    let offsetX = 0;
    let offsetY = 0;
    let ready = false;

    const settle = value => {
      document.removeEventListener('keydown', onKeydown);
      window.removeEventListener('resize', measure);
      host.remove();
      URL.revokeObjectURL(objectUrl);
      if (!document.querySelector('.log-modal:not([hidden])')) {
        document.body.classList.remove('log-modal-open');
      }
      resolve(value);
    };

    const onKeydown = event => { if (event.key === 'Escape') settle(null); };

    const paint = () => {
      if (!ready) return;
      const drawnW = width * fit * zoomLevel;
      const drawnH = height * fit * zoomLevel;
      offsetX = Math.min(Math.max(offsetX, (side - drawnW) / 2), (drawnW - side) / 2);
      offsetY = Math.min(Math.max(offsetY, (side - drawnH) / 2), (drawnH - side) / 2);

      img.style.width = `${drawnW}px`;
      img.style.height = `${drawnH}px`;
      img.style.transform = `translate(-50%, -50%) translate(${offsetX}px, ${offsetY}px)`;
      zoomOut.textContent = `${zoomLevel.toFixed(1)}×`;
    };

    const measure = () => {
      side = stage.clientWidth;
      width = img.naturalWidth;
      height = img.naturalHeight;

      if (!side || !width || !height) return;

      fit = Math.max(side / width, side / height);
      ready = true;
      save.disabled = false;
      paint();
    };

    save.disabled = true;
    save.addEventListener('click', () => {
      if (!ready) return;

      const unit = fit * zoomLevel;
      // Pan and size are CSS pixels; drawImage wants image pixels, so divide by the scale.
      const sourceSide = side / unit;
      const startX = width / 2 - (offsetX + side / 2) / unit;
      const startY = height / 2 - (offsetY + side / 2) / unit;

      const canvas = document.createElement('canvas');
      canvas.width = AVATAR_EDGE;
      canvas.height = AVATAR_EDGE;
      const context = canvas.getContext('2d');
      // JPEG has no alpha, so a transparent PNG would otherwise flatten to black.
      context.fillStyle = '#ffffff';
      context.fillRect(0, 0, AVATAR_EDGE, AVATAR_EDGE);
      context.drawImage(img, startX, startY, sourceSide, sourceSide, 0, 0, AVATAR_EDGE, AVATAR_EDGE);

      settle(canvas.toDataURL('image/jpeg', 0.85));
    });

    zoom.addEventListener('input', () => {
      zoomLevel = Number(zoom.value) || 1;
      paint();
    });

    let drag = null;

    stage.addEventListener('pointerdown', event => {
      if (!ready) return;
      drag = { x: event.clientX, y: event.clientY, offsetX, offsetY };
      stage.setPointerCapture(event.pointerId);
      stage.classList.add('dragging');
    });

    stage.addEventListener('pointermove', event => {
      if (!drag) return;
      offsetX = drag.offsetX + (event.clientX - drag.x);
      offsetY = drag.offsetY + (event.clientY - drag.y);
      paint();
    });

    const endDrag = () => {
      drag = null;
      stage.classList.remove('dragging');
    };

    stage.addEventListener('pointerup', endDrag);
    stage.addEventListener('pointercancel', endDrag);

    host.querySelector('.log-modal-backdrop').addEventListener('click', () => settle(null));
    host.querySelector('.btn-ghost').addEventListener('click', () => settle(null));
    document.addEventListener('keydown', onKeydown);

    img.addEventListener('error', () => {
      toast('That file could not be opened as a photo.', 'error');
      settle(null);
    });

    if (img.complete && img.naturalWidth) {
      measure();
    } else {
      img.addEventListener('load', measure, { once: true });
    }

    window.addEventListener('resize', measure);
  });
}

async function uploadAvatar(dataUrl) {
  const result = await api('/avatar', 'POST', { image_data: dataUrl });

  if (result && result.avatar_url) {
    applyAvatarPhoto(result.avatar_url);
    toast('Profile photo updated.');
  } else {
    toast(errText(result), 'error');
  }
}

function pickAvatarPhoto() {
  const input = $('avatarFile');
  if (!input) return;
  input.value = '';
  input.click();
}

async function readAvatarPhoto(input) {
  const file = input.files && input.files[0];
  if (!file) return;

  const dataUrl = await openAvatarCropper(file);
  if (dataUrl) await uploadAvatar(dataUrl);
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
   Quick log (dashboard rail + mobile sheet)
   --------------------------------------------------------- */
const QUICK_LOG_DEFS = {
  water: {
    title: 'Add a water entry',
    eyebrow: 'Water',
    url: '/water-logs',
    note: 'Add one glass at a time or enter the total amount you have had so far.',
    fields: [
      ['amount_ml', 'Amount (ml)', 'number', null, null, 'e.g. 250'],
      ['log_date', 'Date', 'today']
    ]
  },
  sleep: {
    title: 'Add a sleep entry',
    eyebrow: 'Sleep',
    url: '/sleep-logs',
    note: 'Log bedtime and wake-up time, or enter the total hours slept.',
    fields: [
      ['sleep_method', 'How would you like to log your sleep?', 'select', [['times', 'Bedtime and wake-up time'], ['hours', 'Total hours slept']], 'times'],
      ['bedtime', 'Bedtime', 'time', null, null, null, ['sleep_method', 'times']],
      ['wake_time', 'Wake up', 'time', null, null, null, ['sleep_method', 'times']],
      ['hours', 'Hours slept', 'number', null, null, 'e.g. 7.5', ['sleep_method', 'hours']],
      ['log_date', 'Date', 'today']
    ]
  },
  meals: {
    title: 'Add a meal',
    eyebrow: 'Meals',
    url: '/meal-logs',
    note: 'A short description is enough. Calories are optional. Enter 0 if you don\'t have the calorie information.',
    fields: [
      ['meal_type', 'Meal', 'select', ['breakfast', 'lunch', 'dinner', 'snack'], 'breakfast'],
      ['food_name', 'Food', 'text', null, null, 'e.g. Chicken rice bowl'],
      ['meal_time', 'Time', 'time'],
      ['calories', 'Calories (optional)', 'number', null, null, 'e.g. 450'],
      ['log_date', 'Date', 'today']
    ]
  },
  mood: {
    title: 'Mood check-in',
    eyebrow: 'Mood',
    url: '/mood-logs',
    note: 'Choose the mood that fits best. You can add your own mood too.',
    fields: [
      ['mood', 'Mood', 'select', ['']],
      ['intensity', 'Intensity', 'slider', null, 3],
      ['log_date', 'Date', 'today']
    ]
  },
  weight: {
    title: 'Add a weight entry',
    eyebrow: 'Weight',
    url: '/weight-logs',
    note: 'Add your height so MediByte can calculate your BMI.',
    fields: [
      ['weight_kg', 'Weight (kg)', 'number', null, null, 'e.g. 62.5'],
      ['height_cm', 'Height (cm)', 'number', null, null, 'e.g. 165'],
      ['log_date', 'Date', 'today']
    ]
  },
  walking: {
    title: 'Log some movement',
    eyebrow: 'Activity',
    url: '/walking-logs',
    note: 'Steps and active minutes are both optional, but add at least one.',
    fields: [
      ['steps', 'Steps', 'number', null, null, 'e.g. 2500'],
      ['minutes', 'Minutes', 'number', null, null, 'e.g. 30'],
      ['log_date', 'Date', 'today']
    ]
  },
  symptoms: {
    title: 'Log a symptom',
    eyebrow: 'Symptoms',
    url: '/symptom-logs',
    note: 'Add the symptom and severity. Illness and notes are optional.',
    fields: [
      ['symptom', 'Symptom', 'text', null, null, 'e.g. Headache'],
      ['severity', 'Severity (1 to 5)', 'select', [1, 2, 3, 4, 5], 3],
      ['illness_name', 'Illness (optional)', 'text'],
      ['log_date', 'Date', 'today']
    ]
  },
  vitals: {
    title: 'Add a vital sign reading',
    eyebrow: 'Vitals',
    url: '/vital-sign-logs',
    note: 'Add the readings you have. Leave the fields you did not measure blank.',
    fields: [
      ['systolic', 'Systolic', 'number'],
      ['diastolic', 'Diastolic', 'number'],
      ['heart_rate', 'Heart rate (bpm)', 'number'],
      ['temperature_c', 'Temperature (C)', 'number'],
      ['oxygen_saturation', 'Oxygen (%)', 'number'],
      ['log_date', 'Date', 'today']
    ]
  },
  medications: {
    title: 'Add a medication',
    eyebrow: 'Medications',
    url: '/medications',
    note: 'Use 24-hour times separated by commas, for example 08:00, 20:00.',
    fields: [
      ['name', 'Medication name', 'text'],
      ['dosage', 'Dosage', 'text'],
      ['times', 'Times (24-hour, separated by commas)', 'list', null, null, 'e.g. 08:00, 20:00'],
      ['start_date', 'Start date', 'today'],
      ['is_active', 'I am currently taking this', 'check', null, true]
    ]
  },
  period: {
    title: 'Log a period',
    eyebrow: 'Cycle',
    url: '/period-logs',
    note: 'Add the first day and, if it has finished, the last day. Leave the last day blank for an ongoing period.',
    fields: [
      ['start_date', 'First day', 'today'],
      ['end_date', 'Last day (leave blank if ongoing)', 'date'],
      ['flow', 'Flow', 'select', ['', 'light', 'medium', 'heavy']]
    ]
  }
};

let activeQuickLog = null;

function quickFieldValue(type, value, def) {
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

function quickInputHtml([name, label, type, opts, def, placeholder, showsWhen], value, values) {
  const v = quickFieldValue(type, value, def);
  const id = 'q_' + name;
  const safeLabel = esc(label);
  let html;

  if (type === 'check') {
    html = switchRowHtml(id, safeLabel, v);
  } else if (type === 'select' || type === 'method') {
    const pairs = Array.isArray(opts) ? opts : [];
    const selected = v === undefined || v === null ? (pairs[0] ? (Array.isArray(pairs[0]) ? pairs[0][0] : pairs[0]) : '') : v;
    const options = pairs.map(option => {
      const optionValue = Array.isArray(option) ? option[0] : option;
      const optionText = Array.isArray(option)
        ? option[1]
        : (String(optionValue) === '' ? 'Choose…' : optionLabel(optionValue));
      return `<option value="${esc(String(optionValue))}" ${String(optionValue) === String(selected) ? 'selected' : ''}>${esc(String(optionText))}</option>`;
    });

    if (name === 'mood') {
      return `<label class="field" data-grid-full="1"><span>${safeLabel}</span><select id="${id}">${moodOptionHtml(pairs, v)}</select></label>`;
    }

    html = `<label class="field"${type === 'method' || name === 'sleep_method' ? ' data-grid-full="1"' : ''}><span>${safeLabel}</span><select id="${id}">${options.join('')}</select></label>`;
  } else if (type === 'textarea') {
    html = `<label class="field diary-field${name === 'event_note' ? ' note-field' : ''}" data-grid-full="1"><span>${safeLabel}</span><textarea id="${id}" rows="${name === 'event_note' ? 3 : 7}" placeholder="${esc(placeholder || '')}">${esc(v ?? '')}</textarea></label>`;
  } else if (name === 'intensity' || type === 'slider') {
    return intensitySliderHtml(id, safeLabel, v ?? def ?? 3);
  } else {
    const htmlType = type === 'list' ? 'text' : type === 'today' ? 'date' : type;
    const maxAttr = type === 'today' ? ` max="${today()}"` : '';
    const listAttr = type === 'list' ? ' data-grid-full="1"' : '';
    html = `<label class="field"${listAttr}><span>${safeLabel}</span><input id="${id}" type="${htmlType}" step="any"${maxAttr} value="${esc(v ?? '')}" placeholder="${esc(placeholder || '')}"></label>`;
  }

  if (showsWhen) {
    const show = String((values || {})[showsWhen[0]] ?? '') === String(showsWhen[1]);
    html = `<div data-shows-when="${esc(showsWhen[0])}:${esc(showsWhen[1])}"${show ? '' : ' hidden'}>${html}</div>`;
  }

  return html;
}

function syncQuickConditionals() {
  bindFieldConditionals($('quickLogBody'), 'q_', $('quickLogBody')?.querySelector('.form-grid'));
}

async function openQuickLog(key, defaults = {}) {
  const source = QUICK_LOG_DEFS[key];
  if (!source) return;

  closeQuickSheet();
  closeAccountMenu();

  const def = { ...source, fields: source.fields.map(field => field.slice()) };

  if (key === 'mood') {
    const [moods, custom] = await Promise.all([api('/moods'), api('/custom-moods')]);
    rememberPersonalizedMoods(Array.isArray(custom) ? custom.map(mood => mood.name) : []);
    def.fields[0][3] = Array.isArray(moods) ? moods : [];
  }

  activeQuickLog = { key, def };

  const body = def.fields
    .map(field => quickInputHtml(field, defaults[field[0]], defaults))
    .join('');

  $('quickLogEyebrow').textContent = def.eyebrow;
  $('quickLogTitle').textContent = def.title;
  $('quickLogBody').innerHTML = `
    <p class="form-card-note">${esc(def.note || '')}</p>
    <p id="quickMsg" class="form-message" role="alert"></p>
    <form onsubmit="event.preventDefault(); saveQuickLog()">
      <div class="form-grid">${body}</div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <svg class="icon"><use href="#i-plus"/></svg>
          Add record
        </button>
        <button type="button" class="btn btn-ghost" onclick="closeQuickLog()">Cancel</button>
      </div>
    </form>`;

  const modal = $('quickLogModal');
  modal.hidden = false;
  document.body.classList.add('log-modal-open');
  syncQuickConditionals();
  setTimeout(() => modal.querySelector('input, select, textarea')?.focus({ preventScroll: true }), 80);
}

function closeQuickLog() {
  const modal = $('quickLogModal');
  if (modal) modal.hidden = true;
  activeQuickLog = null;
  if (!$('logFormModal') || $('logFormModal').hidden) {
    if (!$('logCalendarModal') || $('logCalendarModal').hidden) {
      document.body.classList.remove('log-modal-open');
    }
  }
}

function quickLogFromSheet(key) {
  closeQuickSheet();
  openQuickLog(key);
}

async function saveQuickLog() {
  if (!activeQuickLog) return;
  const { def } = activeQuickLog;
  const body = {};

  def.fields.forEach(([name, , type]) => {
    const el = $('q_' + name);
    if (!el) return;
    if (el.closest('[data-shows-when]')?.hidden) return;

    if (type === 'check') {
      body[name] = el.checked;
      return;
    }

    if (type === 'list') {
      body[name] = el.value.split(',').map(part => part.trim()).filter(Boolean);
      return;
    }

    if (el.value === '') return;
    body[name] = el.value;
  });

  const result = await api(def.url, 'POST', body);
  if (result && result.id) {
    closeQuickLog();
    toast('Record added.');
    if (typeof window.loadDashboard === 'function') {
      window.loadDashboard();
    } else if (typeof window.__medibyteReloadLog === 'function') {
      window.__medibyteReloadLog();
    }
    return;
  }

  const message = errText(result);
  $('quickMsg').textContent = message;
  toast(message, 'error');

  if (result && result.errors) {
    const firstKey = Object.keys(result.errors)[0];
    const target = $('q_' + firstKey);
    target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target?.focus({ preventScroll: true });
  }
}

/* ---------------------------------------------------------
   Global search
   --------------------------------------------------------- */
let searchTimer = null;
let searchSequence = 0;
let searchEntries = [];
let searchActiveIndex = -1;

function searchResultRow(item) {
  return `
    <button type="button" class="global-search-result" role="option" data-search-href="${esc(item.href)}" data-search-index="${searchEntries.length}">
      <span class="global-search-result-icon tone-${esc(item.tone || 'blue')}"><svg class="icon" aria-hidden="true"><use href="#i-${esc(item.icon || 'file')}"/></svg></span>
      <span class="global-search-result-copy">
        <strong>${esc(item.title || item.label || '')}</strong>
        ${item.subtitle || item.description ? `<small>${esc(item.subtitle || item.description)}</small>` : ''}
      </span>
      ${item.date ? `<span class="global-search-result-date">${esc(dateLabel(item.date))}</span>` : ''}
    </button>`;
}

function renderGlobalSearch(data) {
  const box = $('globalSearchResults');
  if (!box) return;

  searchEntries = [];
  searchActiveIndex = -1;

  const groups = [];
  const features = (data && data.features) || [];
  const meals = (data && data.meals) || [];
  const documents = (data && data.documents) || [];

  if (features.length) {
    groups.push(`<p class="global-search-group">Features</p>${features.map(feature => {
      searchEntries.push(feature);
      return searchResultRow({ ...feature, title: feature.label, subtitle: feature.description });
    }).join('')}`);
  }

  if (meals.length) {
    groups.push(`<p class="global-search-group">Meals</p>${meals.map(meal => {
      searchEntries.push(meal);
      return searchResultRow(meal);
    }).join('')}`);
  }

  if (documents.length) {
    groups.push(`<p class="global-search-group">Documents</p>${documents.map(document => {
      searchEntries.push(document);
      return searchResultRow(document);
    }).join('')}`);
  }

  if (!groups.length) {
    box.innerHTML = `<p class="global-search-empty">No matches yet. Try “sleep”, “mood”, or a food you logged.</p>`;
  } else {
    box.innerHTML = groups.join('');
  }

  box.hidden = false;
  $('globalSearchInput')?.setAttribute('aria-expanded', 'true');
}

function setSearchActive(index) {
  const box = $('globalSearchResults');
  if (!box) return;
  const rows = box.querySelectorAll('.global-search-result');
  if (!rows.length) return;

  searchActiveIndex = (index + rows.length) % rows.length;
  rows.forEach((row, i) => {
    const active = i === searchActiveIndex;
    row.classList.toggle('is-active', active);
    row.setAttribute('aria-selected', String(active));
    if (active) row.scrollIntoView({ block: 'nearest' });
  });
}

function closeGlobalSearch() {
  const box = $('globalSearchResults');
  if (box) box.hidden = true;
  $('globalSearchInput')?.setAttribute('aria-expanded', 'false');
  searchActiveIndex = -1;
}

function clearGlobalSearch() {
  const input = $('globalSearchInput');
  if (input) input.value = '';
  $('globalSearchClear').hidden = true;
  closeGlobalSearch();
}

async function runGlobalSearch(query) {
  const sequence = ++searchSequence;
  const cleaned = String(query || '').trim();
  $('globalSearchClear').hidden = cleaned.length === 0;

  if (!cleaned) {
    const data = await api('/search');
    if (sequence === searchSequence) renderGlobalSearch(data);
    return;
  }

  const data = await api('/search?q=' + encodeURIComponent(cleaned));
  if (sequence !== searchSequence) return;

  if (!data || data.message) {
    closeGlobalSearch();
    return;
  }

  renderGlobalSearch(data);
}

function bindGlobalSearch() {
  const input = $('globalSearchInput');
  const box = $('globalSearchResults');
  if (!input || !box) return;

  input.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => runGlobalSearch(input.value), 180);
  });

  input.addEventListener('focus', () => {
    if (!input.value.trim()) runGlobalSearch('');
    else if (!box.hidden) input.setAttribute('aria-expanded', 'true');
  });

  input.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      if (box.hidden) runGlobalSearch(input.value);
      else setSearchActive(searchActiveIndex + 1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setSearchActive(searchActiveIndex - 1);
    } else if (event.key === 'Enter') {
      const active = box.querySelector('.global-search-result.is-active');
      if (active) {
        event.preventDefault();
        location.href = active.dataset.searchHref;
      }
    } else if (event.key === 'Escape') {
      closeGlobalSearch();
      input.blur();
    }
  });

  box.addEventListener('click', event => {
    const row = event.target.closest('[data-search-href]');
    if (!row) return;
    location.href = row.dataset.searchHref;
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('.global-search-wrap')) closeGlobalSearch();
  });
}

/* ---------------------------------------------------------
   Custom dropdowns (replaces browser default select chrome)
   --------------------------------------------------------- */
function refreshCustomSelect(select) {
  if (!select) return;
  const wrap = select.closest('.cselect');
  if (!wrap) return;

  const button = wrap.querySelector('.cselect-btn');
  const menu = wrap.querySelector('.cselect-menu');
  if (!button || !menu) return;

  const options = Array.from(select.options);
  const selected = options.find(option => option.value === select.value) || options[0];
  button.querySelector('span').textContent = selected
    ? (selected.value === '' ? (selected.textContent.trim() || 'Choose…') : selected.textContent.trim())
    : 'Choose…';
  button.disabled = select.disabled;
  button.setAttribute('aria-disabled', String(select.disabled));

  menu.innerHTML = options.map(option => `
    <button type="button" class="cselect-option${option.value === select.value ? ' is-selected' : ''}" role="option" aria-selected="${option.value === select.value}" data-value="${esc(option.value)}">
      ${esc(option.textContent.trim())}
      ${option.value === select.value ? '<svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>' : ''}
    </button>`).join('');
}

function closeAllCustomSelects() {
  document.querySelectorAll('.cselect.is-open').forEach(wrap => {
    wrap.classList.remove('is-open', 'is-up');
    wrap.querySelector('.cselect-btn')?.setAttribute('aria-expanded', 'false');
  });
}

function enhanceCustomSelect(select) {
  if (!select || select.dataset.customSelect === '1') return;
  if (select.multiple || select.dataset.native === '1') return;
  if (select.hidden || select.dataset.chips === '1') return;
  if (select.closest('.cselect')) return;

  const wrap = document.createElement('div');
  wrap.className = 'cselect';
  select.parentNode.insertBefore(wrap, select);
  wrap.appendChild(select);

  select.dataset.customSelect = '1';
  select.classList.add('cselect-native');
  select.setAttribute('tabindex', '-1');
  if (select.required) {
    select.dataset.required = '1';
    select.removeAttribute('required');
  }

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'cselect-btn';
  button.setAttribute('aria-haspopup', 'listbox');
  button.setAttribute('aria-expanded', 'false');
  button.innerHTML = '<span></span><svg class="icon cselect-chevron" aria-hidden="true"><use href="#i-chevron-right"/></svg>';

  const menu = document.createElement('div');
  menu.className = 'cselect-menu';
  menu.setAttribute('role', 'listbox');

  wrap.appendChild(button);
  wrap.appendChild(menu);

  button.addEventListener('click', event => {
    event.stopPropagation();
    const willOpen = !wrap.classList.contains('is-open');
    closeAllCustomSelects();
    wrap.classList.toggle('is-open', willOpen);
    button.setAttribute('aria-expanded', String(willOpen));
    if (willOpen) {
      // Flip the menu upwards when it would otherwise be clipped by the
      // bottom of a modal or the viewport.
      const buttonRect = button.getBoundingClientRect();
      const menuRect = menu.getBoundingClientRect();
      const openUp = buttonRect.bottom + menuRect.height + 12 > window.innerHeight
        && buttonRect.top - menuRect.height - 12 > 8;
      wrap.classList.toggle('is-up', openUp);
      menu.querySelector('.is-selected')?.scrollIntoView({ block: 'nearest' });
    }
  });

  menu.addEventListener('click', event => {
    const option = event.target.closest('[data-value]');
    if (!option) return;
    select.value = option.dataset.value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    refreshCustomSelect(select);
    closeAllCustomSelects();
    button.focus({ preventScroll: true });
  });

  button.addEventListener('keydown', event => {
    const options = Array.from(menu.querySelectorAll('[data-value]'));
    if (!options.length) return;
    const currentIndex = options.findIndex(option => option.classList.contains('is-selected'));

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (!wrap.classList.contains('is-open')) {
        wrap.classList.add('is-open');
        button.setAttribute('aria-expanded', 'true');
        return;
      }
      const next = event.key === 'ArrowDown'
        ? Math.min(options.length - 1, currentIndex + 1)
        : Math.max(0, currentIndex <= 0 ? 0 : currentIndex - 1);
      options[next]?.focus();
    } else if (event.key === 'Escape') {
      closeAllCustomSelects();
    }
  });

  menu.addEventListener('keydown', event => {
    const options = Array.from(menu.querySelectorAll('[data-value]'));
    const index = options.indexOf(document.activeElement);
    if (event.key === 'Escape') {
      event.preventDefault();
      closeAllCustomSelects();
      button.focus({ preventScroll: true });
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const next = event.key === 'ArrowDown'
        ? Math.min(options.length - 1, index + 1)
        : Math.max(0, index - 1);
      options[next]?.focus();
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      document.activeElement?.click();
    }
  });

  select.addEventListener('change', () => refreshCustomSelect(select));
  refreshCustomSelect(select);
}

function enhanceAllCustomSelects(root = document) {
  root.querySelectorAll('select').forEach(enhanceCustomSelect);
}

function bindCustomSelects() {
  document.addEventListener('click', event => {
    if (!event.target.closest('.cselect')) closeAllCustomSelects();
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeAllCustomSelects();
  });

  // Reset events change the native select without firing "change", so the
  // visible custom dropdown has to be refreshed afterwards.
  document.addEventListener('reset', event => {
    const form = event.target;
    setTimeout(() => form?.querySelectorAll?.('select').forEach(refreshCustomSelect), 0);
  }, true);

  if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(mutations => {
      mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
          if (node.nodeType !== 1) return;
          if (node.matches?.('select')) enhanceCustomSelect(node);
          node.querySelectorAll?.('select').forEach(enhanceCustomSelect);
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  enhanceAllCustomSelects();
}

/* ---------------------------------------------------------
   Friendly tooltips for every titled control
   --------------------------------------------------------- */
let uiTip = null;
let uiTipTarget = null;

function hideUiTip() {
  if (uiTipTarget && uiTipTarget.dataset.tipTitle) {
    uiTipTarget.setAttribute('title', uiTipTarget.dataset.tipTitle);
    delete uiTipTarget.dataset.tipTitle;
  }
  if (uiTip) uiTip.hidden = true;
  uiTipTarget = null;
}

function showUiTip(target) {
  const label = target.getAttribute('title') || target.getAttribute('data-tip') || target.getAttribute('aria-label');
  if (!label) return;

  // Icon-only controls borrow their accessible name; labelled controls already show it.
  const hasExplicitTip = target.hasAttribute('title') || target.hasAttribute('data-tip');
  if (!hasExplicitTip && ((target.textContent || '').trim() || !target.querySelector('.icon'))) return;

  if (!uiTip) {
    uiTip = document.createElement('div');
    uiTip.className = 'ui-tip';
    uiTip.setAttribute('role', 'tooltip');
    uiTip.hidden = true;
    document.body.appendChild(uiTip);
  }

  if (target.hasAttribute('title')) {
    target.dataset.tipTitle = target.getAttribute('title');
    target.removeAttribute('title');
  }

  uiTipTarget = target;
  uiTip.textContent = label;
  uiTip.hidden = false;

  const rect = target.getBoundingClientRect();
  const tipRect = uiTip.getBoundingClientRect();
  let left = rect.left + rect.width / 2 - tipRect.width / 2;
  left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));
  let top = rect.top - tipRect.height - 10;
  if (top < 8) top = rect.bottom + 10;
  uiTip.style.left = left + 'px';
  uiTip.style.top = top + 'px';
}

function bindTooltips() {
  document.addEventListener('mouseover', event => {
    if (window.matchMedia('(hover: none)').matches) return;
    const target = event.target.closest('[title], [data-tip], [aria-label]');
    if (!target || target.closest('.sidebar')) return;
    if (uiTipTarget === target) return;
    hideUiTip();
    showUiTip(target);
  });

  document.addEventListener('mouseout', event => {
    const target = event.target.closest('[title], [data-tip], [aria-label]');
    if (!target) return;
    if (target.contains(event.relatedTarget)) return;
    if (uiTipTarget === target) hideUiTip();
  });

  document.addEventListener('focusin', event => {
    const target = event.target.closest('[title], [data-tip], [aria-label]');
    if (!target || target.closest('.sidebar')) return;
    showUiTip(target);
  });

  document.addEventListener('focusout', () => hideUiTip());
  window.addEventListener('scroll', () => hideUiTip(), { passive: true });
  window.addEventListener('resize', () => hideUiTip());
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
    const isWelcome = payload.kind === 'welcome';

    if (isWelcome) {
      return `
        <article class="notification-row welcome-row ${item.read_at ? '' : 'unread'}">
          <div class="notification-copy">
            <strong>${esc(payload.title || 'Welcome to MediByte')}</strong>
            <p>${esc(payload.body || '')}</p>
            <small>${esc(relativeTime(item.created_at))}</small>
          </div>
          <button type="button" class="notification-dismiss" data-dismiss-notification="${esc(item.id)}" aria-label="Close welcome message" title="Close">
            <svg class="icon"><use href="#i-close"/></svg>
          </button>
        </article>`;
    }

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

async function dismissNotificationFromDrawer(id) {
  const result = await api('/notifications/' + encodeURIComponent(id), 'DELETE');
  if (result && result.message === 'Dismissed') {
    notificationCache = notificationCache.filter(item => String(item.id) !== String(id));
    renderNotificationDrawer();
    updateNotificationBadges(notificationCache);
    if (typeof window.loadNotificationsPage === 'function') window.loadNotificationsPage();
    toast('Notification dismissed.');
  } else {
    toast(errText(result), 'error');
  }
}

function bindShell() {
  const drawerList = $('drawerList');
  if (drawerList) {
    drawerList.addEventListener('click', event => {
      const dismissButton = event.target.closest('[data-dismiss-notification]');
      if (dismissButton) {
        dismissNotificationFromDrawer(dismissButton.dataset.dismissNotification);
        return;
      }
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
    else if ($('quickLogModal') && !$('quickLogModal').hidden) closeQuickLog();
    else if ($('globalSearchResults') && !$('globalSearchResults').hidden) closeGlobalSearch();
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
  bindGlobalSearch();
  bindTooltips();
  bindCustomSelects();
  syncThemeButton();

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

  keepSidebarScroll();

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
  guilty: '😔', embarrassed: '😳', scared: '😨', numb: '😶',
  sleepy: '😴', curious: '🤔', confident: '😎', worried: '😟',
  restless: '😖', drained: '🫠', peaceful: '😇', playful: '😜',
  disgusted: '🤢', regretful: '😞'
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
  let activeDateFilter = null;
  let showAll = false;

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

  // Search results and shared links can open a feature already filtered to a day.
  const initialDate = new URLSearchParams(location.search).get('date');
  if (initialDate && /^\d{4}-\d{2}-\d{2}$/.test(initialDate)) {
    activeDateFilter = initialDate;
    calSel = initialDate;
    const picked = new Date(initialDate + 'T00:00:00');
    calYear = picked.getFullYear();
    calMonth = picked.getMonth();
  }

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

    const cell = (key, day, muted) => {
      const list = map[key];
      const emoji = list ? moodEmoji(list[list.length - 1]) : '';
      const classes = ['cal-day'];
      if (muted) classes.push('muted');
      if (moodMode) classes.push('mood-cell');
      if (list && !moodMode) classes.push('on');
      if (key === t) classes.push('today');
      if (key === calSel) classes.push('sel');
      const label = key + (list ? ', ' + list.length + (list.length === 1 ? ' record' : ' records') : '');
      const face = moodMode
        ? `<b>${day}</b>${emoji ? `<i aria-hidden="true">${emoji}</i>` : (list ? '<i aria-hidden="true">•</i>' : '')}`
        : `<span>${day}</span>`;
      return `<button type="button" class="${classes.join(' ')}" onclick="window.calPick('${key}')" aria-pressed="${key === calSel}" aria-label="${esc(label)}">${face}</button>`;
    };

    const firstOfMonth = new Date(calYear, calMonth, 1);
    const lead = firstOfMonth.getDay();
    const total = new Date(calYear, calMonth + 1, 0).getDate();
    const monthTitle = firstOfMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    const selected = new Date(calSel + 'T00:00:00');

    let cells = '';
    for (let i = lead; i > 0; i--) {
      const d = new Date(calYear, calMonth, 1 - i);
      cells += cell(dayKey(d.getFullYear(), d.getMonth(), d.getDate()), d.getDate(), true);
    }
    for (let d = 1; d <= total; d++) {
      cells += cell(dayKey(calYear, calMonth, d), d, false);
    }
    let filled = lead + total;
    while (filled % 7 !== 0 && filled < 42) {
      const d = new Date(calYear, calMonth + 1, filled - lead - total + 1);
      cells += cell(dayKey(d.getFullYear(), d.getMonth(), d.getDate()), d.getDate(), true);
      filled++;
    }

    host.innerHTML = `
      <div class="cal-widget">
        <div class="cal-widget-top">
          <div class="cal-selected">
            <strong>${selected.getDate()}</strong>
            <span>${esc(monthTitle)}</span>
          </div>
          <div class="cal-head-actions">
            <button type="button" class="btn btn-small cal-nav" onclick="window.calMove(-1)" aria-label="Previous month" title="Previous month">
              <svg class="icon"><use href="#i-chevron-left"/></svg>
            </button>
            <button type="button" class="btn btn-small cal-today" onclick="window.calToday()">Today</button>
            <button type="button" class="btn btn-small cal-nav" onclick="window.calMove(1)" aria-label="Next month" title="Next month">
              <svg class="icon"><use href="#i-chevron-right"/></svg>
            </button>
          </div>
        </div>
        <div class="cal-grid">
          ${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(x => `<span class="cal-dow" aria-hidden="true">${x}</span>`).join('')}
          ${cells}
        </div>
      </div>`;
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
    activeDateFilter = day;
    window.closeLogCalendar();
    draw();
    const card = document.querySelector('.history-card');
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const tone = cfg.tone || 'blue';
  const icon = cfg.icon || 'file';
  document.getElementById('main')?.classList.add('tone-' + tone);

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
      html = switchRowHtml(id, safeLabel, v);
    } else if (type === 'method') {
      const pairs = Array.isArray(opts) ? opts : [];
      const selected = v === undefined || v === null ? (pairs[0] ? pairs[0][0] : '') : v;
      html = `<label class="field" data-grid-full="1"><span>${safeLabel}</span><select id="${id}">${pairs.map(([val, text]) => `<option value="${esc(String(val))}" ${String(val) === String(selected) ? 'selected' : ''}>${esc(String(text))}</option>`).join('')}</select></label>`;
    } else if (type === 'select') {
      if (name === 'mood') {
        const moodNames = [...(opts || [])];

        if (v && !moodNames.some(mood => String(mood) === String(v))) {
          moodNames.push(v);
        }

        return `<label class="field" data-grid-full="1"><span>${safeLabel}</span><select id="${id}">${moodOptionHtml(moodNames, v)}</select></label>`;
      }

      const options = (opts || []).map(o => {
        const optionValue = String(o ?? '');
        const text = optionValue === '' ? 'Choose…' : optionLabel(optionValue);
        return `<option value="${esc(optionValue)}" ${String(o) === String(v) ? 'selected' : ''}>${esc(text)}</option>`;
      });

      html = `<label class="field"><span>${safeLabel}</span><select id="${id}">${options.join('')}</select></label>`;
    } else if (type === 'textarea') {
      html = `<label class="field diary-field${name === 'event_note' ? ' note-field' : ''}" data-grid-full="1"><span>${safeLabel}</span><textarea id="${id}" rows="${name === 'event_note' ? 3 : 7}" placeholder="${esc(placeholder || '')}">${esc(v ?? '')}</textarea></label>`;
    } else if (name === 'intensity' || type === 'intensity' || type === 'slider') {
      return intensitySliderHtml(id, safeLabel, v ?? def ?? 3);
    } else {
      const htmlType = type === 'list' ? 'text' : type === 'today' ? 'date' : type;
      const maxAttr = type === 'today' || opts === 'today' ? ` max="${today()}"` : '';
      const listAttr = type === 'list' ? ' data-grid-full="1"' : '';
      html = `<label class="field"${listAttr}><span>${safeLabel}</span><input id="${id}" type="${htmlType}" step="any"${maxAttr} value="${esc(v ?? '')}" placeholder="${esc(placeholder || '')}"></label>`;
    }

    if (showsWhen) {
      const show = String((values || {})[showsWhen[0]] ?? '') === String(showsWhen[1]);
      html = `<div data-shows-when="${esc(showsWhen[0])}:${esc(showsWhen[1])}"${show ? '' : ' hidden'}>${html}</div>`;
    }

    return html;
  }

  function syncMethodFields() {
    bindFieldConditionals($('log'), 'f_', $('log')?.querySelector('.form-grid'));
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

  function overlaps(row, from, to) {
    const first = dateKey && row[dateKey] ? ld(row[dateKey]) : '';
    if (!first) return false;
    let last = endKey && row[endKey] ? ld(row[endKey]) : first;
    if (!last || last < first) last = first;
    return to >= first && from <= last;
  }

  const weekStart = addDays(today(), -6);

  // Long histories start on the current week so the list stays readable.
  function recentOnly() {
    if (activeDateFilter || showAll || !dateKey) return false;
    if (rows.length <= WEEK_RECORD_THRESHOLD) return false;
    return rows.some(row => overlaps(row, weekStart, today()));
  }

  function filteredRows() {
    if (activeDateFilter) return rows.filter(row => overlaps(row, activeDateFilter, activeDateFilter));
    if (recentOnly()) return rows.filter(row => overlaps(row, weekStart, today()));
    return rows;
  }

  function historyCardHtml() {
    const visibleRows = filteredRows();
    const filterLabel = activeDateFilter ? dateLabel(activeDateFilter) : '';

    const head = `
      <div class="card-head">
        <div>
          <h3>Records</h3>
        </div>
        <div class="card-head-tools">
          ${cfg.url === '/mood-logs' ? `<button type="button" class="btn btn-small btn-ghost" onclick="window.openMoodPersonalize()">
            <svg class="icon"><use href="#i-smile"/></svg>Personalize
          </button>` : ''}
          ${showCalendar ? `<button type="button" class="btn btn-small btn-ghost" onclick="window.openLogCalendar()">
            <svg class="icon"><use href="#i-calendar"/></svg>View calendar
          </button>` : ''}
          <span class="history-count">${visibleRows.length}</span>
        </div>
      </div>`;

    const weekMode = recentOnly();

    const filterBar = activeDateFilter ? `
      <div class="records-filter-bar">
        <span>Showing records for <strong>${esc(filterLabel)}</strong></span>
        <button type="button" class="btn btn-small btn-ghost" onclick="window.clearDateFilter()">
          <svg class="icon"><use href="#i-close"/></svg>Show all
        </button>
      </div>` : weekMode ? `
      <div class="records-filter-bar">
        <span>Showing <strong>this week</strong> · ${rows.length} records in total</span>
        <button type="button" class="btn btn-small btn-ghost" onclick="window.showAllLogRecords()">
          <svg class="icon"><use href="#i-calendar"/></svg>Show all
        </button>
      </div>` : showAll && dateKey && rows.length > WEEK_RECORD_THRESHOLD ? `
      <div class="records-filter-bar">
        <span>Showing <strong>all records</strong></span>
        <button type="button" class="btn btn-small btn-ghost" onclick="window.showWeekLogRecords()">
          <svg class="icon"><use href="#i-calendar"/></svg>This week
        </button>
      </div>` : '';

    if (!visibleRows.length) {
      return `<section class="card history-card">
        ${head}
        ${filterBar}
        <div class="empty-state">
          <span class="empty-orb"><svg class="icon"><use href="#i-${esc(icon)}"/></svg></span>
          <h4>${activeDateFilter ? 'No records on this date' : 'No records yet'}</h4>
          <p>${activeDateFilter ? 'There are no entries logged for this day. Add one below.' : 'Add your first entry and it will appear here.'}</p>
          <button type="button" class="btn btn-primary" onclick="window.addLogRecord()">
            <svg class="icon"><use href="#i-plus"/></svg>
            Add record
          </button>
        </div>
      </section>`;
    }

    return `<section class="card history-card">
      ${head}
      ${filterBar}
      <div class="record-list">${visibleRows.map(rowHtml).join('')}</div>
    </section>`;
  }

  window.clearDateFilter = () => {
    activeDateFilter = null;
    draw();
  };

  window.showAllLogRecords = () => {
    showAll = true;
    draw();
  };

  window.showWeekLogRecords = () => {
    showAll = false;
    draw();
  };

  function moodPersonalizeHtml() {
    const list = customMoods.length
      ? `<div class="record-list custom-mood-list">
          ${customMoods.map(cm => `
            <div class="record">
              <span class="record-icon tone-rose"><svg class="icon"><use href="#i-smile"/></svg></span>
              <div class="record-main">
                <strong>${esc(cm.name)}</strong>
              </div>
              <div class="record-actions">
                <button type="button" class="record-row-action" onclick="window.renameCustomMood(event, ${Number(cm.id)})" aria-label="Rename custom mood" title="Rename custom mood">
                  <svg class="icon"><use href="#i-pencil"/></svg>
                </button>
                <button type="button" class="record-row-action danger" onclick="window.deleteCustomMood(event, ${Number(cm.id)})" aria-label="Delete custom mood" title="Delete custom mood">
                  <svg class="icon"><use href="#i-trash"/></svg>
                </button>
              </div>
            </div>
          `).join('')}
        </div>`
      : `<div class="empty-state empty-state-compact">
          <span class="empty-orb"><svg class="icon"><use href="#i-smile"/></svg></span>
          <h4>No custom moods yet</h4>
          <p>Add a mood that feels like yours. It will appear in the mood form and calendar.</p>
        </div>`;

    return `
      <div id="moodPersonalizeModal" class="log-modal" hidden>
        <div class="log-modal-backdrop" onclick="window.closeMoodPersonalize()"></div>
        <div class="log-modal-dialog glassy" role="dialog" aria-modal="true" aria-labelledby="moodPersonalizeTitle">
          <header class="log-modal-head">
            <div>
              <p class="eyebrow">Personalize</p>
              <h3 id="moodPersonalizeTitle">Your custom moods</h3>
            </div>
            <button type="button" class="icon-btn" onclick="window.closeMoodPersonalize()" aria-label="Close personalization" title="Close">
              <svg class="icon"><use href="#i-close"/></svg>
            </button>
          </header>
          <div class="log-modal-body">
            <form class="custom-mood-form" onsubmit="event.preventDefault(); window.savePersonalizedMood()">
              <label class="field">
                <span>New custom mood</span>
                <input id="new_custom_mood" type="text" maxlength="50" placeholder="e.g. Overstimulated" autocomplete="off">
              </label>
              <button type="submit" class="btn btn-primary">
                <svg class="icon"><use href="#i-plus"/></svg>
                Save mood
              </button>
            </form>
            <p id="personalizeMsg" class="form-message" role="alert"></p>
            ${list}
          </div>
        </div>
      </div>`;
  }

  function formModalHtml(form) {
    const actions = `
      <div class="form-grid-action">
        <button type="submit" class="btn btn-primary">
          <svg class="icon"><use href="#i-${edit ? 'check' : 'plus'}"/></svg>
          ${edit ? 'Save changes' : 'Add record'}
        </button>
        ${edit ? '<button type="button" class="btn btn-ghost" onclick="window.cancelLogEdit()">Cancel</button>' : ''}
      </div>`;

    return `
      <div id="logFormModal" class="log-modal" hidden>
        <div class="log-modal-backdrop" onclick="window.closeLogForm()"></div>
        <div class="log-modal-dialog log-form-dialog glassy" role="dialog" aria-modal="true" aria-labelledby="logFormTitle">
          <header class="log-modal-head">
            <div>
              <p class="eyebrow">${edit ? 'Editing record' : 'New record'}</p>
              <h3 id="logFormTitle">${edit ? 'Update this entry' : (cfg.formTitle || 'Add a new entry')}</h3>
            </div>
            ${edit ? '' : `<button type="button" class="icon-btn" onclick="window.closeLogForm()" aria-label="Close form">
              <svg class="icon"><use href="#i-close"/></svg>
            </button>`}
          </header>
          <div class="log-modal-body">
            <p class="form-card-note">${esc(cfg.formNote || 'Fields left blank are saved as empty. Only the required details are needed.')}</p>
            <p id="formMsg" class="form-message" role="alert" style="display:none"></p>
            <form onsubmit="event.preventDefault(); window.saveLog()">
              <div class="form-grid">${form}${actions}</div>
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
              <p class="eyebrow">${esc(cfg.title || 'Records')}</p>
              <h3 id="logCalendarTitle">Calendar</h3>
            </div>
            <button type="button" class="icon-btn" onclick="window.closeLogCalendar()" aria-label="Close calendar" title="Close">
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
      ${historyCardHtml()}
      ${cfg.url === '/mood-logs' ? moodPersonalizeHtml() : ''}
      ${rows.length ? `<button type="button" id="logFab" class="fab" onclick="window.addLogRecord()" aria-label="Add record" title="Add record">
        <svg class="icon"><use href="#i-plus"/></svg>
      </button>` : ''}
      ${formModalHtml(form)}
      ${showCalendar ? calendarModalHtml() : ''}`;

    if (showCalendar) drawCalendar();

    syncMethodFields();

    if (edit && typeof cfg.methodInit === 'function') {
      try { cfg.methodInit(edit); } catch (error) { /* optional hook */ }
      syncMethodFields();
    }
  }

  async function reloadCustomMoods() {
    if (cfg.url !== '/mood-logs') return;

    const cms = await api('/custom-moods');
    customMoods = Array.isArray(cms) ? cms : (cms.data || []);
    rememberPersonalizedMoods(customMoods);

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
    if (activeDateFilter) {
      calSel = activeDateFilter;
      const picked = new Date(calSel + 'T00:00:00');
      calYear = picked.getFullYear();
      calMonth = picked.getMonth();
    }
    modal.hidden = false;
    document.body.classList.add('log-modal-open');
    drawCalendar();
    setTimeout(() => modal.querySelector('.icon-btn')?.focus({ preventScroll: true }), 80);
  };

  window.closeLogCalendar = () => {
    const modal = $('logCalendarModal');
    if (modal) modal.hidden = true;
    if (!$('logFormModal') || $('logFormModal').hidden) {
      document.body.classList.remove('log-modal-open');
    }
  };

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if ($('moodPersonalizeModal') && !$('moodPersonalizeModal').hidden) window.closeMoodPersonalize();
    else if ($('logCalendarModal') && !$('logCalendarModal').hidden) window.closeLogCalendar();
    else if ($('logFormModal') && !$('logFormModal').hidden) window.closeLogForm();
  });

  window.addLogRecord = () => {
    if (edit) {
      edit = null;
      msg('');
      draw();
    }
    window.openLogForm();

    if (activeDateFilter && dateKey) {
      const dateInput = $('f_' + dateKey);
      if (dateInput) dateInput.value = activeDateFilter;
    }
  };

  window.addCustomMood = () => {
    window.openMoodPersonalize();
  };

  window.openMoodPersonalize = () => {
    const modal = $('moodPersonalizeModal');
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add('log-modal-open');
    const message = $('personalizeMsg');
    if (message) message.textContent = '';
    setTimeout(() => $('new_custom_mood')?.focus({ preventScroll: true }), 80);
  };

  window.closeMoodPersonalize = () => {
    const modal = $('moodPersonalizeModal');
    if (modal) modal.hidden = true;
    if ((!$('logFormModal') || $('logFormModal').hidden) && (!$('logCalendarModal') || $('logCalendarModal').hidden)) {
      document.body.classList.remove('log-modal-open');
    }
  };

  window.savePersonalizedMood = async () => {
    const input = $('new_custom_mood');
    const name = input?.value.trim();
    const message = $('personalizeMsg');

    if (!name) {
      if (message) message.textContent = 'Enter a name for your custom mood.';
      input?.focus();
      return;
    }

    const result = await api('/custom-moods', 'POST', { name });
    if (result.id) {
      toast('Custom mood saved.');
      if (input) input.value = '';
      await reloadCustomMoods();
      draw();
      window.openMoodPersonalize();
    } else {
      const error = errText(result);
      if (message) message.textContent = error;
      toast(error, 'error');
    }
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
    const newName = await promptText({
      eyebrow: 'Personalize',
      title: 'Rename custom mood',
      label: 'Mood name',
      placeholder: 'e.g. Overstimulated',
      value: oldName,
      note: 'Records you already logged keep this mood.',
      confirmLabel: 'Save',
      emptyText: 'Please enter a mood name.'
    });

    if (!newName || newName === oldName) return;

    const personalizeOpen = $('moodPersonalizeModal') && !$('moodPersonalizeModal').hidden;
    const res = await api('/custom-moods/' + id, 'PUT', { name: newName.trim() });
    if (res.id) {
      toast('Custom mood renamed.');
      await reloadCustomMoods();
      draw();
      if (personalizeOpen) window.openMoodPersonalize();
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

    const personalizeOpen = $('moodPersonalizeModal') && !$('moodPersonalizeModal').hidden;
    const res = await api('/custom-moods/' + id, 'DELETE');
    if (res.message) {
      toast('Custom mood deleted.');
      await reloadCustomMoods();
      draw();
      if (personalizeOpen) window.openMoodPersonalize();
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

    const data = await api(cfg.url + (edit ? '/' + edit.id : ''), edit ? 'PUT' : 'POST', body);
    if (data.id) {
      const wasEditing = Boolean(edit);
      edit = null;
      msg('');
      toast(wasEditing ? 'Changes saved.' : 'Record added.');
      window.closeLogForm();
      await load();
      window.scrollTo({ top: 0, behavior: 'smooth' });
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

  window.__medibyteReloadLog = load;

  load();
}
