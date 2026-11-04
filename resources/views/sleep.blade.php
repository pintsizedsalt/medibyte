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
  formNote: 'Choose how you would like to log your sleep, then add the details.',
  fields: [
    ['sleep_method', 'How would you like to log your sleep?', 'method', [['times', 'Bedtime and wake-up time'], ['hours', 'Total hours slept']], 'times'],
    ['bedtime', 'Bedtime', 'time', null, null, null, ['sleep_method', 'times']],
    ['wake_time', 'Wake up', 'time', null, null, null, ['sleep_method', 'times']],
    ['hours', 'Hours slept', 'number', null, null, null, ['sleep_method', 'hours']],
    ['log_date', 'Date', 'today']
  ],
  methodInit: row => {
    const el = $('f_sleep_method');
    if (el) el.value = row.bedtime ? 'times' : 'hours';
  },
  value: r => r.hours ? Number(r.hours).toFixed(Number(r.hours) % 1 === 0 ? 0 : 1) + ' hrs' : 'Sleep entry',
  meta: r => {
    const window = r.bedtime && r.wake_time ? lt(r.bedtime) + ' – ' + lt(r.wake_time) + ' · ' : '';
    return window + dateLabel(r.log_date);
  }
});
</script>
@endpush
