@extends('layouts.app')

@section('title', 'Set up your profile · MediByte')
@section('body-class', 'is-onboarding')

@section('content')
  <div class="wizard-shell">
    <div class="wizard-card">
      <div class="wizard-top">
        <span class="wizard-brand"><span class="wizard-orb" aria-hidden="true"></span>MediByte</span>
        <span class="wizard-pill">Set up your account</span>
      </div>
      <div class="wizard-progress" aria-hidden="true"><span id="wizard-progress"></span></div>
      <div id="wizard-body">
        <p class="wizard-copy">Loading your setup…</p>
      </div>
      <p id="wizard-error" class="wizard-error" role="alert"></p>
    </div>
  </div>
@endsection

@push('scripts')
<script>
const MOOD_FALLBACK = [
  'happy', 'excited', 'grateful', 'calm', 'content', 'hopeful',
  'proud', 'loved', 'energetic', 'motivated', 'relaxed',
  'bored', 'tired', 'neutral', 'confused',
  'lonely', 'sad', 'anxious', 'stressed', 'overwhelmed',
  'angry', 'frustrated', 'irritated', 'disappointed',
  'guilty', 'embarrassed', 'scared', 'numb'
];

let profile = null;
let steps = [];
let current = 0;
let answers = {};
let saved = {};
let moods = MOOD_FALLBACK;
let busy = false;

function setError(message) {
  const el = $('wizard-error');
  if (el) el.textContent = message || '';
}

function clearError() {
  setError('');
}

function stepHeader(title, copy) {
  return `<p class="wizard-kicker">Step ${current + 1} of ${steps.length}</p>
    <h1 class="wizard-title">${esc(title)}</h1>
    <p class="wizard-copy">${esc(copy)}</p>`;
}

function optionCard(key, value, title, description) {
  const selected = String(answers[key] ?? '') === String(value);
  return `<button type="button" class="option-card ${selected ? 'selected' : ''}" onclick="window.wizardChoose('${key}', '${value}')">
    <span>
      <strong>${esc(title)}</strong>
      <small>${esc(description)}</small>
    </span>
  </button>`;
}

function wizardActions(skip = true, label = 'Continue') {
  return `<div class="wizard-actions">
    ${current > 0 ? '<button type="button" class="btn btn-ghost" onclick="window.wizardBack()">Back</button>' : ''}
    <span class="spacer"></span>
    ${skip ? '<button type="button" class="btn btn-ghost" onclick="window.wizardSkip()">Skip for now</button>' : ''}
    <button type="submit" class="btn btn-primary">${esc(label)}</button>
  </div>`;
}

function stepForm(content, skip = true, label = 'Continue') {
  return `<form onsubmit="event.preventDefault(); window.wizardNext()">${content}${wizardActions(skip, label)}</form>`;
}

function buildSteps() {
  const list = [];

  if (profile && profile.sex === 'female') list.push('cycle');

  list.push('activity', 'water', 'sleep', 'meals', 'body', 'weight_goal', 'mood', 'illness');

  if (answers.illness === 'yes') {
    list.push('symptoms', 'vitals', 'medications', 'reminders');
  }

  list.push('goals', 'done');
  steps = list;

  if (current >= steps.length) current = steps.length - 1;
}

function stepCycle() {
  const active = answers.period_status === 'active';

  return stepForm(stepHeader(
    'Your menstrual cycle',
    'This helps MediByte personalize cycle tracking and predictions. You can change this later in your profile.'
  ) + `
    <div class="option-grid">
      ${optionCard('period_status', 'active', 'Yes, I currently have a cycle', 'Track periods and see an estimated next start date.')}
      ${optionCard('period_status', 'not_yet', 'Not yet', 'Cycle tracking will stay hidden for now.')}
      ${optionCard('period_status', 'menopause', 'No, I am in menopause', 'Cycle tracking will stay hidden for now.')}
      ${optionCard('period_status', 'prefer_not_to_track', 'I prefer not to track', 'You can enable cycle tracking later in your profile.')}
    </div>
    ${active ? `<div class="wizard-fields">
      <label class="field">
        <span>First day of your last period</span>
        <input id="w_last_period" type="date" value="${esc(answers.last_period || '')}">
      </label>
      <label class="field">
        <span>Typical cycle length (days)</span>
        <input id="w_cycle_length" type="number" min="20" max="45" value="${esc(answers.cycle_length || 28)}">
      </label>
      <p class="wizard-note">If you add your last period, we can estimate when your next one may start.</p>
    </div>` : ''}
  `, false);
}

