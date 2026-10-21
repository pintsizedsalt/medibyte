@extends('layouts.app')

@section('title', 'Weight & BMI · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Weight & BMI',
    'description' => 'See how your weight changes over time. BMI is calculated automatically from height and weight.',
    'eyebrow' => 'Wellbeing',
  ])
@endsection

@push('scripts')
<script>
logPage({
  title: 'Weight',
  icon: 'scale',
  tone: 'slate',
  url: '/weight-logs',
  formTitle: 'Add a weight entry',
  formNote: 'Add your height so MediByte can calculate your BMI. BMI is a screening tool, not a diagnosis.',
  fields: [
    ['weight_kg', 'Weight (kg)', 'number'],
    ['height_cm', 'Height (cm)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  value: r => r.weight_kg + ' kg',
  meta: r => [r.bmi ? 'BMI ' + r.bmi : null, r.height_cm ? r.height_cm + ' cm' : null, dateLabel(r.log_date)].filter(Boolean).join(' · ')
});
</script>
@endpush
