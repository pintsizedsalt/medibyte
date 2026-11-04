@extends('layouts.app')

@section('title', 'Activity · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Activity',
    'description' => 'Keep a simple record of your daily walking — steps and active minutes.',
    'eyebrow' => 'Everyday',
  ])
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
</script>
@endpush