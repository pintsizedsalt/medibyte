@extends('layouts.app')

@section('title', 'Dashboard · MediByte')

@section('content')
<div class="dash-page">
  <div class="dash-heading">
    <div class="dash-heading-main">
      <span class="heading-ic" aria-hidden="true"><svg class="icon"><use href="#i-heart"/></svg></span>
      <div>
        <p class="eyebrow">Health summary</p>
        <h2><span id="greeting">Welcome</span>, <span id="hello">there</span></h2>
        <p class="dash-tagline">Your health at a glance.</p>
      </div>
    </div>
    <a class="btn btn-ghost dash-all-records" href="/records">
      All records
      <svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg>
    </a>
  </div>

  <p id="msg" class="form-message" role="alert"></p>

  <section class="health-hero" aria-label="Health progress">
    <div class="card health-score-card">
      <div class="card-head">
        <div>
          <h3>Health progress</h3>
          <p class="muted">Today's daily goals</p>
        </div>
        <span class="badge" id="health-status-badge">Loading</span>
      </div>

      <div class="score-content">
        <div class="score-ring" aria-hidden="true">
          <svg viewBox="0 0 120 120">
            <circle class="score-track" cx="60" cy="60" r="52"></circle>
            <circle class="score-value" id="score-circle" cx="60" cy="60" r="52" stroke-dasharray="326.73" stroke-dashoffset="326.73"></circle>
          </svg>
          <div class="score-center">
            <strong id="health-score">—</strong>
            <span>of 100</span>
          </div>
        </div>

        <div class="score-detail">
          <p class="score-summary" id="health-summary">Loading your health summary…</p>
          <div class="score-components" id="score-components"></div>
        </div>
      </div>
    </div>

    <div class="card health-week-card">
      <div class="card-head">
        <div>
          <h3>Last 7 days</h3>
          <p class="muted">Daily progress</p>
        </div>
        <strong class="week-average" id="week-average">—</strong>
      </div>
      <div class="week-chart" id="week-chart" aria-label="Seven day health progress"></div>
      <p class="muted week-note">Based on sleep, hydration, and movement goals.</p>
    </div>
  </section>

  <nav class="dash-rail" aria-label="Quick actions">
    <button type="button" class="dash-rail-btn" data-tone="water" onclick="openQuickLog('water')" aria-label="Log water">
      <svg class="icon" aria-hidden="true"><use href="#i-droplet"/></svg>
      <span class="dash-rail-label">Log water</span>
    </button>
    <button type="button" class="dash-rail-btn" data-tone="sleep" onclick="openQuickLog('sleep')" aria-label="Log sleep">
      <svg class="icon" aria-hidden="true"><use href="#i-moon"/></svg>
      <span class="dash-rail-label">Log sleep</span>
    </button>
    <button type="button" class="dash-rail-btn" data-tone="move" onclick="openQuickLog('meals')" aria-label="Add meal">
      <svg class="icon" aria-hidden="true"><use href="#i-meal"/></svg>
      <span class="dash-rail-label">Add meal</span>
    </button>
    <button type="button" class="dash-rail-btn" data-tone="mood" onclick="openQuickLog('mood')" aria-label="Mood check-in">
      <svg class="icon" aria-hidden="true"><use href="#i-smile"/></svg>
      <span class="dash-rail-label">Mood check-in</span>
    </button>
  </nav>

  <section class="metric-grid" id="metric-grid" aria-label="Health metrics">
    <div class="metric-card"><small>Sleep</small><strong>—</strong><span>Loading…</span></div>
    <div class="metric-card"><small>Hydration</small><strong>—</strong><span>Loading…</span></div>
    <div class="metric-card"><small>Movement</small><strong>—</strong><span>Loading…</span></div>
    <div class="metric-card"><small>Cycle or mood</small><strong>—</strong><span>Loading…</span></div>
    <div class="metric-card"><small>Weight &amp; BMI</small><strong>—</strong><span>Loading…</span></div>
  </section>

  <div class="dash-grid">
    <section class="card">
      <div class="card-head">
        <h3>Next up</h3>
        <a class="card-cta" href="/appointments">Appointments<svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a>
      </div>
      <div class="care-list" id="care-list">
        <p class="muted">Loading…</p>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <h3>Wellbeing check</h3>
        <a class="card-cta" href="/symptoms">Symptoms<svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a>
      </div>
      <div class="wellbeing-list" id="wellbeing-list">
        <p class="muted">Loading…</p>
      </div>
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
const SCORE_CIRCUMFERENCE = 2 * Math.PI * 52;

// Accent color + icon for each kind of metric (colors live in medibyte.css)
const TONE_ICONS = {
  sleep: 'i-moon',
  water: 'i-droplet',
  move: 'i-activity',
  mood: 'i-smile',
  cycle: 'i-cycle',
  weight: 'i-scale',
  ok: 'i-check',
  appointment: 'i-calendar',
  medication: 'i-pill',
  symptom: 'i-thermometer',
  vitals: 'i-heart'
};

