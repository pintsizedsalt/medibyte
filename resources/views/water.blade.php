@extends('layouts.app')

@section('title', 'Water · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Water',
    'description' => 'Every glass counts. Log what you drink and watch your daily hydration add up.',
    'eyebrow' => 'Everyday',
  ])
@endsection

@push('scripts')
<script>
logPage({
  title: 'Water',
  icon: 'droplet',
  tone: 'blue',
  url: '/water-logs',
  formTitle: 'Add a water entry',
  formNote: 'Add one glass at a time or enter the total amount you have had so far.',
  fields: [
    ['amount_ml', 'Amount (ml)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  value: r => waterLabel(r.amount_ml),
  meta: r => dateLabel(r.log_date)
});
</script>
@endpush
