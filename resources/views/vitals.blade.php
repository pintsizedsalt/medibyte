@extends('layouts.app')

@section('content')
  <h2>Vitals</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/vital-sign-logs',
  fields: [
    ['systolic', 'BP top number', 'number'],
    ['diastolic', 'BP bottom number', 'number'],
    ['heart_rate', 'Heart rate (bpm)', 'number'],
    ['temperature_c', 'Temperature (C)', 'number'],
    ['oxygen_saturation', 'Oxygen (%)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  show: r => `BP ${r.systolic ?? '-'}/${r.diastolic ?? '-'}, HR ${r.heart_rate ?? '-'}, ${r.temperature_c ?? '-'} C, O2 ${r.oxygen_saturation ?? '-'}%`
});
</script>
@endpush