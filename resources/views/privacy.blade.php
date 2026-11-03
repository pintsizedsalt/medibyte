@extends('layouts.app')

@section('title', 'Privacy notice · MediByte')
@section('body-class', 'is-public')

@section('content')
  <div class="pv-page">
    <div class="lg-blob lg-blob-a" aria-hidden="true"></div>
    <div class="lg-blob lg-blob-b" aria-hidden="true"></div>

    <div class="pv-wrap">
      <header class="pv-top">
        @auth
          <a class="pv-brand" href="/dashboard">MediByte</a>
          <a class="pv-back" href="/dashboard">
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"/></svg>
            Back to dashboard
          </a>
        @else
          <a class="pv-brand" href="/login">MediByte</a>
          <a class="pv-back" href="/login">
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"/></svg>
            Back to login
          </a>
        @endauth
      </header>

      <section class="pv-hero">
        <p class="eyebrow">Privacy</p>
        <h1>Privacy notice</h1>
        <p class="pv-intro">
          MediByte keeps a private record of the health information you choose to enter. This notice explains what is stored and how you can control it.
        </p>
      </section>

      <div class="pv-glance">
        <div class="pv-chip">
          <span class="pv-ic"><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg></span>
          Private to your account
        </div>
        <div class="pv-chip">
          <span class="pv-ic"><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg></span>
          Never sold or used for ads
        </div>
        <div class="pv-chip">
          <span class="pv-ic"><svg class="icon" aria-hidden="true"><use href="#i-settings"/></svg></span>
          Download or delete anytime
        </div>
      </div>

      <div class="pv-layout">
        <nav class="pv-toc" aria-label="On this page">
          <a href="#collect">What we collect</a>
          <a href="#why">Why we collect it</a>
          <a href="#consent">Consent</a>
          <a href="#storage">Storage and access</a>
          <a href="#choices">Your choices</a>
          <a href="#contact">Contact</a>
        </nav>

        <div class="pv-content">
          <section class="pv-sec" id="collect">
            <div class="pv-sec-head">
              <span class="pv-ic"><svg class="icon" aria-hidden="true"><use href="#i-file"/></svg></span>
              <h2>What we collect</h2>
            </div>
            <p>Only the information you choose to give MediByte:</p>
            <ul class="pv-list">
              <li><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg><span><strong>Account:</strong> name, username, email, and a hashed password</span></li>
              <li><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg><span><strong>Profile:</strong> sex, cycle status, goals, and reminder preferences</span></li>
              <li><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg><span><strong>Health logs:</strong> water, sleep, activity, meals, weight, mood, cycle, symptoms, and vital signs</span></li>
              <li><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg><span><strong>Care:</strong> medications and appointments</span></li>
              <li><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg><span><strong>Documents:</strong> the files you upload and their names</span></li>
              <li><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg><span><strong>Sign-in sessions:</strong> IP address and browser details</span></li>
            </ul>
          </section>

          <section class="pv-sec" id="why">
            <div class="pv-sec-head">
              <span class="pv-ic"><svg class="icon" aria-hidden="true"><use href="#i-activity"/></svg></span>
              <h2>Why we collect it</h2>
            </div>
            <p>To show your dashboard, calculate progress and cycle estimates, display reminders, and keep your uploaded records available to you. MediByte does not sell your data or use it for advertising.</p>
          </section>

          <section class="pv-sec" id="consent">
            <div class="pv-sec-head">
              <span class="pv-ic"><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg></span>
              <h2>Consent</h2>
            </div>
            <p>You provide consent when you create an account. You can withdraw it by deleting your account. Some information, such as cycle status, is optional and can be changed in your profile.</p>
          </section>

          <section class="pv-sec" id="storage">
            <div class="pv-sec-head">
              <span class="pv-ic"><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg></span>
              <h2>Storage and access</h2>
            </div>
            <p>Records are stored in the MediByte database and are accessible only through your authenticated account. Uploaded documents are stored on a private disk and downloaded through an authenticated route.</p>
            <p>To help the app load quickly and show an offline notice, your browser may keep the app's static files, such as styles, scripts, and icons. Your records are not saved this way.</p>
          </section>

          <section class="pv-sec" id="choices">
            <div class="pv-sec-head">
              <span class="pv-ic"><svg class="icon" aria-hidden="true"><use href="#i-settings"/></svg></span>
              <h2>Your choices</h2>
            </div>
            <div class="pv-choices">
              <div class="pv-choice">
                <strong>Update your profile</strong>
                <p>Change your profile details and goals anytime from Profile.</p>
              </div>
              <div class="pv-choice">
                <strong>Download your data</strong>
                <p>Get a JSON copy of your records from Settings. Uploaded files are not included, so download them from Documents first.</p>
              </div>
              <div class="pv-choice">
                <strong>Delete your account</strong>
                <p>Permanently delete your account from Settings. This also removes your private document files.</p>
              </div>
            </div>
          </section>

          <section class="pv-sec" id="contact">
            <div class="pv-sec-head">
              <span class="pv-ic"><svg class="icon" aria-hidden="true"><use href="#i-user"/></svg></span>
              <h2>Contact</h2>
            </div>
            <p>For privacy questions, contact the MediByte administrator through the channel provided by your school or organization.</p>
          </section>

          <div class="pv-actions">
            @auth
              <a class="btn btn-primary" href="/dashboard">Back to dashboard</a>
              <a class="btn btn-ghost" href="/settings">Back to settings</a>
            @else
              <a class="btn btn-primary" href="/register">Create an account</a>
              <a class="btn btn-ghost" href="/login">Back to login</a>
            @endauth
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function () {
  if (!('IntersectionObserver' in window)) return;

  const links = Array.from(document.querySelectorAll('.pv-toc a'));
  const byId = {};
  links.forEach(a => { byId[a.getAttribute('href').slice(1)] = a; });

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      links.forEach(a => a.classList.remove('on'));
      const link = byId[entry.target.id];
      if (link) link.classList.add('on');
    });
  }, { rootMargin: '-20% 0px -65% 0px' });

  document.querySelectorAll('.pv-sec').forEach(section => observer.observe(section));
})();
</script>
@endpush