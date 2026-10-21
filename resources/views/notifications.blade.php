@extends('layouts.app')

@section('title', 'Notifications · MediByte')

@section('content')
  <div class="page-heading">
    <div class="heading-main">
      <a class="back-link" href="/dashboard" aria-label="Back to dashboard">
        <svg class="icon"><use href="#i-chevron-left"/></svg>
      </a>
      <div>
        <p class="eyebrow">Inbox</p>
        <h2>Notifications</h2>
        <p>All reminders and account updates, gathered in one quiet inbox.</p>
      </div>
    </div>
    <button type="button" class="btn btn-ghost" onclick="markAllRead()">
      <svg class="icon"><use href="#i-check"/></svg>
      Mark all read
    </button>
  </div>

  <p id="msg" class="form-message" role="alert"></p>

  <section class="card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Inbox</p>
        <h3 id="notification-summary">Loading…</h3>
      </div>
      <span class="history-count" id="notification-count">0</span>
    </div>

    <div class="notification-filters" role="tablist" aria-label="Filter notifications">
      <button type="button" class="notification-filter active" data-filter="all" onclick="setNotificationFilter('all')">All</button>
      <button type="button" class="notification-filter" data-filter="unread" onclick="setNotificationFilter('unread')">Unread</button>
    </div>

    <div id="notifications-list">
      <p class="muted">Loading…</p>
    </div>
  </section>
@endsection

@push('scripts')
<script>
let notificationRows = [];
let notificationFilter = 'all';

function notificationTypeIcon(item) {
  const url = String(item?.data?.url || '');
  if (url.includes('medication')) return 'pill';
  if (url.includes('appointment')) return 'calendar';
  return 'bell';
}

function renderNotifications() {
  const list = $('notifications-list');
  const unread = notificationRows.filter(item => !item.read_at).length;

  $('notification-summary').textContent = unread ? unread + ' unread' : 'You are all caught up';
  $('notification-count').textContent = notificationRows.length;

  const visible = notificationFilter === 'unread'
    ? notificationRows.filter(item => !item.read_at)
    : notificationRows;

  if (!visible.length) {
    list.innerHTML = `<div class="empty-state">
      <span class="empty-orb"><svg class="icon"><use href="#i-bell"/></svg></span>
      <h4>${notificationFilter === 'unread' ? 'Nothing unread' : 'No notifications yet'}</h4>
      <p>${notificationFilter === 'unread' ? 'You have read everything in your inbox.' : 'Reminders will appear here when they are due.'}</p>
    </div>`;
    return;
  }

  list.innerHTML = '<div class="record-list">' + visible.map(item => {
    const payload = item.data || {};
    return `
      <div class="record ${item.read_at ? '' : 'record-unread'}">
        <span class="record-icon"><svg class="icon"><use href="#i-${notificationTypeIcon(item)}"/></svg></span>
        <div class="record-main">
          <strong>${esc(payload.title || 'Reminder')}${item.read_at ? '' : ' · New'}</strong>
          <span class="record-meta">${esc(payload.body || '')}</span>
          <span class="record-meta">${esc(relativeTime(item.created_at))}</span>
        </div>
        <div class="record-actions">
          ${payload.url ? `<a class="record-row-action" href="${esc(payload.url)}" aria-label="Open notification"><svg class="icon"><use href="#i-arrow"/></svg></a>` : ''}
          ${item.read_at ? '' : `<button type="button" class="record-row-action" onclick="markRead('${esc(item.id)}')" aria-label="Mark as read"><svg class="icon"><use href="#i-check"/></svg></button>`}
        </div>
      </div>
    `;
  }).join('') + '</div>';
}

function setNotificationFilter(filter) {
  notificationFilter = filter;
  document.querySelectorAll('.notification-filter').forEach(button => {
    button.classList.toggle('active', button.dataset.filter === filter);
  });
  renderNotifications();
}

async function loadNotificationsPage() {
  const data = await api('/notifications');
  notificationRows = Array.isArray(data) ? data : [];
  renderNotifications();
}

async function markRead(id) {
  const result = await api('/notifications/' + encodeURIComponent(id) + '/read', 'POST');

  if (result && result.message === 'Read') {
    await loadNotificationsPage();
    refreshNotificationBadge();
    toast('Notification marked as read.');
  } else {
    $('msg').textContent = errText(result);
  }
}

async function markAllRead() {
  const result = await api('/notifications/read-all', 'POST');

  if (result && result.message === 'All read') {
    $('msg').textContent = '';
    toast('All notifications marked as read.');
    await loadNotificationsPage();
    refreshNotificationBadge();
  } else {
    $('msg').textContent = errText(result);
  }
}

window.loadNotificationsPage = loadNotificationsPage;
loadNotificationsPage();
</script>
@endpush
