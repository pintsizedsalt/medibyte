@extends('layouts.app')

@section('content')
  <h2>Water</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/medications',
  fields: [
    ['name', 'Medicine', 'text'],
    ['dosage', 'Dosage (as on prescription)', 'text'],
    ['times', 'Times (24h, separate with commas, e.g. 08:00, 20:00)', 'list'],
    ['food_instruction', 'With food?', 'select', ['anytime', 'before_meal', 'after_meal', 'with_meal']],
    ['start_date', 'Start date', 'today'],
    ['end_date', 'End date (optional)', 'date'],
    ['notes', 'Notes', 'text'],
    ['is_active', 'Currently taking', 'check', null, true]
  ],
  show: r => `${r.name} ${r.dosage ?? ''} at ${(r.times || []).join(', ')} (${r.food_instruction.replace('_', ' ')})`
});
</script>
@endpush