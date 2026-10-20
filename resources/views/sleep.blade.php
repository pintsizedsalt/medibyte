@extends('layouts.app')

@section('content')
  <h2>Water</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/sleep-logs',
  fields: [['hours', 'Hours slept', 'number'], ['log_date', 'Date', 'today']],
  show: r => r.hours + ' hours (' + ld(r.log_date) + ')'
});
</script>
@endpush