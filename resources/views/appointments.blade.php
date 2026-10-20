@extends('layouts.app')

@section('content')
  <h2>Appointment</h2>
  <p id="msg"></p>
  <div id="log"></div>
@endsection

@push('scripts')
<script>
logPage({
  url: '/appointments',
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
  show: r => `${r.doctor_name}, ${new Date(r.appointment_at).toLocaleString()} [${r.status}]`
});
</script>
@endpush