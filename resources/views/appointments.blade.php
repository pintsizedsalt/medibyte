@extends('layouts.app')

@section('title', 'Appointments · MediByte')

@section('content')
  @include('partials.record-header', [
    'title' => 'Appointments',
    'description' => 'Keep doctor visits, contact details, and the reason for each appointment together.',
    'eyebrow' => 'Care',
  ])
@endsection

@push('scripts')
<script>
logPage({
  title: 'Appointments',
  icon: 'calendar',
  tone: 'blue',
  url: '/appointments',
  formTitle: 'Add an appointment',
  formNote: 'Add the doctor and date first. Contact details and notes can be filled in as needed.',
  fields: [
    ['doctor_name', 'Doctor', 'text'],
    ['specialty', 'Specialty', 'text'],
    ['contact_number', 'Contact number', 'text'],
    ['office_address', 'Office address', 'text'],
    ['hospital', 'Hospital', 'text'],
    ['appointment_at', 'Date and time', 'datetime-local'],
    ['reason', 'Reason', 'text'],
    ['status', 'Status', 'select', ['scheduled', 'done', 'cancelled']]
  ],
  value: r => r.doctor_name + (r.specialty ? ' · ' + r.specialty : ''),
  meta: r => [dateTimeLabel(r.appointment_at), (r.status || 'scheduled').replace(/^./, c => c.toUpperCase())].filter(Boolean).join(' · ')
});
</script>
@endpush
