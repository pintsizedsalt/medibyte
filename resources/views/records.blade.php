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

  <section class="records-section">
    <div class="card-head">
      <div>
        <p class="eyebrow">Every feature</p>
        <h3>Your health record</h3>
      </div>
    </div>
    <div class="records-grid" id="records-grid">
      <p class="muted">Loading your records…</p>
    </div>
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

  $('records-grid').innerHTML = features.map(feature => {
    const iconId = feature.icon.startsWith('i-') ? feature.icon : ('i-' + feature.icon);
    const lastLabel = feature.last_date
      ? 'Last: ' + esc(feature.last_value || dateLabel(feature.last_date)) + ' · ' + esc(dateLabel(feature.last_date))
      : 'No entries yet';

    return `
      <a class="record-hub-card tone-${esc(feature.tone)}" href="${esc(feature.href)}" aria-label="${esc(feature.label)} records">
        <div class="record-hub-notch" aria-hidden="true">
          <span class="record-hub-notch-btn">
            <svg class="icon"><use href="#i-arrow-up-right"/></svg>
          </span>
        </div>
        <div class="record-hub-head">
          <span class="record-hub-icon">
            <svg class="icon" aria-hidden="true"><use href="#${esc(iconId)}"/></svg>
          </span>
          <div class="record-hub-info">
            <span class="record-hub-label">${esc(feature.label)}</span>
            <strong class="record-hub-count">${feature.count} ${entryPlural(feature.count)}</strong>
          </div>
        </div>
        <div class="record-hub-foot">
          <span class="record-hub-stat">${lastLabel}</span>
        </div>
      </a>`;
  }).join('');
}

loadRecordsSummary();
</script>
@endpush