function stepActivity() {
  const activity = answers.activity || {};

  return stepForm(stepHeader(
    'Daily movement',
    'Would you like to log physical activity today? Add steps, active minutes, or both.'
  ) + `
    <div class="wizard-fields">
      <label class="field">
        <span>Activity type</span>
        <select id="w_activity_type">
          ${['walking', 'jogging', 'running', 'workout'].map(type => `<option value="${type}" ${activity.activity_type === type ? 'selected' : ''}>${type.charAt(0).toUpperCase() + type.slice(1)}</option>`).join('')}
        </select>
      </label>
      <label class="field">
        <span>Steps (optional)</span>
        <input id="w_steps" type="number" min="0" placeholder="e.g. 3000" value="${esc(activity.steps || '')}">
      </label>
      <label class="field">
        <span>Active minutes (optional)</span>
        <input id="w_minutes" type="number" min="1" max="1440" placeholder="e.g. 30" value="${esc(activity.minutes || '')}">
      </label>
      <p class="wizard-note">You can log more activities from the Activity page.</p>
    </div>
  `);
}

function stepWater() {
  return stepForm(stepHeader(
    'Hydration',
    'How much water have you had today? This is optional, and you can add more throughout the day.'
  ) + `
    <div class="wizard-fields">
      <label class="field">
        <span>Water today (ml)</span>
        <input id="w_water" type="number" min="1" max="10000" placeholder="e.g. 500" value="${esc(answers.water_ml || '')}">
      </label>
      <div class="quick-log">
        ${[250, 500, 750, 1000].map(amount => `<button type="button" class="btn btn-small" onclick="document.getElementById('w_water').value = ${amount}">${amount} ml</button>`).join('')}
      </div>
    </div>
  `);
}

function stepSleep() {
  const sleep = answers.sleep || {};

  return stepForm(stepHeader(
    'Sleep',
    'How did you sleep last night? Add bedtime and wake-up time, or enter the total hours.'
  ) + `
    <div class="wizard-fields">
      <label class="field">
        <span>Bedtime</span>
        <input id="w_bedtime" type="time" value="${esc(sleep.bedtime || '')}">
      </label>
      <label class="field">
        <span>Wake-up time</span>
        <input id="w_wake_time" type="time" value="${esc(sleep.wake_time || '')}">
      </label>
      <label class="field">
        <span>Or total hours slept</span>
        <input id="w_hours" type="number" min="0" max="24" step="0.25" placeholder="e.g. 7.5" value="${esc(sleep.hours || '')}">
      </label>
    </div>
  `);
}

function stepMeals() {
  const meal = answers.meal || {};

  return stepForm(stepHeader(
    'Meals',
    'Log your first meal of the day. You can add the rest from the Meals page.'
  ) + `
    <div class="wizard-fields">
      <label class="field">
        <span>Meal</span>
        <select id="w_meal_type">
          ${['breakfast', 'lunch', 'dinner', 'snack'].map(type => `<option value="${type}" ${meal.meal_type === type ? 'selected' : ''}>${type.charAt(0).toUpperCase() + type.slice(1)}</option>`).join('')}
        </select>
      </label>
      <label class="field">
        <span>What did you eat?</span>
        <input id="w_food_name" type="text" placeholder="e.g. Oatmeal with fruit" value="${esc(meal.food_name || '')}">
      </label>
      <label class="field">
        <span>Calories (optional)</span>
        <input id="w_calories" type="number" min="0" placeholder="e.g. 350" value="${esc(meal.calories || '')}">
      </label>
    </div>
  `);
}

function stepBody() {
  const body = answers.body || {};

  return stepForm(stepHeader(
    'Height and weight',
    'We use these to compute your BMI. BMI is a screening tool, not a diagnosis.'
  ) + `
    <div class="wizard-fields">
      <label class="field">
        <span>Height (cm)</span>
        <input id="w_height_cm" type="number" min="50" max="260" step="0.1" placeholder="e.g. 170" value="${esc(body.height_cm || '')}" oninput="window.updateBmiPreview()">
      </label>
      <label class="field">
        <span>Weight (kg)</span>
        <input id="w_weight_kg" type="number" min="20" max="400" step="0.1" placeholder="e.g. 65" value="${esc(body.weight_kg || '')}" oninput="window.updateBmiPreview()">
      </label>
      <div class="wizard-success" id="bmi-preview">BMI will appear here.</div>
    </div>
  `);
}

