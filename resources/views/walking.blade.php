@extends('layouts.app')

@section('title', 'Activity · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Activity',
    'description' => 'From a short walk to a full workout, keep a simple record of daily movement.',
    'eyebrow' => 'Everyday',
  ])
@endsection

@push('scripts')
<script>
logPage({
  title: 'Activity',
  icon: 'activity',
  tone: 'mint',
  url: '/walking-logs',
  formTitle: 'Log some movement',
  formNote: 'Steps and active minutes are both optional, but add at least one.',
  fields: [
    ['activity_type', 'Activity', 'select', ['walking', 'jogging', 'running', 'workout']],
    ['steps', 'Steps (optional)', 'number'],
    ['minutes', 'Minutes (optional)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  value: r => (r.activity_type || 'Activity').charAt(0).toUpperCase() + (r.activity_type || 'activity').slice(1),
  meta: r => [r.steps ? compactNumber(r.steps) + ' steps' : null, r.minutes ? r.minutes + ' min' : null, dateLabel(r.log_date)].filter(Boolean).join(' · ')
});
</script>
@endpush
