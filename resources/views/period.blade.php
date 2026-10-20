@extends('layouts.app')

@section('content')
  <h2>Water</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
api('/profile').then(p => {
  if (!p || !p.show_period_tracker) { location.href = '/dashboard'; return; }

  logPage({
    url: '/period-logs',
    fields: [
      ['start_date', 'First day', 'today'],
      ['end_date', 'Last day (leave blank if ongoing)', 'date'],
      ['flow', 'Flow', 'select', ['', 'light', 'medium', 'heavy']],
      ['notes', 'Notes', 'text']
    ],
    show: r => `${ld(r.start_date)} to ${ld(r.end_date) || 'ongoing'}${r.flow ? ', ' + r.flow : ''}`
  });
});
</script>
@endpush