function stepWeightGoal() {
  const hasGoal = answers.weight_goal_choice === 'yes';

  return stepForm(stepHeader(
    'Weight goal',
    'Do you have a weight goal you would like to work toward? You can set or change this later.'
  ) + `
    <div class="option-grid">
      ${optionCard('weight_goal_choice', 'yes', 'Yes, I have a goal', 'Add the weight you are working toward.')}
      ${optionCard('weight_goal_choice', 'no', 'Not right now', 'You can set a goal later in your profile.')}
    </div>
    ${hasGoal ? `<div class="wizard-fields">
      <label class="field">
        <span>Goal weight (kg)</span>
        <input id="w_weight_goal" type="number" min="20" max="400" step="0.1" placeholder="e.g. 60" value="${esc(answers.weight_goal || '')}">
      </label>
    </div>` : ''}
  `, false);
}

function stepMood() {
  const intensity = Number(answers.intensity || 3);
  const selectedMood = answers.mood || '';

  const groups = {
    positive: ['happy','excited','grateful','calm','content','hopeful','proud','loved','energetic','motivated','relaxed'],
    neutral:  ['neutral','bored','tired','confused'],
    low:      ['lonely','sad','anxious','stressed','overwhelmed','angry','frustrated','irritated','disappointed','guilty','embarrassed','scared','numb']
  };

  let activeCat = answers._moodCat || 'positive';
  if (!answers._moodCat && selectedMood) {
    if (groups.low.includes(selectedMood)) activeCat = 'low';
    else if (groups.neutral.includes(selectedMood)) activeCat = 'neutral';
  }

  const chips = (groups[activeCat] || groups.positive).map(m => {
    const label = m.charAt(0).toUpperCase() + m.slice(1);
    const on = selectedMood === m ? ' active' : '';
    return `<button type="button" class="mood-chip${on}" data-mood="${m}" onclick="window.wizardPickMood('${m}')">${label}</button>`;
  }).join('');

  const segs = [1,2,3,4,5].map(n => {
    const on = intensity === n ? 'checked' : '';
    return `<input type="radio" id="w_int_${n}" name="w_intensity" value="${n}" ${on} onchange="window.wizardSetIntensity(${n})">
            <label for="w_int_${n}">${n}</label>`;
  }).join('');

  return stepForm(stepHeader(
    'How are you feeling?',
    'Choose the mood that fits you best right now and how strong it feels.'
  ) + `
    <div class="wizard-fields">
      <div class="field">
        <span>Mood</span>
        <div class="mood-cat-tabs">
          <button type="button" class="mood-cat${activeCat==='positive'?' active':''}" onclick="window.wizardMoodCat('positive')">Positive</button>
          <button type="button" class="mood-cat${activeCat==='neutral'?' active':''}" onclick="window.wizardMoodCat('neutral')">Neutral</button>
          <button type="button" class="mood-cat${activeCat==='low'?' active':''}" onclick="window.wizardMoodCat('low')">Low</button>
        </div>
        <div class="mood-chips" id="w_mood_chips">${chips}</div>
        <input type="hidden" id="w_mood" value="${esc(selectedMood)}">
      </div>

      <div class="field">
        <span>Intensity (1 to 5)</span>
        <div class="intensity-seg" role="radiogroup" aria-label="Intensity from 1 to 5">
          ${segs}
        </div>
        <input type="hidden" id="w_intensity" value="${intensity}">
      </div>

      <label class="field">
        <span>Anything you want to note? (optional)</span>
        <textarea id="w_reflection" rows="3" placeholder="A short note about your day or how you feel.">${esc(answers.reflection || '')}</textarea>
      </label>
    </div>
  `);
}

window.wizardMoodCat = function(cat) {
  answers._moodCat = cat;
  const groups = {
    positive: ['happy','excited','grateful','calm','content','hopeful','proud','loved','energetic','motivated','relaxed'],
    neutral:  ['neutral','bored','tired','confused'],
    low:      ['lonely','sad','anxious','stressed','overwhelmed','angry','frustrated','irritated','disappointed','guilty','embarrassed','scared','numb']
  };
  if (answers.mood && !(groups[cat] || []).includes(answers.mood)) {
    answers.mood = '';
  }
  render();
};

window.wizardPickMood = function(mood) {
  answers.mood = mood;
  const el = $('w_mood');
  if (el) el.value = mood;
  document.querySelectorAll('#w_mood_chips .mood-chip').forEach(c => {
    c.classList.toggle('active', c.getAttribute('data-mood') === mood);
  });
};

window.wizardSetIntensity = function(n) {
  answers.intensity = n;
  const el = $('w_intensity');
  if (el) el.value = n;
};

