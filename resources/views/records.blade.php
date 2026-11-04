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
    <a class="record-hub-card" href="${esc(feature.href)}">
      <span class="record-hub-icon tone-${esc(feature.tone)}"><svg class="icon" aria-hidden="true"><use href="#i-${esc(feature.icon)}"/></svg></span>
      <span class="record-hub-copy">
        <strong>${esc(feature.label)}</strong>
        <small>${feature.count} ${entryPlural(feature.count)}</small>
        ${feature.last_date ? `<small class="records-last">Last: ${esc(feature.last_value || dateLabel(feature.last_date))} · ${esc(dateLabel(feature.last_date))}</small>` : ''}
      </span>
      <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"/></svg>
    </a>`).join('');
}

loadRecordsSummary();
</script>
@endpush
