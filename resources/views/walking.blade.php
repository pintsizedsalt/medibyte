@extends('layouts.app')

@section('content')
  <h2>Water</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/walking-logs',
  fields: [['steps', 'Steps', 'number'], ['log_date', 'Date', 'today']],
  show: r => r.steps + ' steps (' + ld(r.log_date) + ')'
});
</script>
@endpush