function stepIllness() {
  return stepForm(stepHeader(
    'Health conditions',
    'Are you currently managing an illness or health condition? This is optional and stays private to your account.'
  ) + `
    <div class="option-grid">
      ${optionCard('illness', 'yes', 'Yes, I am', 'You can log symptoms, vital signs, medications, and reminders next.')}
      ${optionCard('illness', 'no', 'No, not right now', 'You can add health records later from the dashboard.')}
    </div>
  `, false);
}

function stepSymptoms() {
  const symptom = answers.symptom || {};

  return stepForm(stepHeader(
    'Signs and symptoms',
    'Log one symptom you are experiencing. You can add more from the Symptoms page later.'
  ) + `
    <div class="wizard-fields">
      <label class="field">
        <span>Condition or illness (optional)</span>
        <input id="w_illness_name" type="text" placeholder="e.g. Migraine" value="${esc(symptom.illness_name || '')}">
      </label>
      <label class="field">
        <span>Symptom</span>
        <input id="w_symptom" type="text" placeholder="e.g. Headache" value="${esc(symptom.symptom || '')}">
      </label>
      <label class="field">
        <span>Severity (1 = mild, 5 = severe)</span>
        <select id="w_severity">
          ${[1, 2, 3, 4, 5].map(level => `<option value="${level}" ${Number(symptom.severity || 1) === level ? 'selected' : ''}>${level} / 5</option>`).join('')}
        </select>
      </label>
      <label class="field">
        <span>Notes (optional)</span>
        <textarea id="w_notes" rows="2" placeholder="Anything else you want to remember.">${esc(symptom.notes || '')}</textarea>
      </label>
    </div>
  `);
}

function stepVitals() {
  const vitals = answers.vitals || {};

  return stepForm(stepHeader(
    'Vital signs',
    'Add any recent readings you have. Leave the fields blank if you do not have them.'
  ) + `
    <div class="wizard-fields">
      <label class="field">
        <span>Blood pressure — top number</span>
        <input id="w_systolic" type="number" min="30" max="300" placeholder="e.g. 120" value="${esc(vitals.systolic || '')}">
      </label>
      <label class="field">
        <span>Blood pressure — bottom number</span>
        <input id="w_diastolic" type="number" min="20" max="200" placeholder="e.g. 80" value="${esc(vitals.diastolic || '')}">
      </label>
      <label class="field">
        <span>Heart rate (bpm)</span>
        <input id="w_heart_rate" type="number" min="20" max="300" placeholder="e.g. 72" value="${esc(vitals.heart_rate || '')}">
      </label>
      <label class="field">
        <span>Temperature (°C)</span>
        <input id="w_temperature_c" type="number" min="30" max="45" step="0.1" placeholder="e.g. 36.6" value="${esc(vitals.temperature_c || '')}">
      </label>
      <label class="field">
        <span>Oxygen saturation (%)</span>
        <input id="w_oxygen_saturation" type="number" min="50" max="100" placeholder="e.g. 98" value="${esc(vitals.oxygen_saturation || '')}">
      </label>
    </div>
  `);
}

function stepMedications() {
  const medication = answers.medication || {};

  return stepForm(stepHeader(
    'Medications',
    'Add a medication you are taking. Include the times you usually take it so reminders can work.'
  ) + `
    <div class="wizard-fields">
      <label class="field">
        <span>Medication name</span>
        <input id="w_med_name" type="text" placeholder="e.g. Paracetamol" value="${esc(medication.name || '')}">
      </label>
      <label class="field">
        <span>Dosage (optional)</span>
        <input id="w_med_dosage" type="text" placeholder="e.g. 500 mg" value="${esc(medication.dosage || '')}">
      </label>
      <label class="field">
        <span>Times (24-hour, separated by commas)</span>
        <input id="w_med_times" type="text" placeholder="e.g. 08:00, 20:00" value="${esc(medication.times || '')}">
      </label>
      <label class="field">
        <span>Food instruction</span>
        <select id="w_food_instruction">
          ${['anytime', 'before_meal', 'after_meal', 'with_meal'].map(value => `<option value="${value}" ${medication.food_instruction === value ? 'selected' : ''}>${value.replace(/_/g, ' ')}</option>`).join('')}
        </select>
      </label>
      <label class="field">
        <span>Start date</span>
        <input id="w_start_date" type="date" value="${esc(medication.start_date || today())}">
      </label>
      <label class="check-row">
        <input id="w_is_active" type="checkbox" ${medication.is_active === false ? '' : 'checked'}>
        <span>I am currently taking this medication</span>
      </label>
      <label class="field">
        <span>Notes (optional)</span>
        <textarea id="w_med_notes" rows="2" placeholder="Anything your future self should know.">${esc(medication.notes || '')}</textarea>
      </label>
    </div>
  `);
}

