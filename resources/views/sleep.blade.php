@extends('layouts.app')

@section('title', 'Sleep · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Sleep',
    'description' => 'Track bedtime, wake-up time, and the nights that leave you feeling restored.',
    'eyebrow' => 'Everyday',
  ])
@endsection

@push('scripts')
<script>
logPage({
  title: 'Sleep',
  icon: 'moon',
  tone: 'violet',
  url: '/sleep-logs',
  formTitle: 'Add a sleep entry',
  formNote: 'Add bedtime and wake-up time, or just enter the total hours if you prefer.',
  fields: [
    ['bedtime', 'Bedtime', 'time'],
    ['wake_time', 'Wake up', 'time'],
    ['hours', 'Hours slept (only if you did not fill bedtime and wake up)', 'number'],
    ['log_date', 'Date', 'today']
  ],
  value: r => r.hours ? Number(r.hours).toFixed(Number(r.hours) % 1 === 0 ? 0 : 1) + ' hrs' : 'Sleep entry',
  meta: r => {
    const window = r.bedtime && r.wake_time ? lt(r.bedtime) + ' – ' + lt(r.wake_time) + ' · ' : '';
    return window + dateLabel(r.log_date);
  }
});
</script>
@endpush