const METRIC_TONES = {
  '/sleep': 'sleep',
  '/water': 'water',
  '/walking': 'move',
  '/mood': 'mood',
  '/period': 'cycle',
  '/weight': 'weight'
};

const COMPONENT_TONES = { sleep: 'sleep', hydration: 'water', movement: 'move' };

function iconChip(name, tone, large) {
  return `<span class="dm-ic${large ? ' dm-ic-lg' : ''}" data-tone="${tone}"><svg class="icon" aria-hidden="true"><use href="#${TONE_ICONS[name]}"/></svg></span>`;
}

function greetingText() {
  const hour = new Date().getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 18) return 'Good afternoon';
  return 'Good evening';
}

function setHealthScore(score) {
  const safe = Math.max(0, Math.min(100, Number(score) || 0));
  const circle = $('score-circle');

  if (circle) {
    circle.style.strokeDasharray = String(SCORE_CIRCUMFERENCE);
    circle.style.strokeDashoffset = String(SCORE_CIRCUMFERENCE * (1 - safe / 100));
  }

  $('health-score').textContent = safe;
}

function renderHealthComponents(components) {
  const box = $('score-components');

  if (!components.length) {
    box.innerHTML = '<p class="muted">Set your daily goals in your profile to see a breakdown.</p>';
    return;
  }

  box.innerHTML = components.map(component => {
    const tone = COMPONENT_TONES[String(component.label).toLowerCase()] || 'ok';
    const percent = Math.max(0, Math.min(100, Number(component.percent) || 0));

    return `
    <div data-tone="${tone}">
      <div class="score-component-row">
        <span>${esc(component.label)}</span>
        <strong>${Number(component.percent) || 0}%</strong>
      </div>
      <div class="meter"><span style="width:${percent}%"></span></div>
    </div>
  `;
  }).join('');
}

function renderWeek(week) {
  const box = $('week-chart');

  if (!Array.isArray(week) || !week.length) {
    box.innerHTML = '<p class="muted">No progress yet.</p>';
    return;
  }

  box.innerHTML = week.map((day, index) => {
    const score = Math.max(0, Math.min(100, Number(day.score) || 0));
    const height = day.has_data ? Math.max(score, 2) : 2;
    const today = index === week.length - 1;

    return `
      <div class="week-day${today ? ' today' : ''}" title="${esc(day.label)}: ${day.has_data ? score + '%' : 'No logs'}">
        <span class="week-value">${day.has_data ? score : '—'}</span>
        <div class="week-bar-track">
          <span class="week-bar ${day.has_data ? '' : 'empty'}" style="height:${height}%"></span>
        </div>
        <small>${esc(day.label)}</small>
      </div>
    `;
  }).join('');
}

function renderMetrics(metrics) {
  $('metric-grid').innerHTML = metrics.map(metric => {
    const tone = METRIC_TONES[metric.href] || 'weight';
    const hasMeter = !(metric.percent === null || metric.percent === undefined);

    return `
    <a class="metric-card" data-tone="${tone}" href="${esc(metric.href)}">
      <div class="dm-top">
        ${iconChip(tone, tone, false)}
        <small>${esc(metric.label)}</small>
      </div>
      <strong>${esc(metric.value)}</strong>
      <span>${esc(metric.detail)}</span>
      ${hasMeter ? `<div class="metric-meter"><span style="width:${Math.max(0, Math.min(100, Number(metric.percent) || 0))}%"></span></div>` : ''}
    </a>
  `;
  }).join('');
}

function renderCare(data) {
  const appointment = (data.upcoming_appointments || [])[0];
  const medication = data.next_medication;
  const rows = [];

  if (appointment) {
    rows.push(`
      <div class="care-row">
        ${iconChip('appointment', 'sleep', true)}
        <div class="dr-body">
          <span class="dr-label">Appointment</span>
          <strong>${esc(appointment.doctor_name)}</strong>
          <span class="dr-sub">${esc(dateTimeLabel(appointment.appointment_at))}${appointment.specialty ? ' · ' + esc(appointment.specialty) : ''}</span>
          ${appointment.reason ? `<span class="dr-sub">${esc(appointment.reason)}</span>` : ''}
        </div>
      </div>
    `);
  } else {
    rows.push(`
      <div class="care-row">
        ${iconChip('appointment', 'sleep', true)}
        <div class="dr-body">
          <span class="dr-label">Appointment</span>
          <strong>No upcoming appointments</strong>
          <span class="dr-sub">Add a visit when you have one scheduled.</span>
        </div>
      </div>
    `);
  }

  if (medication) {
    rows.push(`
      <div class="care-row">
        ${iconChip('medication', 'move', true)}
        <div class="dr-body">
          <span class="dr-label">Medication</span>
          <strong>${esc(medication.name)}${medication.dosage ? ' · ' + esc(medication.dosage) : ''}</strong>
          <span class="dr-sub">Next dose ${esc(dateTimeLabel(medication.at))}</span>
        </div>
      </div>
    `);
  } else {
    rows.push(`
      <div class="care-row">
        ${iconChip('medication', 'move', true)}
        <div class="dr-body">
          <span class="dr-label">Medication</span>
          <strong>No scheduled medication</strong>
          <span class="dr-sub">Active medications with times will appear here.</span>
        </div>
      </div>
    `);
  }

  $('care-list').innerHTML = rows.join('');
}

