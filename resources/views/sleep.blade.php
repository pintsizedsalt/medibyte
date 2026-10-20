@extends('layouts.app')

@section('content')
  <h2>Sleep</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/sleep-logs',
  fields: [
    ['bedtime', 'Bedtime', 'time'],
    ['wake_time', 'Wake up', 'time'],
    ['hours', 'Hours slept (only if you did not fill bedtime and wake up)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  show: r => `${r.hours} hours${r.bedtime ? ' (' + r.bedtime.slice(0, 5) + ' to ' + r.wake_time.slice(0, 5) + ')' : ''} on ${ld(r.log_date)}`
});
</script>
@endpush