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
  tone: 'amber',
  url: '/meal-logs',
  formTitle: 'Add a meal',
  formNote: 'A short description is enough. Calories are optional.',
  fields: [
    ['meal_type', 'Meal', 'select', ['breakfast', 'lunch', 'dinner', 'snack']],
    ['food_name', 'Food', 'text'],
    ['calories', 'Calories', 'number'],
    ['log_date', 'Date', 'today']
  ],
  value: r => r.food_name,
  meta: r => [(r.meal_type || '').replace(/^./, c => c.toUpperCase()), r.calories ? r.calories + ' cal' : null, dateLabel(r.log_date)].filter(Boolean).join(' · ')
});
</script>
@endpush