function stepReminders() {
  return stepForm(stepHeader(
    'Reminders',
    'Would you like MediByte to remind you about medications and appointments?'
  ) + `
    <div class="option-grid">
      ${optionCard('reminders_enabled', 'yes', 'Yes, remind me', 'Show upcoming medication times and appointments on your dashboard.')}
      ${optionCard('reminders_enabled', 'no', 'Not right now', 'You can turn reminders on later in Settings.')}
    </div>
  `, false);
}

function stepGoals() {
  return stepForm(stepHeader(
    'Daily goals',
    'Set targets that feel realistic. You can update them anytime in your profile.'
  ) + `
    <div class="wizard-fields">
      <label class="field">
        <span>Water goal (ml)</span>
        <input id="w_water_goal" type="number" min="500" step="100" value="${esc(answers.water_goal_ml || 2000)}">
      </label>
      <label class="field">
        <span>Sleep goal (hours)</span>
        <input id="w_sleep_goal" type="number" min="1" max="16" step="0.5" value="${esc(answers.sleep_goal_hours || 8)}">
      </label>
      <label class="field">
        <span>Daily steps goal</span>
        <input id="w_steps_goal" type="number" min="0" step="500" value="${esc(answers.walking_goal_steps || 8000)}">
      </label>
      <label class="field">
        <span>Daily calorie goal (optional)</span>
        <input id="w_calorie_goal" type="number" min="500" placeholder="e.g. 2000" value="${esc(answers.calorie_goal || '')}">
      </label>
      ${answers.prediction && answers.prediction.predicted_next_start ? `<div class="wizard-success">Estimated next period: <strong>${esc(answers.prediction.predicted_next_start)}</strong> (about ${esc(answers.prediction.days_until_next)} days from today).</div>` : ''}
    </div>
  `, false, 'Finish setup');
}

function stepDone() {
  const fullName = String(
    (profile && (profile.first_name || profile.name || profile.full_name)) ||
    answers.first_name || answers.name || ''
  ).trim();
  const first = fullName.split(' ')[0];

  const svg = d => `<svg class="done-ic" viewBox="0 0 24 24" aria-hidden="true">${d}</svg>`;
  const icons = {
    water: svg('<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>'),
    sleep: svg('<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>'),
    steps: svg('<path d="M3 12h4l3-8 4 16 3-8h4"/>')
  };

  const goals = [
    [answers.water_goal_ml, v => Number(v).toLocaleString() + ' ml', 'Water', icons.water],
    [answers.sleep_goal_hours, v => Number(v) + ' hours', 'Sleep', icons.sleep],
    [answers.walking_goal_steps, v => Number(v).toLocaleString(), 'Steps', icons.steps]
  ].filter(g => g[0]).map(g => `
    <div class="done-tile">
      <div class="done-ic-wrap">${g[3]}</div>
      <strong>${esc(g[1](g[0]))}</strong>
      <small>${esc(g[2])}</small>
    </div>`).join('');

  const goalsHtml = goals
    ? `<p class="done-label">Your daily goals</p><div class="done-goals">${goals}</div>`
    : '<p class="done-note">You can set daily goals in your profile.</p>';

  const prediction = answers.prediction && answers.prediction.predicted_next_start
    ? '<p class="done-note">Estimated next period: ' + esc(answers.prediction.predicted_next_start) + '</p>'
    : '';

  return `<div class="wizard-step">
    <p class="wizard-kicker">Step ${current + 1} of ${steps.length}</p>
    <div class="done-hero">
      <div class="done-check"><svg class="icon"><use href="#i-check"/></svg></div>
      <h2 class="done-title">Welcome to MediByte${first ? ', ' + esc(first) : ''}</h2>
      <p class="done-copy">Your profile is ready. You can change any of these details later from your profile.</p>
    </div>
    ${goalsHtml}
    ${prediction}
    <div class="done-actions">
      <button type="button" class="btn-ripple" onclick="location.replace('/dashboard')">
        <span class="animation"></span>
        Go to dashboard
        <span class="animation"></span>
      </button>
      <span class="done-note">Adjust goals anytime in Profile</span>
    </div>
  </div>`;
}

