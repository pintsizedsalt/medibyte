<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#ffffff">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="MediByte">
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'MediByte')</title>
  <link rel="stylesheet" href="/css/medibyte.css?v=2">
  @stack('styles')
</head>
<body class="@yield('body-class')">
  <script>
    try {
      if (sessionStorage.getItem('medibyte-sidebar') === 'hidden') {
        document.body.classList.add('sidebar-collapsed');
      }
    } catch (error) {}
  </script>
  <svg class="svg-sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
    <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/></symbol>
    <symbol id="i-droplet" viewBox="0 0 24 24"><path d="M12 3s6 6.2 6 10a6 6 0 0 1-12 0c0-3.8 6-10 6-10Z"/></symbol>
    <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/></symbol>
    <symbol id="i-activity" viewBox="0 0 24 24"><path d="M3 12h4l2-6 4 12 2-6h6"/></symbol>
    <symbol id="i-meal" viewBox="0 0 24 24"><path d="M7 3v8a2 2 0 0 0 2 2v8"/><path d="M4 3v5a3 3 0 0 0 6 0V3"/><path d="M17 3c-2 3-2 6 0 8v10"/></symbol>
    <symbol id="i-scale" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="4"/><path d="M8 10a4 4 0 0 1 8 0"/><path d="m12 10 2-2"/></symbol>
    <symbol id="i-smile" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 0 0 7 0"/><path d="M9 9.5h.01M15 9.5h.01"/></symbol>
    <symbol id="i-thermometer" viewBox="0 0 24 24"><path d="M14 14.8V5a2 2 0 1 0-4 0v9.8a4 4 0 1 0 4 0Z"/><path d="M12 11v6"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24"><path d="M20.8 5.8a5 5 0 0 0-7.1 0L12 7.5l-1.7-1.7a5 5 0 0 0-7.1 7.1L12 21l8.8-8.1a5 5 0 0 0 0-7.1Z"/></symbol>
    <symbol id="i-cycle" viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14-4.5L4 9"/><path d="M4 4v5h5"/><path d="M4 13a8 8 0 0 0 14 4.5L20 15"/><path d="M20 20v-5h-5"/></symbol>
    <symbol id="i-pill" viewBox="0 0 24 24"><rect x="3.2" y="8.5" width="17.6" height="7" rx="3.5" transform="rotate(-45 12 12)"/><path d="m9 9 6 6"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></symbol>
    <symbol id="i-settings" viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="M10 4H5v16h5"/><path d="m14 8 4 4-4 4"/><path d="M18 12H9"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-pencil" viewBox="0 0 24 24"><path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M9 7V5h6v2"/><path d="m6 7 1 13h10l1-13"/><path d="M10 11v5M14 11v5"/></symbol>
    <symbol id="i-chevron-left" viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></symbol>
    <symbol id="i-chevron-right" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6Z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></symbol>
  </svg>

  <div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Primary navigation">
      <div class="sidebar-head">
        <a class="brand" href="/dashboard">
          <strong>MediByte</strong>
          <small>Personal health</small>
        </a>
        <button type="button" class="icon-btn sidebar-close" onclick="closeSidebar()" aria-label="Hide sidebar" title="Hide sidebar">
          <svg class="icon"><use href="#i-chevron-left"/></svg>
        </button>
      </div>

      <a class="sidebar-profile" href="/profile" aria-label="Open your profile">
        <span class="avatar avatar-sm" id="sideUserAvatar" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()?->first_name ?? auth()->user()?->name ?? 'M', 0, 1)) }}</span>
        <span class="side-user-copy">
          <strong id="sideUserName">{{ auth()->user()?->name ?? 'MediByte' }}</strong>
          <small id="sideUserHandle">{{ auth()->user()?->username ? '@'.auth()->user()?->username : 'Private account' }}</small>
        </span>
        <span class="sidebar-profile-link">Profile</span>
      </a>

      <nav class="side-nav">
        <p class="nav-label">Overview</p>
        <a class="side-link" data-nav href="/dashboard"><svg class="icon"><use href="#i-home"/></svg><span>Dashboard</span></a>
        <a class="side-link" data-nav href="/records"><svg class="icon"><use href="#i-file"/></svg><span>All records</span></a>

        <p class="nav-label">Everyday</p>
        <a class="side-link" data-nav href="/water"><svg class="icon"><use href="#i-droplet"/></svg><span>Water</span></a>
        <a class="side-link" data-nav href="/sleep"><svg class="icon"><use href="#i-moon"/></svg><span>Sleep</span></a>
        <a class="side-link" data-nav href="/walking"><svg class="icon"><use href="#i-activity"/></svg><span>Activity</span></a>
        <a class="side-link" data-nav href="/meals"><svg class="icon"><use href="#i-meal"/></svg><span>Meals</span></a>

        <p class="nav-label">Wellbeing</p>
        <a class="side-link" data-nav href="/weight"><svg class="icon"><use href="#i-scale"/></svg><span>Weight &amp; BMI</span></a>
        <a class="side-link" data-nav href="/mood"><svg class="icon"><use href="#i-smile"/></svg><span>Mood</span></a>
        <a class="side-link" data-nav href="/symptoms"><svg class="icon"><use href="#i-thermometer"/></svg><span>Symptoms</span></a>
        <a class="side-link" data-nav href="/vitals"><svg class="icon"><use href="#i-heart"/></svg><span>Vitals</span></a>
        <a class="side-link" data-cycle data-nav href="/period" hidden><svg class="icon"><use href="#i-cycle"/></svg><span>Cycle</span></a>

        <p class="nav-label">Care</p>
        <a class="side-link" data-nav href="/medications"><svg class="icon"><use href="#i-pill"/></svg><span>Medications</span></a>
        <a class="side-link" data-nav href="/appointments"><svg class="icon"><use href="#i-calendar"/></svg><span>Appointments</span></a>
        <a class="side-link" data-nav href="/documents"><svg class="icon"><use href="#i-file"/></svg><span>Documents</span></a>

        <p class="nav-label">Account</p>
        <a class="side-link" data-nav href="/settings"><svg class="icon"><use href="#i-settings"/></svg><span>Settings</span></a>
      </nav>

      <div class="sidebar-foot">
        <a class="side-privacy" href="/privacy">Privacy notice</a>
      </div>
    </aside>

    <div class="app-main">
      <header class="app-topbar">
        <div class="topbar-left">
          <button type="button" class="icon-btn menu-btn" onclick="toggleSidebar()" aria-label="Open or hide menu" title="Open or hide menu" aria-controls="sidebar" aria-expanded="false">
            <svg class="icon"><use href="#i-menu"/></svg>
          </button>
          <a class="topbar-brand" href="/dashboard"><strong>MediByte</strong></a>
          <span class="topbar-date">{{ now()->format('D, M j') }}</span>
        </div>
        <div class="topbar-actions">
          <button type="button" class="icon-btn notif-btn" onclick="openNotificationDrawer()" aria-label="Open notifications" title="Notifications" aria-controls="notificationDrawer">
            <svg class="icon"><use href="#i-bell"/></svg>
            <span class="icon-dot" id="notifDot" hidden></span>
          </button>

          <div class="account-menu" id="accountMenu">
            <button type="button" class="avatar avatar-button" id="topbarAvatar" onclick="toggleAccountMenu(event)" aria-label="Open account menu" aria-haspopup="menu" aria-expanded="false" aria-controls="accountDropdown">
              {{ strtoupper(mb_substr(auth()->user()?->first_name ?? auth()->user()?->name ?? 'M', 0, 1)) }}
            </button>
            <div class="account-dropdown" id="accountDropdown" role="menu" hidden>
              <a href="/profile" role="menuitem">Profile</a>
              <a href="/settings" role="menuitem">Settings</a>
              <button type="button" role="menuitem" onclick="logout()">Logout</button>
            </div>
          </div>
        </div>
      </header>

      <main class="page" id="main">
        @yield('content')
      </main>

      <nav class="mobile-nav" aria-label="Mobile navigation">
        <a data-nav href="/dashboard"><svg class="icon"><use href="#i-home"/></svg><span>Home</span></a>
        <a data-nav href="/records"><svg class="icon"><use href="#i-file"/></svg><span>Records</span></a>
        <button type="button" class="mobile-add" onclick="openQuickSheet()" aria-label="Add a record">
          <svg class="icon"><use href="#i-plus"/></svg>
        </button>
        <a data-nav href="/notifications">
          <svg class="icon"><use href="#i-bell"/></svg><span>Notifications</span>
          <span class="icon-dot" id="mobileNotifDot" hidden></span>
        </a>
      </nav>
    </div>
  </div>

  <div class="scrim" id="sidebarScrim" onclick="closeSidebar()" aria-hidden="true"></div>

  <aside class="drawer" id="notificationDrawer" aria-hidden="true" aria-labelledby="drawerTitle">
    <div class="drawer-head">
      <div>
        <p class="eyebrow">Your inbox</p>
        <h2 id="drawerTitle">Notifications</h2>
      </div>
      <button type="button" class="icon-btn" onclick="closeNotificationDrawer()" aria-label="Close notifications">
        <svg class="icon"><use href="#i-close"/></svg>
      </button>
    </div>
    <div class="drawer-toolbar">
      <span class="drawer-summary" id="drawerSummary">Loading…</span>
      <button type="button" class="text-button" onclick="markAllNotificationsRead()">Mark all read</button>
    </div>
    <div class="drawer-body" id="drawerList">
      <p class="empty-copy">Loading your reminders…</p>
    </div>
    <div class="drawer-foot">
      <a class="btn btn-ghost" href="/notifications">View all notifications</a>
      <a class="btn btn-ghost" href="/settings">Reminder settings</a>
    </div>
  </aside>
  <div class="scrim" id="drawerScrim" onclick="closeNotificationDrawer()" aria-hidden="true"></div>

  <div class="sheet" id="quickSheet" aria-hidden="true" aria-labelledby="sheetTitle" role="dialog">
    <div class="sheet-scrim" onclick="closeQuickSheet()"></div>
    <div class="sheet-panel">
      <div class="sheet-head">
        <div>
          <p class="eyebrow">Quick add</p>
          <h2 id="sheetTitle">What would you like to log?</h2>
        </div>
        <button type="button" class="icon-btn" onclick="closeQuickSheet()" aria-label="Close quick add">
          <svg class="icon"><use href="#i-close"/></svg>
        </button>
      </div>
      <div class="quick-actions">
        <button type="button" class="quick-action" onclick="quickAddWater()">
          <span class="quick-action-icon"><svg class="icon"><use href="#i-droplet"/></svg></span>
          <span><strong>250 ml water</strong><small>One glass, right now</small></span>
        </button>
        <a class="quick-action" href="/sleep">
          <span class="quick-action-icon"><svg class="icon"><use href="#i-moon"/></svg></span>
          <span><strong>Sleep</strong><small>Last night's rest</small></span>
        </a>
        <a class="quick-action" href="/walking">
          <span class="quick-action-icon"><svg class="icon"><use href="#i-activity"/></svg></span>
          <span><strong>Activity</strong><small>Steps or a workout</small></span>
        </a>
        <a class="quick-action" href="/meals">
          <span class="quick-action-icon"><svg class="icon"><use href="#i-meal"/></svg></span>
          <span><strong>Meal</strong><small>Food and calories</small></span>
        </a>
        <a class="quick-action" href="/mood">
          <span class="quick-action-icon"><svg class="icon"><use href="#i-smile"/></svg></span>
          <span><strong>Mood</strong><small>How you feel</small></span>
        </a>
        <a class="quick-action" href="/weight">
          <span class="quick-action-icon"><svg class="icon"><use href="#i-scale"/></svg></span>
          <span><strong>Weight</strong><small>Weight and BMI</small></span>
        </a>
        <a class="quick-action" href="/medications">
          <span class="quick-action-icon"><svg class="icon"><use href="#i-pill"/></svg></span>
          <span><strong>Medication</strong><small>Add or update</small></span>
        </a>
        <a class="quick-action" href="/documents">
          <span class="quick-action-icon"><svg class="icon"><use href="#i-file"/></svg></span>
          <span><strong>Document</strong><small>Upload a file</small></span>
        </a>
      </div>
    </div>
  </div>

  <div class="modal" id="confirmModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="modal-scrim" onclick="closeConfirm(false)"></div>
    <div class="confirm-card">
      <h2 id="confirmTitle">Delete this record?</h2>
      <p id="confirmBody">This cannot be undone.</p>
      <div class="confirm-actions">
        <button type="button" class="btn btn-ghost" id="confirmCancel" onclick="closeConfirm(false)">Cancel</button>
        <button type="button" class="btn btn-danger-solid" id="confirmOk" onclick="closeConfirm(true)">Delete</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>

  <script src="/js/medibyte.js?v=2"></script>
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
      });
    }
  </script>
  @stack('scripts')
</body>
</html>