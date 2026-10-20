@extends('layouts.app')

@section('content')
  <h2>Water</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/symptom-logs',
  fields: [
    ['illness_name', 'Illness (optional)', 'text'],
    ['symptom', 'Symptom', 'text'],
    ['severity', 'Severity (1 to 5)', 'select', [1, 2, 3, 4, 5]],
    ['notes', 'Notes', 'text'],
    ['log_date', 'Date', 'today']
  ],
  show: r => `${r.symptom} (severity ${r.severity}/5)`
});
</script>
@endpush