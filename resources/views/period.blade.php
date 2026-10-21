@extends('layouts.app')

@section('title', 'Cycle · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Cycle',
    'description' => 'Log your period and see an estimated next start date. This section is only visible when cycle tracking is enabled.',
    'eyebrow' => 'Wellbeing',
  ])
@endsection

@push('scripts')
<script>
api('/profile').then(p => {
  if (!p || !p.show_period_tracker) { location.href = '/dashboard'; return; }
  setCycleVisibility(true);

  logPage({
    title: 'Cycle',
    icon: 'cycle',
    tone: 'rose',
    url: '/period-logs',
    formTitle: 'Log a period',
    formNote: 'Add the first day and, if it has finished, the last day. Leave the last day blank for an ongoing period.',
    fields: [
      ['start_date', 'First day', 'today'],
      ['end_date', 'Last day (leave blank if ongoing)', 'date'],
      ['flow', 'Flow', 'select', ['', 'light', 'medium', 'heavy']],
      ['notes', 'Notes', 'text']
    ],
    value: r => dateLabel(r.start_date) + ' – ' + (r.end_date ? dateLabel(r.end_date) : 'ongoing'),
    meta: r => [r.flow ? r.flow.replace(/^./, c => c.toUpperCase()) + ' flow' : null, r.notes || null].filter(Boolean).join(' · ')
  });
});
</script>
@endpush
