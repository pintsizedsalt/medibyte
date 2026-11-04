@extends('layouts.app')

@section('title', 'Activity · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Activity',
    'description' => 'Keep a simple record of your daily walking — steps and active minutes.',
    'eyebrow' => 'Everyday',
  ])

  <section class="walk-tracker tone-amber" id="walkTracker" aria-label="Step tracker">
    <span class="walk-tracker-ic" aria-hidden="true"><svg class="icon"><use href="#i-activity"/></svg></span>
    <div class="walk-tracker-copy">
      <strong>Track steps with this device</strong>
      <small id="walkTrackerNote">Start tracking and carry your phone as you walk. Steps are counted from your phone's motion sensor.</small>
    </div>
    <div class="walk-tracker-steps" aria-live="polite">
      <strong id="walkStepCount">0</strong>
      <small>steps</small>
    </div>
    <div class="walk-tracker-actions">
      <button type="button" class="btn btn-primary" id="walkTrackToggle" onclick="toggleWalkTracker()">
        <svg class="icon" aria-hidden="true"><use href="#i-activity"/></svg>
        <span id="walkTrackLabel">Start tracking</span>
      </button>
      <button type="button" class="btn btn-ghost" id="walkUseSteps" onclick="useTrackedSteps()" title="Log the steps counted on this device" hidden>
        <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
        Log steps
      </button>
    </div>
  </section>
@endsection

@push('scripts')
<script>
logPage({
  title: 'Activity',
  icon: 'activity',
  tone: 'amber',
  url: '/walking-logs',
  formTitle: 'Log some movement',
  formNote: 'Steps and active minutes are both optional, but add at least one.',
  fields: [
    ['steps', 'Steps (optional)', 'number'],
    ['minutes', 'Minutes (optional)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  value: r => (r.activity_type || 'Activity').charAt(0).toUpperCase() + (r.activity_type || 'activity').slice(1),
  meta: r => [r.steps ? compactNumber(r.steps) + ' steps' : null, r.minutes ? r.minutes + ' min' : null, dateLabel(r.log_date)].filter(Boolean).join(' · ')
});

/* ---------------------------------------------------------
   Device motion step tracker
   --------------------------------------------------------- */
let walkTracking = false;
let walkSteps = 0;
let walkListener = null;
let walkLastStepAt = 0;
let walkFiltered = 0;
let walkBaseline = null;

function walkTrackerNote(text) {
  const note = document.getElementById('walkTrackerNote');
  if (note) note.textContent = text;
}

function setWalkStepCount(value) {
  walkSteps = Math.max(0, Math.round(value));
  const count = document.getElementById('walkStepCount');
  if (count) count.textContent = walkSteps.toLocaleString();
  const use = document.getElementById('walkUseSteps');
  if (use) use.hidden = walkSteps < 1;
}

function handleWalkMotion(event) {
  const acc = event.accelerationIncludingGravity || event.acceleration;
  if (!acc) return;

  const x = Number(acc.x) || 0;
  const y = Number(acc.y) || 0;
  const z = Number(acc.z) || 0;
  const magnitude = Math.sqrt(x * x + y * y + z * z);

  // Smooth the signal so a single shake does not look like a step.
  walkFiltered = walkFiltered === 0 ? magnitude : walkFiltered * 0.8 + magnitude * 0.2;

  if (walkBaseline === null) walkBaseline = walkFiltered;

  const delta = Math.abs(walkFiltered - walkBaseline);
  walkBaseline = walkBaseline * 0.98 + walkFiltered * 0.02;

  const now = Date.now();
  if (delta > 1.6 && now - walkLastStepAt > 280) {
    walkLastStepAt = now;
    setWalkStepCount(walkSteps + 1);
  }
}

async function startWalkTracker() {
  if (!window.isSecureContext) {
    walkTrackerNote('Live step tracking needs a secure (HTTPS) connection. You can still log steps manually below.');
    return;
  }

  if (typeof DeviceMotionEvent === 'undefined') {
    walkTrackerNote('This device does not share motion data, so steps cannot be counted automatically. You can still log them below.');
    return;
  }

  if (typeof DeviceMotionEvent.requestPermission === 'function') {
    try {
      const permission = await DeviceMotionEvent.requestPermission();
      if (permission !== 'granted') {
        walkTrackerNote('Motion access was not allowed. You can enable it in your browser settings or log steps manually.');
        return;
      }
    } catch (error) {
      walkTrackerNote('Motion access was not available. You can log steps manually below.');
      return;
    }
  }

  walkFiltered = 0;
  walkBaseline = null;
  walkLastStepAt = 0;
  walkListener = handleWalkMotion;
  window.addEventListener('devicemotion', walkListener, { passive: true });

  walkTracking = true;
  const tracker = document.getElementById('walkTracker');
  if (tracker) tracker.classList.add('is-live');

  const toggle = document.getElementById('walkTrackToggle');
  if (toggle) {
    toggle.classList.remove('btn-primary');
    toggle.classList.add('btn-ghost');
  }
  const label = document.getElementById('walkTrackLabel');
  if (label) label.textContent = 'Stop tracking';

  walkTrackerNote('Tracking is on. Keep the phone with you — steps update as you move.');
}

function stopWalkTracker() {
  if (walkListener) {
    window.removeEventListener('devicemotion', walkListener);
    walkListener = null;
  }

  walkTracking = false;
  const tracker = document.getElementById('walkTracker');
  if (tracker) tracker.classList.remove('is-live');

  const toggle = document.getElementById('walkTrackToggle');
  if (toggle) {
    toggle.classList.add('btn-primary');
    toggle.classList.remove('btn-ghost');
  }
  const label = document.getElementById('walkTrackLabel');
  if (label) label.textContent = walkSteps > 0 ? 'Resume tracking' : 'Start tracking';

  walkTrackerNote(
    walkSteps > 0
      ? 'Tracking paused. Use "Log steps" to save what this device counted.'
      : "Tracking stopped. Start again whenever you're ready to walk."
  );
}

function toggleWalkTracker() {
  if (walkTracking) stopWalkTracker();
  else startWalkTracker();
}

function useTrackedSteps() {
  if (walkSteps < 1) {
    toast('No steps counted yet. Start tracking first.', 'error');
    return;
  }
  openQuickLog('walking', { steps: walkSteps });
}

document.addEventListener('visibilitychange', () => {
  if (document.hidden && walkTracking) stopWalkTracker();
});

setWalkStepCount(0);
</script>
@endpush
