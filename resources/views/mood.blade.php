@extends('layouts.app')

@section('content')
  <h2>Water</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
api('/moods').then(moods => logPage({
  url: '/mood-logs',
  fields: [
    ['mood', 'Mood', 'select', moods],
    ['intensity', 'Intensity (1 to 5)', 'select', [1, 2, 3, 4, 5]],
    ['cried', 'I cried today', 'check'],
    ['has_big_event', 'Something big happened today', 'check'],
    ['event_note', 'What was the event?', 'text'],
    ['impacts', 'What affected your mood? (separate with commas)', 'list'],
    ['reflection', 'What happened?', 'text'],
    ['log_date', 'Date', 'today']
  ],
  show: r => `${r.mood} (${r.intensity}/5)${r.cried ? ', cried' : ''}`
}));
</script>
@endpush