@extends('layouts.app')

@section('title', 'Symptoms · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Symptoms',
    'description' => 'Keep a clear record of symptoms and severity so patterns are easier to discuss with your doctor.',
    'eyebrow' => 'Wellbeing',
  ])
@endsection

@push('scripts')
<script>
logPage({
  title: 'Symptoms',
  icon: 'thermometer',
  tone: 'amber',
  url: '/symptom-logs',
  formTitle: 'Log a symptom',
  formNote: 'Add the symptom and severity. Illness and notes are optional.',
  fields: [
    ['illness_name', 'Illness (optional)', 'text'],
    ['symptom', 'Symptom', 'text'],
    ['severity', 'Severity (1 to 5)', 'select', [1, 2, 3, 4, 5]],
    ['notes', 'Notes', 'text'],
    ['log_date', 'Date', 'today']
  ],
  value: r => (r.symptom || 'Symptom').replace(/^./, c => c.toUpperCase()),
  meta: r => [r.severity ? 'Severity ' + r.severity + '/5' : null, r.illness_name || null, dateLabel(r.log_date)].filter(Boolean).join(' · ')
});
</script>
@endpush