function render() {
  const key = steps[current];
  const progress = ((current + 1) / steps.length) * 100;
  $('wizard-progress').style.width = progress + '%';
  clearError();

  const builders = {
    cycle: stepCycle,
    activity: stepActivity,
    water: stepWater,
    sleep: stepSleep,
    meals: stepMeals,
    body: stepBody,
    weight_goal: stepWeightGoal,
    mood: stepMood,
    illness: stepIllness,
    symptoms: stepSymptoms,
    vitals: stepVitals,
    medications: stepMedications,
    reminders: stepReminders,
    goals: stepGoals,
    done: stepDone
  };

  $('wizard-body').innerHTML = builders[key]();
  if (key === 'body') window.updateBmiPreview();

  const firstField = $('wizard-body').querySelector('input, select, textarea, button.option-card');
  if (firstField && key !== 'done') firstField.focus({ preventScroll: true });
}

function advance() {
  if (current < steps.length - 1) {
    current++;
    render();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

window.wizardChoose = (key, value) => {
  answers[key] = value;

  if (key === 'illness') buildSteps();
  if (key === 'weight_goal_choice' && value === 'no') answers.weight_goal = '';
  if (key === 'period_status' && value !== 'active') answers.prediction = null;

  render();
};

window.wizardBack = () => {
  if (current > 0) {
    current--;
    render();
  }
};

window.wizardSkip = () => {
  const key = steps[current];
  const required = ['cycle', 'weight_goal', 'illness', 'reminders', 'goals', 'done'];
  if (required.includes(key)) return;
  advance();
};

window.updateBmiPreview = () => {
  const preview = $('bmi-preview');
  if (!preview) return;

  const height = parseFloat($('w_height_cm')?.value || '0');
  const weight = parseFloat($('w_weight_kg')?.value || '0');

  if (height > 0 && weight > 0) {
    const bmi = weight / Math.pow(height / 100, 2);
    let label = 'Healthy range';
    if (bmi < 18.5) label = 'Below the healthy range';
    else if (bmi >= 25 && bmi < 30) label = 'Above the healthy range';
    else if (bmi >= 30) label = 'Well above the healthy range';

    preview.textContent = `Estimated BMI: ${bmi.toFixed(1)} — ${label}`;
  } else {
    preview.textContent = 'BMI will appear here.';
  }
};

async function saveProfile(data) {
  const result = await api('/profile', 'POST', data);
  if (result && result.id) {
    profile = Object.assign({}, profile, result);
    return true;
  }
  setError(errText(result));
  return false;
}

async function upsert(key, url, body) {
  const id = saved[key];
  const result = await api(url + (id ? '/' + id : ''), id ? 'PUT' : 'POST', body);
  if (result && result.id) {
    saved[key] = result.id;
    return result;
  }
  setError(errText(result));
  return null;
}

async function saveCycle() {
  if (!answers.period_status) {
    setError('Please choose how you would like to handle cycle tracking.');
    return false;
  }

  const cycleLength = $('w_cycle_length')?.value;
  const lastPeriod = $('w_last_period')?.value;
  const body = { period_status: answers.period_status };
  if (cycleLength) body.cycle_length = cycleLength;
  if (cycleLength) answers.cycle_length = cycleLength;
  if (lastPeriod) answers.last_period = lastPeriod;

  if (!(await saveProfile(body))) return false;

  if (answers.period_status === 'active') {
    if (lastPeriod) {
      const period = await upsert('period', '/period-logs', { start_date: lastPeriod });
      if (!period) return false;
    }

    const prediction = await api('/period-predict');
    if (prediction && prediction.predicted_next_start) {
      answers.prediction = prediction;
    }
  }

  return true;
}

async function saveActivity() {
  const type = $('w_activity_type').value;
  const stepsValue = $('w_steps').value.trim();
  const minutesValue = $('w_minutes').value.trim();

  if (!stepsValue && !minutesValue) {
    setError('Add steps or active minutes, or choose “Skip for now”.');
    return false;
  }

  const body = { activity_type: type, log_date: today() };
  if (stepsValue) body.steps = stepsValue;
  if (minutesValue) body.minutes = minutesValue;

  const result = await upsert('activity', '/walking-logs', body);
  if (result) answers.activity = { activity_type: type, steps: stepsValue, minutes: minutesValue };
  return Boolean(result);
}

async function saveWater() {
  const amount = $('w_water').value.trim();
  if (!amount) {
    setError('Enter an amount, or choose “Skip for now”.');
    return false;
  }

  const result = await upsert('water', '/water-logs', { amount_ml: amount, log_date: today() });
  if (result) answers.water_ml = amount;
  return Boolean(result);
}

async function saveSleep() {
  const bedtime = $('w_bedtime').value;
  const wakeTime = $('w_wake_time').value;
  const hours = $('w_hours').value.trim();

  if (!bedtime && !wakeTime && !hours) {
    setError('Add your sleep details, or choose “Skip for now”.');
    return false;
  }

  if ((bedtime && !wakeTime) || (!bedtime && wakeTime)) {
    setError('Please add both bedtime and wake-up time, or use total hours instead.');
    return false;
  }

  const body = { log_date: today() };

  if (bedtime && wakeTime) {
    body.bedtime = bedtime;
    body.wake_time = wakeTime;
    body.hours = null;
  } else {
    body.hours = hours;
    body.bedtime = null;
    body.wake_time = null;
  }

  const result = await upsert('sleep', '/sleep-logs', body);
  if (result) answers.sleep = { bedtime, wake_time: wakeTime, hours };
  return Boolean(result);
}

async function saveMeal() {
  const mealType = $('w_meal_type').value;
  const foodName = $('w_food_name').value.trim();
  const calories = $('w_calories').value.trim();

  if (!foodName) {
    setError('Tell us what you ate, or choose “Skip for now”.');
    return false;
  }

  const result = await upsert('meal', '/meal-logs', {
    meal_type: mealType,
    food_name: foodName,
    calories: calories || 0,
    log_date: today()
  });

  if (result) answers.meal = { meal_type: mealType, food_name: foodName, calories };
  return Boolean(result);
}

async function saveBody() {
  const height = $('w_height_cm').value.trim();
  const weight = $('w_weight_kg').value.trim();

  if (!height || !weight) {
    setError('Add both height and weight, or choose “Skip for now”.');
    return false;
  }

  const result = await upsert('body', '/weight-logs', {
    height_cm: height,
    weight_kg: weight,
    log_date: today()
  });

  if (result) answers.body = { height_cm: height, weight_kg: weight };
  return Boolean(result);
}

async function saveWeightGoal() {
  if (!answers.weight_goal_choice) {
    setError('Please choose an option.');
    return false;
  }

  if (answers.weight_goal_choice === 'yes') {
    const goal = $('w_weight_goal').value.trim();
    if (!goal) {
      setError('Enter your goal weight, or choose “Not right now”.');
      return false;
    }
    answers.weight_goal = goal;
    return saveProfile({ weight_goal: goal });
  }

  answers.weight_goal = '';
  return saveProfile({ weight_goal: null });
}

async function saveMood() {
  const mood = ($('w_mood')?.value || answers.mood || '').trim();
  if (!mood) {
    setError('Please choose a mood, or skip for now.');
    return false;
  }

  const intensity = $('w_intensity')?.value || answers.intensity || 3;
  const reflection = ($('w_reflection')?.value || '').trim();

  const body = { mood, intensity, log_date: today() };
  if (reflection) body.reflection = reflection;

  const result = await upsert('mood', '/mood-logs', body);
  if (result) {
    answers.mood = mood;
    answers.intensity = intensity;
    answers.reflection = reflection;
  }
  return Boolean(result);
}

function saveIllness() {
  if (!answers.illness) {
    setError('Please choose an option.');
    return false;
  }
  return true;
}

async function saveSymptoms() {
  const symptom = $('w_symptom').value.trim();
  const severity = $('w_severity').value;
  const illnessName = $('w_illness_name').value.trim();
  const notes = $('w_notes').value.trim();

  if (!symptom) {
    setError('Add a symptom, or choose “Skip for now”.');
    return false;
  }

  const body = { symptom, severity, log_date: today() };
  if (illnessName) body.illness_name = illnessName;
  if (notes) body.notes = notes;

  const result = await upsert('symptom', '/symptom-logs', body);
  if (result) answers.symptom = { symptom, severity, illness_name: illnessName, notes };
  return Boolean(result);
}

async function saveVitals() {
  const fields = ['systolic', 'diastolic', 'heart_rate', 'temperature_c', 'oxygen_saturation'];
  const body = { log_date: today() };
  let hasValue = false;

  fields.forEach(field => {
    const value = $('w_' + field).value.trim();
    body[field] = value || null;
    if (value) hasValue = true;
  });

  if (!hasValue) {
    setError('Add at least one reading, or choose “Skip for now”.');
    return false;
  }

  if ((body.systolic && !body.diastolic) || (!body.systolic && body.diastolic)) {
    setError('Please add both blood pressure numbers.');
    return false;
  }

  const result = await upsert('vitals', '/vital-sign-logs', body);
  if (result) answers.vitals = body;
  return Boolean(result);
}

async function saveMedication() {
  const name = $('w_med_name').value.trim();
  if (!name) {
    setError('Add the medication name, or choose “Skip for now”.');
    return false;
  }

  const rawTimes = $('w_med_times').value.trim();
  const times = rawTimes ? rawTimes.split(',').map(item => item.trim()).filter(Boolean) : [];

  if (times.some(time => !/^\d{2}:\d{2}$/.test(time))) {
    setError('Use 24-hour times like 08:00, 20:00.');
    return false;
  }

  const body = {
    name,
    times,
    start_date: $('w_start_date').value || today(),
    food_instruction: $('w_food_instruction').value,
    is_active: $('w_is_active').checked
  };

  const dosage = $('w_med_dosage').value.trim();
  const notes = $('w_med_notes').value.trim();
  if (dosage) body.dosage = dosage;
  if (notes) body.notes = notes;

  const result = await upsert('medication', '/medications', body);
  if (result) {
    answers.medication = {
      name,
      dosage,
      times: times.join(', '),
      food_instruction: body.food_instruction,
      start_date: body.start_date,
      is_active: body.is_active,
      notes
    };
  }
  return Boolean(result);
}

async function saveReminders() {
  if (!answers.reminders_enabled) {
    setError('Please choose an option.');
    return false;
  }

  return saveProfile({ reminders_enabled: answers.reminders_enabled === 'yes' });
}

async function saveGoals() {
  const water = $('w_water_goal').value.trim();
  const sleep = $('w_sleep_goal').value.trim();
  const stepsGoal = $('w_steps_goal').value.trim();
  const calories = $('w_calorie_goal').value.trim();

  if (!water || !sleep || !stepsGoal) {
    setError('Please complete the water, sleep, and steps goals.');
    return false;
  }

  const body = {
    water_goal_ml: water,
    sleep_goal_hours: sleep,
    walking_goal_steps: stepsGoal,
    calorie_goal: calories || null
  };

  answers.water_goal_ml = water;
  answers.sleep_goal_hours = sleep;
  answers.walking_goal_steps = stepsGoal;
  answers.calorie_goal = calories;

  if (!(await saveProfile(body))) return false;

  const completed = await api('/profile/complete', 'POST');
  if (!completed || !completed.id) {
    setError(errText(completed));
    return false;
  }

  profile = Object.assign({}, profile, completed);
  return true;
}

window.wizardNext = async () => {
  if (busy) return;
  busy = true;
  clearError();

  const key = steps[current];
  let ok = true;

  try {
    if (key === 'cycle') ok = await saveCycle();
    else if (key === 'activity') ok = await saveActivity();
    else if (key === 'water') ok = await saveWater();
    else if (key === 'sleep') ok = await saveSleep();
    else if (key === 'meals') ok = await saveMeal();
    else if (key === 'body') ok = await saveBody();
    else if (key === 'weight_goal') ok = await saveWeightGoal();
    else if (key === 'mood') ok = await saveMood();
    else if (key === 'illness') {
      ok = saveIllness();
      if (ok) buildSteps();
    } else if (key === 'symptoms') ok = await saveSymptoms();
    else if (key === 'vitals') ok = await saveVitals();
    else if (key === 'medications') ok = await saveMedication();
    else if (key === 'reminders') ok = await saveReminders();
    else if (key === 'goals') ok = await saveGoals();

    if (ok && key !== 'done') advance();
  } catch (error) {
    setError('Something went wrong. Please check your connection and try again.');
  } finally {
    busy = false;
  }
};

(async function initWizard() {
  profile = await api('/profile');

  if (!profile || !profile.id) {
    $('wizard-body').innerHTML = `<p class="wizard-copy">We could not load your profile. Please <a href="/login">log in again</a>.</p>`;
    return;
  }

  if (profile.onboarding_completed_at) {
    location.replace('/dashboard');
    return;
  }

  const moodResponse = await api('/moods');
  if (Array.isArray(moodResponse)) moods = moodResponse;

  answers = {
    period_status: profile.period_status || '',
    cycle_length: profile.cycle_length || 28,
    last_period: '',
    weight_goal_choice: profile.weight_goal ? 'yes' : '',
    weight_goal: profile.weight_goal || '',
    water_goal_ml: profile.water_goal_ml || 2000,
    sleep_goal_hours: profile.sleep_goal_hours || 8,
    walking_goal_steps: profile.walking_goal_steps || 8000,
    calorie_goal: profile.calorie_goal || '',
    reminders_enabled: profile.reminders_enabled ? 'yes' : 'no',
    illness: '',
    mood: 'neutral',
    intensity: 3
  };

  if (profile.show_period_tracker) {
    const logs = await api('/period-logs');
    if (Array.isArray(logs) && logs.length > 0) {
      answers.last_period = ld(logs[0].start_date);
    }
  }

  buildSteps();
  render();
})();
</script>
@endpush