@extends('layouts.app')

@section('content')
  <h2 id="hello">Dashboard</h2>
  <p id="msg"></p>

  <h3>Progress today</h3>
  <div id="progress"></div>

  <h3>Weight</h3>
  <p id="weight"></p>

  <h3>Latest mood</h3>
  <p id="mood"></p>

  <h3>Medicines today</h3>
  <div id="meds"></div>

  <h3>Appointments (next 7 days)</h3>
  <div id="apps"></div>

  <div id="periodBox" style="display:none">
    <h3>Period</h3>
    <p id="period"></p>
  </div>
@endsection

@push('scripts')
<script>
async function loadDashboard() {
  const d = await api('/dashboard');
  if (!d.name) { $('msg').textContent = errText(d); return; }

  $('hello').textContent = 'Hi, ' + d.name;

  const labels = { water_ml: 'Water (ml)', sleep_hours: 'Sleep (hours)', walking_steps: 'Steps', calories: 'Calories' };
  $('progress').innerHTML = Object.keys(labels).map(k => {
    const p = d.progress[k];
    const note = p.status === 'goal_met' ? 'Goal met!' : p.status === 'below_goal' ? p.remaining + ' to go' : '';
    return `<p>${labels[k]}: ${p.actual} / ${p.goal ?? 'no goal'} (${p.percent ?? 0}%) ${note}</p>`;
  }).join('');

  const w = d.progress.weight;
  $('weight').textContent = `${w.current_kg ?? '-'} kg, BMI ${w.bmi ?? '-'}`;

  $('mood').textContent = d.latest_mood ? `${d.latest_mood.mood} (${d.latest_mood.intensity}/5)` : 'No mood logged yet.';

  $('meds').innerHTML = d.reminders.medications.map(m =>
    `<p>${m.time} ${m.name} ${m.dosage ?? ''} (${m.food_instruction.replace('_', ' ')})</p>`
  ).join('') || '<p>None today.</p>';

  $('apps').innerHTML = d.reminders.appointments.map(a =>
    `<p>${a.doctor_name}, ${new Date(a.appointment_at).toLocaleString()}</p>`
  ).join('') || '<p>None.</p>';

  if (d.period_prediction) {
    $('periodBox').style.display = 'block';
    $('period').textContent = d.period_prediction.predicted_next_start
      ? 'Next period (estimate): ' + d.period_prediction.predicted_next_start
      : d.period_prediction.message;
  }
}

loadDashboard();
</script>
@endpush