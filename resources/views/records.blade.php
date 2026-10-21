@extends('layouts.app')

@section('title', 'All records · MediByte')

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Your history</p>
      <h2>All records</h2>
      <p>Open any part of your health record to view, add, edit, or delete entries.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openQuickSheet()">
      <svg class="icon"><use href="#i-plus"/></svg>
      Quick add
    </button>
  </div>

  @php
    $groups = [
      'Everyday' => [
        ['Water', 'Hydration and daily intake', '/water', 'droplet', 'blue'],
        ['Sleep', 'Bedtime, wake-up, and hours', '/sleep', 'moon', 'violet'],
        ['Activity', 'Steps, walks, and workouts', '/walking', 'activity', 'mint'],
        ['Meals', 'Food, snacks, and calories', '/meals', 'meal', 'amber'],
      ],
      'Wellbeing' => [
        ['Weight & BMI', 'Weight history and body mass index', '/weight', 'scale', 'slate'],
        ['Mood', 'Feelings, intensity, and reflections', '/mood', 'smile', 'rose'],
        ['Symptoms', 'Symptoms, severity, and notes', '/symptoms', 'thermometer', 'amber'],
        ['Vitals', 'Blood pressure, heart rate, and more', '/vitals', 'heart', 'rose'],
        ['Cycle', 'Periods and estimated next start', '/period', 'cycle', 'violet', true],
      ],
      'Care' => [
        ['Medications', 'Dosage, times, and instructions', '/medications', 'pill', 'plum'],
        ['Appointments', 'Doctors, visits, and contact details', '/appointments', 'calendar', 'blue'],
        ['Documents', 'Prescriptions, labs, and certificates', '/documents', 'file', 'blue'],
      ],
    ];
  @endphp

  @foreach ($groups as $label => $records)
    <section class="records-section">
      <div class="card-head">
        <div>
          <p class="eyebrow">{{ $label }}</p>
          <h3>{{ $label === 'Everyday' ? 'Daily habits' : ($label === 'Wellbeing' ? 'How you are doing' : 'Health care') }}</h3>
        </div>
      </div>

      <div class="records-grid">
        @foreach ($records as $record)
          <a
            class="record-hub-card"
            href="{{ $record[2] }}"
            @if (!empty($record[5])) data-cycle hidden @endif
          >
            <span class="record-hub-icon tone-{{ $record[4] }}"><svg class="icon"><use href="#i-{{ $record[3] }}"/></svg></span>
            <span class="record-hub-copy">
              <strong>{{ $record[0] }}</strong>
              <small>{{ $record[1] }}</small>
            </span>
            <svg class="icon"><use href="#i-chevron-right"/></svg>
          </a>
        @endforeach
      </div>
    </section>
  @endforeach
@endsection
