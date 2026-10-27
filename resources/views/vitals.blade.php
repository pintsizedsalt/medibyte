@extends('layouts.app')

@section('title', 'Vitals · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Vitals',
    'description' => 'Record blood pressure, heart rate, temperature, and oxygen readings in one place.',
    'eyebrow' => 'Wellbeing',
  ])
@endsection

@push('scripts')
<script>
logPage({
  title: 'Vitals',
  icon: 'heart',
  tone: 'rose',
  url: '/vital-sign-logs',
  formTitle: 'Add a vital sign reading',
  formNote: 'Add the readings you have. Leave the fields you did not measure blank.',
  fields: [
    ['systolic', 'Systolic', 'number'],
    ['diastolic', 'Diastolic', 'number'],
    ['heart_rate', 'Heart rate (bpm)', 'number'],
    ['temperature_c', 'Temperature (C)', 'number'],
    ['oxygen_saturation', 'Oxygen (%)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  value: r => r.systolic && r.diastolic ? 'BP ' + r.systolic + '/' + r.diastolic : (r.heart_rate ? r.heart_rate + ' bpm' : 'Vital signs'),
  meta: r => [
    r.heart_rate ? 'HR ' + r.heart_rate : null,
    r.temperature_c ? r.temperature_c + '°C' : null,
    r.oxygen_saturation ? 'O₂ ' + r.oxygen_saturation + '%' : null,
    dateLabel(r.log_date)
  ].filter(Boolean).join(' · ')
});
</script>
@endpush
