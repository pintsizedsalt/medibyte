@extends('layouts.app')

@section('content')
  <h2>Activity</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/walking-logs',
  fields: [
    ['activity_type', 'Activity', 'select', ['walking', 'jogging', 'running', 'workout']],
    ['steps', 'Steps (optional)', 'number'],
    ['minutes', 'Minutes (optional)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  show: r => `${r.activity_type}: ${r.steps ?? 0} steps, ${r.minutes ?? 0} min (${ld(r.log_date)})`
});
</script>
@endpush