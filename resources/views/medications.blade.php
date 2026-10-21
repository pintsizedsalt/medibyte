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
  formNote: 'Use 24-hour times separated by commas, for example 08:00, 20:00.',
  fields: [
    ['name', 'Medication name', 'text'],
    ['dosage', 'Dosage (as prescribed)', 'text'],
    ['times', 'Times (24-hour, separated by commas)', 'list', null, null, 'e.g. 08:00, 20:00'],
    ['food_instruction', 'Food instruction', 'select', ['anytime', 'before_meal', 'after_meal', 'with_meal']],
    ['start_date', 'Start date', 'today'],
    ['end_date', 'End date (optional)', 'date'],
    ['notes', 'Notes (optional)', 'textarea'],
    ['is_active', 'I am currently taking this', 'check', null, true]
  ],
  value: r => r.name + (r.dosage ? ' · ' + r.dosage : ''),
  meta: r => [
    r.times && r.times.length ? r.times.join(', ') : null,
    (r.food_instruction || 'anytime').replace(/_/g, ' '),
    r.is_active === false ? 'Inactive' : null
  ].filter(Boolean).join(' · ')
});
</script>
@endpush
