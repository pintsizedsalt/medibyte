@extends('layouts.app')

@section('title', 'Mood · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Mood',
    'description' => 'A private space to notice how you feel, what shaped your day, and what you want to remember.',
    'eyebrow' => 'Wellbeing',
  ])
@endsection

@push('scripts')
<script>
api('/moods').then(moods => logPage({
  title: 'Mood',
  icon: 'smile',
  tone: 'rose',
  url: '/mood-logs',
  formTitle: 'Check in with yourself',
  formNote: 'Choose the mood that fits best. The reflection and event notes are optional.',
  fields: [
    ['mood', 'Mood', 'select', moods],
    ['intensity', 'Intensity (1 to 5)'],
    ['event_note', 'What was the event?', 'text'],
    ['reflection', 'What happened? Share your thoughts.', 'text'],
    ['log_date', 'Date', 'today']
  ],
  value: r => (r.mood || 'Mood').replace(/^./, c => c.toUpperCase()) + ' · ' + r.intensity + '/5',
  meta: r => [dateLabel(r.log_date), r.cried ? 'cried' : null, r.event_note || null].filter(Boolean).join(' · ')
}));
</script>
@endpush
