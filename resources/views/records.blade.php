@extends('layouts.app')

@section('title', 'All records · MediByte')

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Records</p>
      <h2>All records</h2>
      <p>Everything you have logged, in one place.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openQuickSheet()">
      <svg class="icon"><use href="#i-plus"/></svg>
      Quick add
    </button>
  </div>

  <p id="msg" class="form-message" role="alert"></p>

  <section class="card records-card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Recent activity</p>
        <h3>Latest entries</h3>
      </div>
    </div>
    <div id="records-recent" class="record-list">
      <p class="muted">Loading…</p>
    </div>
  </section>

  <section class="records-section">
    <div class="card-head">
      <div>
        <p class="eyebrow">Every feature</p>
        <h3>Your health record</h3>
      </div>
    </div>
    <div class="records-grid" id="records-grid"></div>
  </section>

  <!-- Record detail modal -->
  <div class="modal" id="recordDetailModal" aria-hidden="true" role="dialog" aria-labelledby="recordDetailTitle">
    <div class="modal-scrim" onclick="closeRecordDetail()"></div>
    <div class="detail-card">
      <div class="detail-head">
        <h2 id="recordDetailTitle">Record details</h2>
        <button type="button" class="icon-btn" onclick="closeRecordDetail()" aria-label="Close detail view">
          <svg class="icon"><use href="#i-close"/></svg>
        </button>
      </div>
      <div class="detail-body" id="recordDetailBody">
        <p class="muted">Loading record details…</p>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
function entryPlural(count) {
  return Number(count) === 1 ? 'entry' : 'entries';
}

async function loadRecordsSummary() {
  const data = await api('/records/summary');
  if (!data || !Array.isArray(data.features)) {
    $('msg').textContent = errText(data);
    return;
  }

  const features = data.features || [];

  const recent = data.recent || [];
  $('records-recent').innerHTML = recent.length
    ? recent.map(item => `
        <a class="record records-row" href="${esc(item.href)}">
          <span class="record-icon tone-${esc(item.tone)}"><svg class="icon" aria-hidden="true"><use href="#i-${esc(item.icon)}"/></svg></span>
          <span class="record-main">
            <strong>${esc(item.label)}</strong>
            <span class="record-meta">${esc(item.summary || 'No details')}</span>
          </span>
          <span class="record-meta records-date">${esc(dateLabel(item.date))}</span>
          <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"/></svg>
        </a>`).join('')
    : `<div class="empty-state">
        <span class="empty-orb"><svg class="icon"><use href="#i-file"/></svg></span>
        <h4>Nothing logged yet</h4>
        <p>Your latest entries across every feature will appear here.</p>
      </div>`;

  $('records-grid').innerHTML = features.map(feature => `
    <div class="record-hub-card" tabindex="0" role="button" aria-label="${esc(feature.label)} record" onclick="openRecordDetail(${feature.id})">
      <span class="record-hub-icon tone-${esc(feature.tone)}"><svg class="icon" aria-hidden="true"><use href="#i-${esc(feature.icon)}"/></svg></span>
      <span class="record-hub-copy">
        <strong>${esc(feature.label)}</strong>
        <small>${feature.count} ${entryPlural(feature.count)}</small>
        ${feature.last_date ? `<small class="records-last">Last: ${esc(feature.last_value || dateLabel(feature.last_date))} · ${esc(dateLabel(feature.last_date))}</small>` : ''}
      </span>
      <div class="record-hub-overflow">
        <svg class="icon" aria-hidden="true"><use href="#i-ellipsis-v"/></svg>
        <div class="record-hub-menu">
          <div class="record-hub-menu-line"></div>
          <div class="record-hub-menu-line"></div>
          <div class="record-hub-menu-line"></div>
        </div>
      </div>
    </div>`).join('');
}

loadRecordsSummary();
</script>
<!-- Record detail interaction -->
<script>
function openRecordDetail(recordId) {
  recordIdToDelete = recordId;
  api(`/records/${recordId}`).then(data => {
    if (!data || !data.id) {
      toast('Record not found.');
      return;
    }
    const body = `
      <div class="detail-section">
        <h3>${esc(data.label)}</h3>
        <p><strong>Count:</strong> ${data.count}${data.unit ? ` ${data.unit}` : ''}</p>
        ${data.last_value !== undefined ? `<p><strong>Last value:</strong> ${esc(data.last_value)} ${data.unit || ''}</p>` : ''}
        ${data.description ? `<p><strong>Description:</strong> ${esc(data.description)}</p>` : ''}
      </div>
    `;
    $('recordDetailBody').innerHTML = body;
    $('recordDetailTitle').textContent = `${esc(data.label)} Details`;
    showElement('recordDetailModal');
  }).catch(() => toast('Failed to load record details.'));
}

function closeRecordDetail() {
  hideElement('recordDetailModal');
}

function toggleRecordMenu(elem) {
  const menu = elem.nextElementSibling;
  const isOpen = menu?.style.display === 'block';
  // Close all other menus
  document.querySelectorAll('.record-hub-menu').forEach(m => {
    m.style.display = 'none';
  });
  if (!isOpen) {
    menu.style.display = 'block';
  }
}

function selectRecordMenuAction(action) {
  closeRecordDetail();
  if (action === 'edit') {
    openQuickSheet();
  } else if (action === 'delete') {
    showElement('confirmModal');
    $('confirmTitle').textContent = 'Delete this record?';
    $('confirmOk').onclick = () => {
      closeConfirm(true);
      api(`/records/${recordIdToDelete}`, 'DELETE').then(() => {
        toast('Record deleted.');
        loadRecordsSummary();
        closeConfirm(false);
      }).catch(() => toast('Failed to delete record.'));
    };
  }
  hideElement('recordDetailModal');
}

// Close menu when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.record-hub-card')) {
    document.querySelectorAll('.record-hub-menu').forEach(m => {
      m.style.display = 'none';
    });
  }
});
</script>
@endpush
