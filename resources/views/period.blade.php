@extends('layouts.app')

@section('title', 'Cycle · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Cycle',
    'description' => 'See where you are in your cycle, what is coming up, and how you feel day to day.',
    'eyebrow' => 'Wellbeing',
  ])

  <div class="cycle-dash tone-rose" id="cycleDash" hidden>
    <div class="cycle-hero-grid">
      <section class="card cyc-card cyc-ring-card">
        <div class="cyc-week-strip" id="cycWeek" aria-label="This week"></div>

        <div class="cyc-ring" id="cycRing" role="img" aria-label="Cycle progress">
          <svg viewBox="0 0 200 200" aria-hidden="true">
            <defs>
              <linearGradient id="cycRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#ff76ac"/>
                <stop offset="50%" stop-color="#e03e8f"/>
                <stop offset="100%" stop-color="#8c68f5"/>
              </linearGradient>
            </defs>
            <circle class="cyc-ring-track" cx="100" cy="100" r="84" fill="none" stroke-width="12"/>
            <circle class="cyc-ring-ticks" cx="100" cy="100" r="74" fill="none" stroke="currentColor" stroke-width="4" stroke-dasharray="2 6" opacity="0.3"/>
            <circle class="cyc-ring-bar" id="cycRingBar" cx="100" cy="100" r="84" fill="none" stroke="url(#cycRingGrad)" stroke-width="12" stroke-linecap="round"/>
          </svg>
          <div class="cyc-ring-center">
            <span class="cyc-phase" id="cycPhase" hidden></span>
            <span class="cyc-ring-day" id="cycRingDay">—</span>
            <span class="cyc-ring-sub" id="cycRingSub">No data yet</span>
          </div>
        </div>

        <p class="cyc-ring-msg" id="cycRingMsg">Loading…</p>

        <div class="cyc-ring-actions">
          <button type="button" class="btn btn-primary" data-cyc="day" data-date="{{ now()->toDateString() }}">
            <svg class="icon"><use href="#i-plus"/></svg>
            Log today
          </button>
          <button type="button" class="btn btn-ghost" data-cyc="periods">
            <svg class="icon"><use href="#i-cycle"/></svg>
            Period log
          </button>
          <button type="button" class="btn btn-ghost btn-small" data-cyc="pause" id="cycPauseBtn">Pause predictions</button>
          <button type="button" class="btn btn-ghost btn-small" data-cyc="resume" id="cycResumeBtn" hidden>Resume predictions</button>
        </div>
      </section>

      <div class="cyc-summary">
        <div class="cyc-stat cyc-stat-next">
          <div class="cyc-stat-top">
            <span class="cyc-stat-icon"><svg class="icon"><use href="#i-calendar"/></svg></span>
            <span class="cyc-stat-label">Next period</span>
          </div>
          <span class="cyc-stat-value" id="cycNext">—</span>
          <span class="cyc-stat-sub" id="cycNextSub"></span>
        </div>

        <div class="cyc-stat cyc-stat-fertile">
          <div class="cyc-stat-top">
            <span class="cyc-stat-icon"><svg class="icon"><use href="#i-heart"/></svg></span>
            <span class="cyc-stat-label">Fertile window</span>
          </div>
          <span class="cyc-stat-value" id="cycFertile">—</span>
          <span class="cyc-stat-sub" id="cycFertileSub"></span>
        </div>

        <div class="cyc-stat cyc-stat-ovulation">
          <div class="cyc-stat-top">
            <span class="cyc-stat-icon"><svg class="icon"><use href="#i-sun"/></svg></span>
            <span class="cyc-stat-label">Ovulation</span>
          </div>
          <span class="cyc-stat-value" id="cycOvulation">—</span>
          <span class="cyc-stat-sub" id="cycOvulationSub"></span>
        </div>

        <div class="cyc-stat cyc-stat-day">
          <div class="cyc-stat-top">
            <span class="cyc-stat-icon"><svg class="icon"><use href="#i-cycle"/></svg></span>
            <span class="cyc-stat-label">Cycle day</span>
          </div>
          <span class="cyc-stat-value" id="cycDay">—</span>
          <span class="cyc-stat-sub" id="cycDaySub"></span>
        </div>
      </div>
    </div>

    <section class="cyc-banner" id="cycStatus" hidden>
      <span class="cyc-banner-icon" aria-hidden="true"><svg class="icon"><use href="#i-heart"/></svg></span>
      <div class="cyc-banner-copy">
        <strong id="cycStatusTitle">…</strong>
        <p id="cycStatusBody">…</p>
      </div>
      <div class="cyc-banner-actions" id="cycStatusActions"></div>
    </section>

    <section class="card cyc-card cyc-calendar-card">
      <div class="cyc-cal-head">
        <div class="cyc-cal-nav">
          <button type="button" class="btn btn-small" data-cyc="move" data-delta="-1" aria-label="Previous month" title="Previous month">
            <svg class="icon"><use href="#i-chevron-left"/></svg>
          </button>
          <button type="button" class="btn btn-small" data-cyc="today">Today</button>
          <button type="button" class="btn btn-small" data-cyc="move" data-delta="1" aria-label="Next month" title="Next month">
            <svg class="icon"><use href="#i-chevron-right"/></svg>
          </button>
        </div>
        <h3 class="cyc-cal-title" id="cycCalTitle">…</h3>
        <button type="button" class="btn btn-small btn-ghost" data-cyc="periods">Edit period</button>
      </div>
      <div class="cyc-cal-dow" aria-hidden="true">
        <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
      </div>
      <div class="cyc-cal-grid" id="cycCal"></div>
      <div class="cyc-legend" aria-hidden="true">
        <span class="cyc-legend-item"><i class="cyc-legend-dot period"></i>Logged period</span>
        <span class="cyc-legend-item"><i class="cyc-legend-dot predicted"></i>Predicted</span>
        <span class="cyc-legend-item"><i class="cyc-legend-dot fertile"></i>Fertile window</span>
        <span class="cyc-legend-item"><i class="cyc-legend-dot ovulation"></i>Ovulation</span>
        <span class="cyc-legend-item"><i class="cyc-legend-dot today"></i>Today</span>
      </div>
    </section>

    <section class="card cyc-card">
      <div class="card-head"><h3>For you today</h3></div>
      <div class="cyc-for-you">
        <a class="cyc-action" href="/symptoms">
          <span class="cyc-action-ic"><svg class="icon"><use href="#i-thermometer"/></svg></span>
          <span class="cyc-action-copy"><strong>Log a symptom</strong><small>Track how you feel today</small></span>
        </a>
        <div class="cyc-action">
          <span class="cyc-action-ic"><svg class="icon"><use href="#i-cycle"/></svg></span>
          <span class="cyc-action-copy"><strong id="cycPhaseName">Current phase</strong><small id="cycPhaseDesc">Loading…</small></span>
        </div>
        <a class="cyc-action" href="/mood">
          <span class="cyc-action-ic"><svg class="icon"><use href="#i-smile"/></svg></span>
          <span class="cyc-action-copy"><strong>Well-being</strong><small>Check in with your mood</small></span>
        </a>
      </div>
    </section>
  </div>

  <div class="log-modal" id="cycDayModal" hidden>
    <div class="log-modal-backdrop" data-cyc="close" data-close="cycDayModal"></div>
    <div class="log-modal-dialog log-form-dialog glassy" role="dialog" aria-modal="true" aria-labelledby="cycDayTitle">
      <header class="log-modal-head">
        <div>
          <p class="eyebrow">Daily log</p>
          <h3 id="cycDayTitle">Today</h3>
        </div>
      </header>
      <div class="log-modal-body">
        <div class="form-grid">
          <label class="field" data-grid-full="1"><span>Date</span><input type="date" id="cycDayDate" max=""></label>
          <label class="field"><span>Flow</span><select id="cycDayFlow">
            <option value="">No flow</option>
            <option value="spotting">Spotting</option>
            <option value="light">Light</option>
            <option value="medium">Medium</option>
            <option value="heavy">Heavy</option>
          </select></label>
          <label class="field"><span>Mood</span><select id="cycDayMood">
            <option value="">Choose…</option>
            <option value="happy">Happy</option>
            <option value="calm">Calm</option>
            <option value="anxious">Anxious</option>
            <option value="irritable">Irritable</option>
            <option value="sad">Sad</option>
            <option value="tired">Tired</option>
            <option value="energetic">Energetic</option>
            <option value="low">Low</option>
            <option value="sensitive">Sensitive</option>
          </select></label>
          <label class="field"><span>Discharge</span><select id="cycDayDischarge">
            <option value="">Choose…</option>
            <option value="none">None</option>
            <option value="creamy">Creamy</option>
            <option value="egg_white">Egg white</option>
            <option value="watery">Watery</option>
            <option value="sticky">Sticky</option>
          </select></label>
          <label class="field"><span>Basal body temp (°C)</span><input type="number" id="cycDayBbt" min="30" max="110" step="0.01" placeholder="e.g. 36.5"></label>
          <label class="field"><span>Ovulation test</span><select id="cycDayLh">
            <option value="">Not taken</option>
            <option value="negative">Negative</option>
            <option value="positive">Positive</option>
            <option value="peak">Peak</option>
          </select></label>
          <label class="field"><span>Pregnancy test</span><select id="cycDayPreg">
            <option value="">Not taken</option>
            <option value="negative">Negative</option>
            <option value="positive">Positive</option>
          </select></label>
          <label class="field" data-grid-full="1"><span>Medication taken</span><input type="text" id="cycDayMed" maxlength="150" placeholder="Optional"></label>
          <div class="field slider-field" data-grid-full="1">
            <span class="slider-head"><span>Pain level</span><output id="cycDayPainOut" for="cycDayPain">None</output></span>
            <input id="cycDayPain" type="range" min="0" max="10" step="1" value="0">
            <small class="slider-scale"><span>None</span><span>Severe</span></small>
          </div>
          <div class="field" data-grid-full="1">
            <span>Symptoms</span>
            <div class="chip-grid" id="cycDaySymptoms"></div>
          </div>
          <label class="switch-row" data-grid-full="1">
            <input type="checkbox" id="cycDaySex" role="switch">
            <span class="switch-track" aria-hidden="true"></span>
            <span class="switch-label">Sexual activity</span>
          </label>
          <label class="switch-row" data-grid-full="1">
            <input type="checkbox" id="cycDayProt" role="switch">
            <span class="switch-track" aria-hidden="true"></span>
            <span class="switch-label">Used protection</span>
          </label>
          <label class="field diary-field note-field" data-grid-full="1"><span>Notes</span><textarea id="cycDayNotes" rows="3" maxlength="1000" placeholder="Anything else worth noting"></textarea></label>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-danger-solid" data-cyc="day-delete" id="cycDayDelete" hidden>Delete entry</button>
          <span class="cyc-spacer"></span>
          <button type="button" class="btn btn-ghost" data-cyc="close" data-close="cycDayModal">Cancel</button>
          <button type="button" class="btn btn-primary" data-cyc="day-save">Save</button>
        </div>
      </div>
    </div>
  </div>

  <div class="log-modal" id="cycPeriodModal" hidden>
    <div class="log-modal-backdrop" data-cyc="close" data-close="cycPeriodModal"></div>
    <div class="log-modal-dialog log-form-dialog glassy" role="dialog" aria-modal="true" aria-labelledby="cycPeriodTitle">
      <header class="log-modal-head">
        <div>
          <p class="eyebrow">Cycle history</p>
          <h3 id="cycPeriodTitle">Periods</h3>
        </div>
      </header>
      <div class="log-modal-body">
        <div id="cycPeriodList"></div>
        <p class="form-card-note" id="cycPeriodNote">Add the first day of your period. Leave the last day blank if it is still ongoing.</p>
        <div class="form-grid">
          <label class="field"><span>First day</span><input type="date" id="cycPStart" max=""></label>
          <label class="field"><span>Last day (optional)</span><input type="date" id="cycPEnd" max=""></label>
          <label class="field"><span>Flow</span><select id="cycPFlow">
            <option value="">Choose…</option>
            <option value="light">Light</option>
            <option value="medium">Medium</option>
            <option value="heavy">Heavy</option>
          </select></label>
          <label class="field"><span>Notes</span><input type="text" id="cycPNotes" maxlength="500" placeholder="Optional"></label>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-ghost" data-cyc="period-cancel" id="cycPeriodCancel" hidden>Cancel edit</button>
          <span class="cyc-spacer"></span>
          <button type="button" class="btn btn-primary" data-cyc="period-save" id="cycPeriodSave">Add period</button>
        </div>
      </div>
    </div>
  </div>

  <div class="log-modal" id="cycPauseModal" hidden>
    <div class="log-modal-backdrop" data-cyc="close" data-close="cycPauseModal"></div>
    <div class="log-modal-dialog log-form-dialog glassy" role="dialog" aria-modal="true" aria-labelledby="cycPauseTitle">
      <header class="log-modal-head">
        <div>
          <p class="eyebrow">Cycle tracking</p>
          <h3 id="cycPauseTitle">Pause predictions</h3>
        </div>
      </header>
      <div class="log-modal-body">
        <p class="form-card-note">Predictions and cycle reminders pause, but your period history stays. You can resume any time.</p>
        <label class="field" data-grid-full="1"><span>Reason</span><select id="cycPauseReason">
          <option value="pregnancy">Pregnancy</option>
          <option value="postpartum">Postpartum</option>
          <option value="breastfeeding">Breastfeeding</option>
          <option value="hormonal_birth_control">Hormonal birth control</option>
          <option value="menopause">Menopause</option>
          <option value="other">Other</option>
        </select></label>
        <div class="form-actions">
          <span class="cyc-spacer"></span>
          <button type="button" class="btn btn-ghost" data-cyc="close" data-close="cycPauseModal">Cancel</button>
          <button type="button" class="btn btn-primary" data-cyc="pause-confirm">Pause predictions</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  const SYMPTOMS = ['cramps', 'headache', 'bloating', 'fatigue', 'nausea', 'breast_tenderness', 'backache', 'acne', 'cravings', 'insomnia', 'dizziness', 'mood_swings', 'hot_flashes'];
  const PAUSE_LABELS = {
    pregnancy: 'Pregnancy',
    postpartum: 'Postpartum',
    breastfeeding: 'Breastfeeding',
    hormonal_birth_control: 'Hormonal birth control',
    menopause: 'Menopause',
    other: 'Other'
  };
  const PHASE_DESC = {
    period: 'Menstrual phase: Your body is resetting. Rest, stay warm, and be gentle with yourself.',
    follicular: 'Follicular phase: Estrogen rises and energy climbs. Great time for focus and new projects.',
    fertile: 'Fertile window: Ovulation is approaching. Energy, stamina, and skin glow tend to peak here.',
    ovulation: 'Ovulation day: Your most fertile point. You might notice mild twinges or heightened senses.',
    luteal: 'Luteal phase: Progesterone dominates. Rest well, hydrate, and prepare for your period.'
  };

  const RING_R = 84;
  const RING_C = Math.PI * 2 * RING_R;

  const pad2 = n => String(n).padStart(2, '0');
  const dayKey = (y, m, d) => y + '-' + pad2(m + 1) + '-' + pad2(d);
  const parseDate = s => new Date(s + 'T00:00:00');

  const state = {
    prediction: null,
    periodLogs: [],
    days: {},
    year: new Date().getFullYear(),
    month: new Date().getMonth(),
    sel: today()
  };

  let editingId = null;

  function val(id) {
    const el = $(id);
    const v = el ? String(el.value).trim() : '';
    return v === '' ? null : v;
  }

  function numOrNull(id) {
    const v = val(id);
    return v === null ? null : Number(v);
  }

  function inPeriodLog(date) {
    return state.periodLogs.some(l => date >= l.start_date && date <= (l.end_date || l.start_date));
  }

  function inPredicted(date) {
    const p = state.prediction;
    if (!p || p.paused || p.state === 'no_data') return false;
    if (p.predicted_range) return date >= p.predicted_range.start && date <= p.predicted_range.end;
    return date === p.predicted_next_start;
  }

  function isFertile(date) {
    const p = state.prediction;
    return !!p && p.fertile_start && p.fertile_end && date >= p.fertile_start && date <= p.fertile_end;
  }

  function isOvulation(date) {
    return !!state.prediction && state.prediction.ovulation_date === date;
  }

  function phaseFor(p, t) {
    if (!p || !p.last_period_start) return null;
    if (isFertile(t)) {
      return t === p.ovulation_date ? { name: 'Ovulation day', key: 'ovulation' } : { name: 'Fertile window', key: 'fertile' };
    }
    if ((p.cycle_day || 0) <= 5) return { name: 'Menstrual phase', key: 'period' };
    if (p.fertile_start && t < p.fertile_start) return { name: 'Follicular phase', key: 'follicular' };
    return { name: 'Luteal phase', key: 'luteal' };
  }

  function supportMessage(p, phase) {
    if (!p) return 'Log your first period to start seeing predictions.';
    if (p.is_late) return 'Your period is ' + p.days_late + ' day' + (p.days_late === 1 ? '' : 's') + ' late. Cycles vary naturally — take it easy and consider logging a test.';
    if (p.state === 'still_learning') return p.message;
    if (p.days_until_next !== null && p.days_until_next <= 3 && p.days_until_next >= 0) {
      const dayWord = p.days_until_next === 0 ? 'today' : (p.days_until_next === 1 ? 'tomorrow' : 'in ' + p.days_until_next + ' days');
      return 'Heads up: Your period is expected ' + dayWord + '. You might experience mood changes, irritability, cramps, or bloating — be kind to yourself.';
    }
    if (!phase) return p.message;
    return PHASE_DESC[phase.key] || p.message;
  }

  function daysPhrase(p) {
    if (!p || p.state === 'no_data') return 'No data yet';
    if (p.is_late) return p.days_late + (p.days_late === 1 ? ' day late' : ' days late');
    if (p.days_until_next === null || p.days_until_next === undefined) return '';
    const n = p.days_until_next;
    if (n === 0) return 'Expected today';
    const prefix = p.irregular ? '~' : '';
    return prefix + 'in ' + n + (n === 1 ? ' day' : ' days');
  }

  /* -----------------------------------------------------
     Week strip
     ----------------------------------------------------- */
  function renderWeek() {
    const host = $('cycWeek');
    const t = today();
    const base = parseDate(t);
    let html = '';
    for (let i = -3; i <= 3; i++) {
      const d = new Date(base);
      d.setDate(base.getDate() + i);
      const key = dayKey(d.getFullYear(), d.getMonth(), d.getDate());
      const isToday = key === t;
      const dots = (inPeriodLog(key) ? '<i class="cyc-dot period"></i>' : '') + (state.days[key] ? '<i class="cyc-dot log"></i>' : '');
      html += `<button type="button" class="cyc-day${isToday ? ' today' : ''}" data-cyc="day" data-date="${key}" aria-label="${esc(key)}">
        <span class="cyc-day-dow">${esc(d.toLocaleDateString(undefined, { weekday: 'short' }).slice(0, 1).toUpperCase())}</span>
        <span class="cyc-day-num">${d.getDate()}</span>
        <span class="cyc-day-dots">${dots}</span>
      </button>`;
    }
    host.innerHTML = html;
  }

  /* -----------------------------------------------------
     Cycle ring
     ----------------------------------------------------- */
  function renderRing() {
    const p = state.prediction;
    const bar = $('cycRingBar');
    const paused = !!(p && p.paused);
    $('cycPauseBtn').hidden = paused;
    $('cycResumeBtn').hidden = !paused;

    let progress = 0;
    let dayText = '—';
    let subText = 'No data yet';
    let msg = 'Log your first period to start seeing predictions.';
    let phaseName = '';

    if (p && p.state !== 'no_data' && !paused) {
      const len = p.average_cycle_length || 28;
      if (p.cycle_day != null) {
        dayText = 'Day ' + p.cycle_day;
        subText = 'of ~' + len + ' days';
        progress = Math.min(1, Math.max(0, p.cycle_day / len));
      }
      const phase = phaseFor(p, today());
      if (phase) phaseName = phase.name;
      msg = supportMessage(p, phase);
    } else if (paused) {
      dayText = 'Paused';
      subText = PAUSE_LABELS[p.pause_reason] || 'Paused';
      msg = 'Predictions are paused. Your history is safe — resume whenever you are ready.';
    } else if (p && p.state === 'no_data') {
      msg = p.message || msg;
    }

    $('cycRingDay').textContent = dayText;
    $('cycRingSub').textContent = subText;
    $('cycRingMsg').textContent = msg;
    const phaseEl = $('cycPhase');
    if (phaseName) {
      phaseEl.textContent = phaseName;
      phaseEl.hidden = false;
    } else {
      phaseEl.hidden = true;
    }

    bar.style.strokeDasharray = String(RING_C);
    bar.style.strokeDashoffset = String(RING_C * (1 - progress));
    bar.style.opacity = progress > 0 ? '1' : '0';
  }

  /* -----------------------------------------------------
     Calendar
     ----------------------------------------------------- */
  function renderCalendar() {
    const first = new Date(state.year, state.month, 1);
    $('cycCalTitle').textContent = first.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    const lead = first.getDay();
    const total = new Date(state.year, state.month + 1, 0).getDate();
    const t = today();
    let html = '';
    for (let i = 0; i < lead; i++) html += '<span class="cyc-cal-cell muted"></span>';
    for (let d = 1; d <= total; d++) {
      const key = dayKey(state.year, state.month, d);
      const classes = ['cyc-cal-cell'];
      const period = inPeriodLog(key);
      if (period) classes.push('period');
      else if (inPredicted(key)) classes.push('predicted');
      if (!period && isFertile(key)) classes.push('fertile');
      if (!period && isOvulation(key)) classes.push('ovulation');
      if (key === t) classes.push('today');
      if (key === state.sel) classes.push('sel');
      html += `<button type="button" class="${classes.join(' ')}" data-cyc="day" data-date="${key}" aria-label="${esc(key)}">${d}${isOvulation(key) && !period ? '<i class="cyc-cell-ov" aria-hidden="true"></i>' : ''}</button>`;
    }
    $('cycCal').innerHTML = html;
  }

  /* -----------------------------------------------------
     Summary and status
     ----------------------------------------------------- */
  function renderSummary() {
    const p = state.prediction;
    const paused = !!(p && p.paused);
    const noData = !p || p.state === 'no_data';

    if (paused) {
      $('cycNext').textContent = 'Paused';
      $('cycNextSub').textContent = PAUSE_LABELS[p.pause_reason] || '';
      ['cycFertile', 'cycOvulation', 'cycDay'].forEach(id => $(id).textContent = '—');
      ['cycFertileSub', 'cycOvulationSub', 'cycDaySub'].forEach(id => $(id).textContent = '');
      return;
    }
    if (noData) {
      ['cycNext', 'cycFertile', 'cycOvulation', 'cycDay'].forEach(id => $(id).textContent = '—');
      ['cycNextSub', 'cycFertileSub', 'cycOvulationSub', 'cycDaySub'].forEach(id => $(id).textContent = '');
      return;
    }

    if (p.predicted_range && p.predicted_range.start && p.predicted_range.end) {
      $('cycNext').textContent = dateLabel(p.predicted_range.start) + ' – ' + dateLabel(p.predicted_range.end);
      $('cycNextSub').textContent = (p.irregular ? 'Varies · ' : '') + daysPhrase(p);
    } else {
      $('cycNext').textContent = p.predicted_next_start ? dateLabel(p.predicted_next_start) : '—';
      $('cycNextSub').textContent = (p.irregular ? 'Varies · ' : '') + daysPhrase(p);
    }

    $('cycFertile').textContent = (p.fertile_start && p.fertile_end) ? dateLabel(p.fertile_start) + ' – ' + dateLabel(p.fertile_end) : '—';
    $('cycFertileSub').textContent = p.fertile_start ? 'High fertility window' : '';
    $('cycOvulation').textContent = p.ovulation_date ? dateLabel(p.ovulation_date) : '—';
    $('cycOvulationSub').textContent = p.ovulation_date ? 'Estimated peak' : '';
    $('cycDay').textContent = p.cycle_day != null ? 'Day ' + p.cycle_day : '—';
    $('cycDaySub').textContent = 'of ~' + (p.average_cycle_length || 28) + ' days' + (p.irregular ? ' (irregular)' : '');
  }

  function renderPhase() {
    const p = state.prediction;
    const phase = phaseFor(p, today());
    const nameEl = $('cycPhaseName');
    const descEl = $('cycPhaseDesc');
    if (!phase) {
      nameEl.textContent = 'Current phase';
      descEl.textContent = p && p.paused ? 'Predictions are paused' : 'Log a period to see your phase';
      return;
    }
    nameEl.textContent = phase.name;
    descEl.textContent = PHASE_DESC[phase.key] || '';
  }

  function renderStatus() {
    const p = state.prediction;
    const el = $('cycStatus');
    const title = $('cycStatusTitle');
    const body = $('cycStatusBody');
    const actions = $('cycStatusActions');

    if (p && p.paused) {
      el.hidden = false;
      title.textContent = 'Predictions are paused';
      body.textContent = (PAUSE_LABELS[p.pause_reason] ? PAUSE_LABELS[p.pause_reason] + '. ' : '') + 'Your period history is safe. Resume whenever you are ready.';
      actions.innerHTML = '<button type="button" class="btn btn-primary" data-cyc="resume">Resume predictions</button>';
      return;
    }
    if (p && p.is_late) {
      el.hidden = false;
      title.textContent = p.days_late === 1 ? 'Your period is 1 day late' : 'Your period is ' + p.days_late + ' days late';
      let text = 'Cycles vary — take it easy. Consider logging a pregnancy test.';
      if (p.days_late > 7) text += ' If this continues, a check-in with your doctor may help.';
      body.textContent = text;
      actions.innerHTML = `<button type="button" class="btn btn-primary" data-cyc="day" data-date="${today()}">Log a pregnancy test</button>
        <button type="button" class="btn btn-ghost" data-cyc="pause">Pause predictions</button>`;
      return;
    }
    el.hidden = true;
  }

  function render() {
    renderWeek();
    renderRing();
    renderCalendar();
    renderSummary();
    renderPhase();
    renderStatus();
  }

  /* -----------------------------------------------------
     Daily log modal
     ----------------------------------------------------- */
  function buildSymptoms() {
    const host = $('cycDaySymptoms');
    host.innerHTML = SYMPTOMS.map(s => `<label class="chip"><input type="checkbox" data-symptom="${s}"><span>${esc(optionLabel(s))}</span></label>`).join('');
  }

  function setPain(value) {
    const n = Number(value) || 0;
    $('cycDayPain').value = String(n);
    $('cycDayPainOut').textContent = n === 0 ? 'None' : n + ' / 10';
  }

  function openDay(date) {
    state.sel = date;
    const day = state.days[date] || {};
    $('cycDayDate').value = date;
    $('cycDayDate').max = today();
    $('cycDayTitle').textContent = dateLabel(date) + (date === today() ? ' · Today' : '');
    $('cycDayFlow').value = day.flow || '';
    $('cycDayMood').value = day.mood || '';
    $('cycDayDischarge').value = day.discharge || '';
    $('cycDayBbt').value = day.bbt ?? '';
    $('cycDayLh').value = day.lh_test || '';
    $('cycDayPreg').value = day.pregnancy_test || '';
    $('cycDayMed').value = day.medication || '';
    $('cycDaySex').checked = !!day.sexual_activity;
    $('cycDayProt').checked = !!day.protection;
    $('cycDayNotes').value = day.notes || '';
    setPain(day.pain ?? 0);
    const selected = new Set(day.symptoms || []);
    document.querySelectorAll('#cycDaySymptoms input').forEach(cb => { cb.checked = selected.has(cb.dataset.symptom); });
    $('cycDayDelete').hidden = !day.id;
    openModal($('cycDayModal'));
    render();
  }

  async function saveDay() {
    const date = $('cycDayDate').value;
    if (!date) { toast('Pick a date.', 'error'); return; }
    if (date > today()) { toast('You cannot log a future day.', 'error'); return; }
    const pain = Number($('cycDayPain').value) || 0;
    const symptoms = Array.from(document.querySelectorAll('#cycDaySymptoms input:checked')).map(cb => cb.dataset.symptom);
    const body = {
      log_date: date,
      flow: val('cycDayFlow'),
      pain: pain > 0 ? pain : null,
      symptoms: symptoms,
      mood: val('cycDayMood'),
      discharge: val('cycDayDischarge'),
      bbt: numOrNull('cycDayBbt'),
      lh_test: val('cycDayLh'),
      pregnancy_test: val('cycDayPreg'),
      medication: val('cycDayMed'),
      sexual_activity: $('cycDaySex').checked,
      protection: $('cycDayProt').checked,
      notes: val('cycDayNotes')
    };
    const result = await api('/period-days', 'POST', body);
    if (result && result.log_date) {
      state.days[result.log_date] = result;
      state.sel = result.log_date;
      toast('Daily log saved.');
      closeModal($('cycDayModal'));
      render();
    } else {
      toast(errText(result), 'error');
    }
  }

  async function deleteDay() {
    const date = $('cycDayDate').value;
    const day = state.days[date];
    if (!day) { closeModal($('cycDayModal')); return; }
    const ok = await confirmAction({ title: 'Delete daily log?', body: 'This removes the entry for ' + dateLabel(date) + '.', confirmLabel: 'Delete' });
    if (!ok) return;
    const result = await api('/period-days/' + day.id, 'DELETE');
    if (result && (result.message || result.id)) {
      delete state.days[date];
      toast('Daily log deleted.');
      closeModal($('cycDayModal'));
      render();
    } else {
      toast(errText(result), 'error');
    }
  }

  /* -----------------------------------------------------
     Period modal
     ----------------------------------------------------- */
  function renderPeriodList() {
    const host = $('cycPeriodList');
    if (!state.periodLogs.length) {
      host.innerHTML = '<p class="empty-copy">No periods logged yet. Add your first one below.</p>';
      return;
    }
    const sorted = state.periodLogs.slice().sort((a, b) => (a.start_date < b.start_date ? 1 : -1));
    host.innerHTML = sorted.map(l => {
      const label = dateLabel(l.start_date) + (l.end_date ? ' – ' + dateLabel(l.end_date) : ' – ongoing');
      const flow = l.flow ? optionLabel(l.flow) + ' flow' : '';
      return `<div class="cyc-period-row">
        <div class="cyc-period-meta">
          <strong>${esc(label)}</strong>
          ${flow ? `<span>${esc(flow)}</span>` : ''}
          ${l.notes ? `<span>${esc(l.notes)}</span>` : ''}
        </div>
        <div class="cyc-period-actions">
          <button type="button" class="btn btn-small btn-ghost" data-cyc="period-edit" data-id="${l.id}">Edit</button>
          <button type="button" class="btn btn-small btn-danger" data-cyc="period-delete" data-id="${l.id}">Delete</button>
        </div>
      </div>`;
    }).join('');
  }

  function resetPeriodForm() {
    editingId = null;
    $('cycPStart').value = '';
    $('cycPEnd').value = '';
    $('cycPFlow').value = '';
    $('cycPNotes').value = '';
    $('cycPeriodTitle').textContent = 'Periods';
    $('cycPeriodNote').textContent = 'Add the first day of your period. Leave the last day blank if it is still ongoing.';
    $('cycPeriodSave').textContent = 'Add period';
    $('cycPeriodCancel').hidden = true;
    renderPeriodList();
  }

  function openPeriods() {
    resetPeriodForm();
    $('cycPStart').max = today();
    $('cycPEnd').max = today();
    openModal($('cycPeriodModal'));
  }

  function editPeriod(id) {
    const l = state.periodLogs.find(x => String(x.id) === String(id));
    if (!l) return;
    editingId = id;
    $('cycPStart').value = l.start_date || '';
    $('cycPEnd').value = l.end_date || '';
    $('cycPFlow').value = l.flow || '';
    $('cycPNotes').value = l.notes || '';
    $('cycPeriodTitle').textContent = 'Edit period';
    $('cycPeriodNote').textContent = 'Update the first and last day of this period.';
    $('cycPeriodSave').textContent = 'Save changes';
    $('cycPeriodCancel').hidden = false;
  }

  async function savePeriod() {
    const start = $('cycPStart').value;
    if (!start) { toast('Pick a first day.', 'error'); return; }
    if (start > today()) { toast('The first day cannot be in the future.', 'error'); return; }
    const end = $('cycPEnd').value;
    if (end && end < start) { toast('The last day must be on or after the first day.', 'error'); return; }
    const body = { start_date: start, end_date: end || null, flow: val('cycPFlow'), notes: val('cycPNotes') };
    const result = editingId ? await api('/period-logs/' + editingId, 'PUT', body) : await api('/period-logs', 'POST', body);
    if (result && (result.id || result.start_date)) {
      toast(editingId ? 'Period updated.' : 'Period logged.');
      closeModal($('cycPeriodModal'));
      await load();
    } else {
      toast(errText(result), 'error');
    }
  }

  async function deletePeriod(id) {
    const ok = await confirmAction({ title: 'Delete this period?', body: 'The period is removed and your prediction is recalculated.', confirmLabel: 'Delete' });
    if (!ok) return;
    const result = await api('/period-logs/' + id, 'DELETE');
    if (result && (result.message || result.id)) {
      toast('Period deleted.');
      await load();
    } else {
      toast(errText(result), 'error');
    }
  }

  /* -----------------------------------------------------
     Pause / resume
     ----------------------------------------------------- */
  async function confirmPause() {
    const result = await api('/profile', 'POST', { cycle_paused: true, cycle_pause_reason: $('cycPauseReason').value });
    if (result && result.id) {
      toast('Predictions paused.');
      closeModal($('cycPauseModal'));
      await load();
    } else {
      toast(errText(result), 'error');
    }
  }

  async function resume() {
    const result = await api('/profile', 'POST', { cycle_paused: false });
    if (result && result.id) {
      toast('Predictions resumed.');
      await load();
    } else {
      toast(errText(result), 'error');
    }
  }

  /* -----------------------------------------------------
     Modal plumbing and boot
     ----------------------------------------------------- */
  function openModal(el) {
    el.hidden = false;
    document.body.classList.add('log-modal-open');
  }

  function closeModal(el) {
    if (!el) return;
    el.hidden = true;
    if (['cycDayModal', 'cycPeriodModal', 'cycPauseModal'].every(id => $(id)?.hidden !== false)) {
      document.body.classList.remove('log-modal-open');
    }
  }

  function moveMonth(delta) {
    const d = new Date(state.year, state.month + delta, 1);
    state.year = d.getFullYear();
    state.month = d.getMonth();
    load();
  }

  function goToday() {
    const n = new Date();
    state.year = n.getFullYear();
    state.month = n.getMonth();
    load();
  }

  async function load() {
    const data = await api('/cycle?month=' + dayKey(state.year, state.month, 1));
    if (!data || !data.prediction) {
      $('msg').textContent = (data && data.message) || 'Could not load your cycle. Please refresh.';
      return;
    }
    state.prediction = data.prediction;
    state.periodLogs = data.period_logs || [];
    const map = {};
    (data.period_days || []).forEach(d => { map[d.log_date] = d; });
    state.days = map;
    render();
  }

  document.addEventListener('click', e => {
    const el = e.target.closest('[data-cyc]');
    if (!el) return;
    const action = el.dataset.cyc;
    if (action === 'day') openDay(el.dataset.date);
    else if (action === 'move') moveMonth(Number(el.dataset.delta));
    else if (action === 'today') goToday();
    else if (action === 'periods') openPeriods();
    else if (action === 'close') closeModal($(el.dataset.close));
    else if (action === 'pause') openModal($('cycPauseModal'));
    else if (action === 'pause-confirm') confirmPause();
    else if (action === 'resume') resume();
    else if (action === 'day-save') saveDay();
    else if (action === 'day-delete') deleteDay();
    else if (action === 'period-save') savePeriod();
    else if (action === 'period-cancel') resetPeriodForm();
    else if (action === 'period-edit') editPeriod(el.dataset.id);
    else if (action === 'period-delete') deletePeriod(el.dataset.id);
  });

  $('cycDayPain').addEventListener('input', e => setPain(e.target.value));

  async function boot() {
    const p = await api('/profile');
    if (!p || !p.show_period_tracker) { location.href = '/dashboard'; return; }
    setCycleVisibility(true);
    buildSymptoms();
    $('cycleDash').hidden = false;
    await load();
  }

  boot();
})();
</script>
@endpush
