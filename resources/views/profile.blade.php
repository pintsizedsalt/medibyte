@extends('layouts.app')

@section('content')
  <h2>Profile & goals</h2>
  <p id="msg"></p>

  <label>Sex
    <select id="sex" onchange="togglePeriod()">
      <option value="">Choose</option>
      <option value="female">Female</option>
      <option value="male">Male</option>
    </select>
  </label>

  <div id="periodBox" style="display:none">
    <label>Menstruation
      <select id="period_status">
        <option value="active">I have a regular period</option>
        <option value="not_yet">Not yet started</option>
        <option value="menopause">Menopause</option>
      </select>
    </label>
  </div>

  <label>Water goal (ml) <input id="water_goal_ml" type="number"></label>
  <label>Sleep goal (hours) <input id="sleep_goal_hours" type="number" step="any"></label>
  <label>Steps goal <input id="walking_goal_steps" type="number"></label>
  <label>Calorie goal (optional) <input id="calorie_goal" type="number"></label>
  <label>Weight goal in kg (optional) <input id="weight_goal" type="number" step="any"></label>

  <button onclick="saveProfile()">Save</button>
@endsection

@push('scripts')
<script>
const goalIds = ['water_goal_ml', 'sleep_goal_hours', 'walking_goal_steps', 'calorie_goal', 'weight_goal'];

function togglePeriod() {
  $('periodBox').style.display = $('sex').value === 'female' ? 'block' : 'none';
}

async function loadProfile() {
  const p = await api('/profile');
  if (!p) return;
  $('sex').value = p.sex || '';
  $('period_status').value = p.period_status || 'active';
  goalIds.forEach(id => $(id).value = p[id] ?? '');
  togglePeriod();
}

async function saveProfile() {
  const body = { sex: $('sex').value };
  if (body.sex === 'female') body.period_status = $('period_status').value;
  goalIds.forEach(id => { if ($(id).value !== '') body[id] = $(id).value; });

  const data = await api('/profile', 'POST', body);
  if (data.id) location.href = '/dashboard';
  else $('msg').textContent = errText(data);
}

loadProfile();
</script>
@endpush