@extends('layouts.app')

@section('content')
  <h2>Water</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/water-logs',
  fields: [
    ['amount_ml', 'Amount (ml)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  show: r => r.amount_ml + ' ml (' + ld(r.log_date) + ')'
});
</script>
@endpush