function renderWellbeing(data) {
  const symptoms = data.recent_symptoms || [];
  const mood = data.latest_mood;
  const vitals = data.latest_vitals;
  const rows = [];

  if (symptoms.length) {
    rows.push(`
      <div class="wellbeing-row">
        ${iconChip('symptom', 'move', true)}
        <div class="dr-body">
          <span class="dr-label">Symptoms</span>
          <div class="wellbeing-lines">
            ${symptoms.slice(0, 2).map(symptom => `
              <div class="wellbeing-line">
                <strong>${esc(symptom.symptom)}${symptom.illness_name ? ' · ' + esc(symptom.illness_name) : ''}</strong>
                <span class="dr-sub">Severity ${esc(symptom.severity)}/5 · ${esc(dateLabel(symptom.log_date))}</span>
              </div>
            `).join('')}
          </div>
        </div>
      </div>
    `);
  } else {
    rows.push(`
      <div class="wellbeing-row">
        ${iconChip('ok', 'ok', true)}
        <div class="dr-body">
          <span class="dr-label">Symptoms</span>
          <strong>No symptoms logged recently</strong>
          <span class="dr-sub">If you are feeling unwell, add a check-in from the Symptoms page.</span>
        </div>
      </div>
    `);
  }

  rows.push(`
    <div class="wellbeing-row">
      ${iconChip('mood', 'mood', true)}
      <div class="dr-body">
        <span class="dr-label">Mood</span>
        <strong>${mood ? esc(mood.mood.charAt(0).toUpperCase() + mood.mood.slice(1)) : 'No mood check-in yet'}</strong>
        <span class="dr-sub">${mood ? 'Intensity ' + esc(mood.intensity) + '/5 · ' + esc(dateLabel(mood.log_date)) : 'Add how you feel from the Mood page.'}</span>
      </div>
    </div>
  `);

  if (vitals) {
    const readings = [];
    if (vitals.systolic && vitals.diastolic) readings.push('BP ' + vitals.systolic + '/' + vitals.diastolic);
    if (vitals.heart_rate) readings.push('HR ' + vitals.heart_rate + ' bpm');
    if (vitals.temperature_c) readings.push(vitals.temperature_c + '°C');
    if (vitals.oxygen_saturation) readings.push('O2 ' + vitals.oxygen_saturation + '%');

    rows.push(`
      <div class="wellbeing-row">
        ${iconChip('vitals', 'cycle', true)}
        <div class="dr-body">
          <span class="dr-label">Latest vitals</span>
          <strong>${readings.length ? esc(readings.join(' · ')) : 'No readings recorded'}</strong>
          <span class="dr-sub">${esc(dateLabel(vitals.log_date))}</span>
        </div>
      </div>
    `);
  } else {
    rows.push(`
      <div class="wellbeing-row">
        ${iconChip('vitals', 'cycle', true)}
        <div class="dr-body">
          <span class="dr-label">Latest vitals</span>
          <strong>No vitals logged</strong>
          <span class="dr-sub">Add a blood pressure, heart rate, or temperature reading from Vitals.</span>
        </div>
      </div>
    `);
  }

  $('wellbeing-list').innerHTML = rows.join('');
}

async function loadDashboard() {
  const data = await api('/dashboard');
  if (!data || !data.name) {
    $('msg').textContent = errText(data);
    return;
  }

  $('greeting').textContent = greetingText();
  $('hello').textContent = data.first_name || data.name;

  const health = data.health || {};
  setHealthScore(health.score || 0);
  $('health-status-badge').textContent = health.status || 'No data';
  $('health-summary').textContent = health.summary || 'Start logging to see your health progress.';
  $('week-average').textContent = health.has_data ? (health.week_average || 0) + '%' : '—';

  renderHealthComponents(health.components || []);
  renderWeek(health.week || []);
  renderMetrics(data.metrics || []);
  renderCare(data);
  renderWellbeing(data);
}

loadDashboard();
window.loadDashboard = loadDashboard;
</script>
@endpush