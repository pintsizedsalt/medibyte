@extends('layouts.app')

@section('content')
  <h2>Water</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/weight-logs',
  fields: [
    ['weight_kg', 'Weight (kg)', 'number'],
    ['height_cm', 'Height (cm)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  show: r => `${r.weight_kg} kg, ${r.height_cm} cm, BMI ${r.bmi}`
});
</script>
@endpush