@extends('layouts.app')

@section('title', 'Medications · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Medications',
    'description' => 'Keep your medication list, dosage, and daily times up to date so reminders stay accurate.',
    'eyebrow' => 'Care',
  ])
@endsection

@push('scripts')
<script>
logPage({
  title: 'Medications',
  icon: 'pill',
  tone: 'plum',
  url: '/medications',
  formTitle: 'Add a medication',
  formNote: 'Pick the time of each dose and add another for every time you take it. Clear a time to drop it. A reminder follows this list.',
  fields: [
    ['name', 'Medication name', 'text'],
    ['dosage_amount', 'Dosage (as prescribed)', 'dose', ['mcg', 'mg', 'g', 'mL', 'L', 'IU', 'mg/mL', 'tablet', 'capsule', 'puff', 'spray', 'drop', 'patch']],
    ['times', 'Dose times', 'timelist'],
    ['food_instruction', 'Food instruction', 'select', ['anytime', 'before_meal', 'after_meal', 'with_meal']],
    ['start_date', 'Start date', 'today'],
    ['end_date', 'End date (optional)', 'date'],
    ['notes', 'Notes (optional)', 'textarea'],
    ['is_active', 'I am currently taking this', 'check', null, true]
  ],
  value: r => r.name + (r.dosage ? ' · ' + r.dosage : ''),
  meta: r => [
    r.times && r.times.length ? r.times.map(clockLabel).join(' · ') : null,
    (r.food_instruction || 'anytime').replace(/_/g, ' '),
    r.is_active === false ? 'Inactive' : null
  ].filter(Boolean).join(' · ')
});
</script>
@endpush
