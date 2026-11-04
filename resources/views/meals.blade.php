@extends('layouts.app')

@section('title', 'Meals · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Meals',
    'description' => 'A lightweight food diary for meals, snacks, and the calories that come with them.',
    'eyebrow' => 'Everyday',
  ])
@endsection

@push('scripts')
<script>
logPage({
  title: 'Meals',
  icon: 'meal',
  tone: 'mint',
  url: '/meal-logs',
  formTitle: 'Add a meal',
  formNote: 'Calories are optional. Enter 0 if you don\'t have the calorie information.',
  fields: [
    ['meal_type', 'Meal', 'select', ['breakfast', 'lunch', 'dinner', 'snack']],
    ['food_name', 'Food', 'text'],
    ['meal_time', 'Time', 'time'],
    ['calories', 'Calories (optional)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  value: r => r.food_name,
  meta: r => [
    (r.meal_type || '').replace(/^./, c => c.toUpperCase()),
    r.meal_time ? lt(r.meal_time) : null,
    r.calories !== null && r.calories !== undefined ? r.calories + ' cal' : null,
    dateLabel(r.log_date)
  ].filter(Boolean).join(' · ')
});
</script>
@endpush