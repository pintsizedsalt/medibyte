@extends('layouts.app')

@section('content')
  <h2>Water</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/meal-logs',
  fields: [
    ['meal_type', 'Meal', 'select', ['breakfast', 'lunch', 'dinner', 'snack']],
    ['food_name', 'Food', 'text'],
    ['calories', 'Calories', 'number'],
    ['log_date', 'Date', 'today']
  ],
  show: r => `${r.meal_type}: ${r.food_name} (${r.calories} cal)`
});
</script>
@endpush