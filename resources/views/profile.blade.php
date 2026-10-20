@extends('layouts.app')

@section('content')
  <p id="stepLabel"></p>
  <p id="msg"></p>

  {{-- STEP 1: CYCLE (girls lang) --}}
  <div id="step1" style="display:none">
    <h2>Cycle setup</h2>
    <label>Do you have a period?
      <select id="period_status" onchange="togglePeriodFields()">
        <option value="active">Yes</option>
        <option value="not_yet">No, not yet</option>
        <option value="menopause">No, menopause</option>
      </select>
    </label>
    <div id="periodFields">
      <label>Last period start <input id="last_period" type="date"></label>
      <label>Cycle length (days) <input id="cycle_length" type="number" value="28"></label>
    </div>
    <button onclick="saveStep1()">Save &amp; continue</button>
  </div>

  {{-- STEP 2: HABITS --}}
  <div id="step2" style="display:none">
    <h2>Daily habits</h2>

    <label><input type="checkbox" id="add_walking" onchange="toggleWalking()"> Add walking?</label>
    <div id="walkingFields" style="display:none">
      <label>Activity
        <select id="activity_type">
          <option value="walking">Walking</option>
          <option value="jogging">Jogging</option>
          <option value="running">Running</option>
          <option value="workout">Workout</option>
        </select>
      </label>
      <label>Steps <input id="steps" type="number"></label>
      <label>Minutes <input id="minutes" type="number"></label>
    </div>

    <h3>Water</h3>
    <label>Water intake today (ml) <input id="water_ml" type="number" value="0"></label>

    <h3>Sleep</h3>
    <label>Bedtime <input id="bedtime" type="time" value="23:00"></label>
    <label>Wake up <input id="wake_time" type="time" value="06:00"></label>

    <h3>Meals today</h3>
    @foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $m)
      <p>
        <label><input type="checkbox" id="meal_{{ $m }}"> {{ ucfirst($m) }}</label>
        <input id="food_{{ $m }}" placeholder="food">
        <input id="cal_{{ $m }}" type="number" placeholder="calories (optional)">
      </p>
    @endforeach

    <button onclick="saveStep2()">Continue</button>
  </div>

  {{-- STEP 3: BODY & MOOD --}}
  <div id="step3" style="display:none">
    <h2>Body &amp; mood</h2>

    <label>Height (cm) <input id="height_cm" type="number" step="any" oninput="calcBmi()"></label>
    <label>Weight (kg) <input id="weight_kg" type="number" step="any" oninput="calcBmi()"></label>
    <p>Your BMI: <strong id="bmi">-</strong></p>

    <label><input type="checkbox" id="has_goal" onchange="toggleGoal()"> Set a weight goal?</label>
    <div id="goalFields" style="display:none">
      <label>Target weight (kg) <input id="weight_goal" type="number" step="any"></label>
    </div>

    <h3>Mood</h3>
    <label>How do you feel?
      <select id="mood">
        <option value="happy">Happy</option>
        <option value="calm">Calm</option>
        <option value="stressed">Stressed</option>
        <option value="sad">Sad</option>
      </select>
    </label>
    <label>Intensity
      <select id="mood_intensity">
        <option>1</option>
        <option>2</option>
        <option selected>3</option>
        <option>4</option>
        <option>5</option>
      </select>
    </label>

    <button onclick="saveStep3()">Continue</button>
  </div>
@endsection

@push('scripts')
<script>
const done = {};

// isang beses lang magse-save kada item, kahit ulitin mo ang button
async function once(key, url, body) {
  if (done[key]) return true;
  const d = await api(url, 'POST', body);
  if (!d.id) { $('msg').textContent = errText(d); return false; }
  done[key] = true;
  return true;
}

function show(n) {
  [1, 2, 3].forEach(i => $('step' + i).style.display = i === n ? 'block' : 'none');
  $('stepLabel').textContent = 'Step ' + n + ' of 4';
  $('msg').textContent = '';
}

function togglePeriodFields() {
  $('periodFields').style.display = $('period_status').value === 'active' ? 'block' : 'none';
}

function toggleWalking() {
  $('walkingFields').style.display = $('add_walking').checked ? 'block' : 'none';
}

function calcBmi() {
  const h = Number($('height_cm').value) / 100;
  const w = Number($('weight_kg').value);
  $('bmi').textContent = h > 0 && w > 0 ? (w / (h * h)).toFixed(1) : '-';
}

function toggleGoal() {
  $('goalFields').style.display = $('has_goal').checked ? 'block' : 'none';
}

async function saveStep1() {
  const status = $('period_status').value;
  const body = { period_status: status };

  if (status === 'active') {
    if (!$('last_period').value) { $('msg').textContent = 'Enter your last period start date.'; return; }
    body.cycle_length = $('cycle_length').value;
  }

  const p = await api('/profile', 'POST', body);
  if (!p.id) { $('msg').textContent = errText(p); return; }

  if (status === 'active') {
    if (!await once('period', '/period-logs', { start_date: $('last_period').value })) return;
  }

  show(2);
}

async function saveStep2() {
  const date = ld(new Date());

  if ($('add_walking').checked) {
    const body = { activity_type: $('activity_type').value, log_date: date };
    if ($('steps').value) body.steps = $('steps').value;
    if ($('minutes').value) body.minutes = $('minutes').value;
    if (!await once('walking', '/walking-logs', body)) return;
  }

  if (Number($('water_ml').value) > 0) {
    if (!await once('water', '/water-logs', { amount_ml: $('water_ml').value, log_date: date })) return;
  }

  if ($('bedtime').value && $('wake_time').value) {
    if (!await once('sleep', '/sleep-logs', {
      bedtime: $('bedtime').value, wake_time: $('wake_time').value, log_date: date
    })) return;
  }

  for (const m of ['breakfast', 'lunch', 'dinner', 'snack']) {
    if ($('meal_' + m).checked) {
      const food = $('food_' + m).value.trim();
      if (!food) { $('msg').textContent = 'Enter what you ate for ' + m + '.'; return; }
      if (!await once('meal_' + m, '/meal-logs', {
        meal_type: m, food_name: food, calories: $('cal_' + m).value || 0, log_date: date
      })) return;
    }
  }

  show(3);
}

async function saveStep3() {
  const date = ld(new Date());

  if (!$('height_cm').value || !$('weight_kg').value) {
    $('msg').textContent = 'Enter your height and weight.';
    return;
  }

  if (!await once('weight', '/weight-logs', {
    weight_kg: $('weight_kg').value, height_cm: $('height_cm').value, log_date: date
  })) return;

  if ($('has_goal').checked) {
    if (!$('weight_goal').value) { $('msg').textContent = 'Enter your target weight.'; return; }
    const p = await api('/profile', 'POST', { weight_goal: $('weight_goal').value });
    if (!p.id) { $('msg').textContent = errText(p); return; }
  }

  if (!await once('mood', '/mood-logs', {
    mood: $('mood').value, intensity: $('mood_intensity').value, log_date: date
  })) return;

  // PANSAMANTALA: papalitan ito sa susunod na step ng Step 4 ng wizard
  location.href = '/dashboard';
}

async function init() {
  $('nav').style.display = 'none';
  const profile = await api('/profile');
  togglePeriodFields();
  show(profile && profile.sex === 'female' ? 1 : 2);
}

init();
</script>
@endpush