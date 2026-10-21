@extends('layouts.app')

@section('title', 'Privacy notice · MediByte')
@section('body-class', 'is-public')

@section('content')
  <div class="legal-shell">
    <div class="card">
      <p class="eyebrow">Privacy</p>
      <h2>Privacy notice</h2>
      <p>MediByte keeps a private record of the health information you choose to enter. This notice explains what is stored and how you can control it.</p>

      <h3>What we collect</h3>
      <p>Account details (name, email, and password hash), profile information (sex, cycle status, goals, and reminder preferences), health logs, medications, appointments, and document metadata and files you upload.</p>

      <h3>Why we collect it</h3>
      <p>To show your dashboard, calculate progress and cycle estimates, display reminders, and keep your uploaded records available to you. MediByte does not sell your data or use it for advertising.</p>

      <h3>Consent</h3>
      <p>You provide consent when you create an account. You can withdraw it by deleting your account. Some information, such as cycle status, is optional and can be changed in your profile.</p>

      <h3>Storage and access</h3>
      <p>Records are stored in the MediByte database and are accessible only through your authenticated account. Uploaded documents are stored on a private disk and downloaded through an authenticated route.</p>

      <h3>Your choices</h3>
      <p>You can update your profile and goals from Profile, download a JSON copy of your data, or permanently delete your account from Settings. Deleting your account also removes your private document files.</p>

      <h3>Contact</h3>
      <p>For privacy questions, contact the MediByte administrator through the channel provided by your school or organization.</p>

      <div class="form-actions">
        <a class="btn btn-primary" href="/register">Create an account</a>
        <a class="btn btn-ghost" href="/login">Back to login</a>
      </div>
    </div>
  </div>
@endsection
