@php
  $eyebrow = $eyebrow ?? 'Health record';
  $back = $back ?? '/dashboard';
  $backLabel = $backLabel ?? 'Back to dashboard';

  $sections = [
    'water'        => ['blue', 'droplet'],
    'sleep'        => ['violet', 'moon'],
    'walking'      => ['amber', 'activity'],
    'meals'        => ['mint', 'meal'],
    'weight'       => ['slate', 'scale'],
    'mood'         => ['gold', 'smile'],
    'symptoms'     => ['amber', 'thermometer'],
    'vitals'       => ['rose', 'heart'],
    'period'       => ['rose', 'cycle'],
    'medications'  => ['plum', 'pill'],
    'appointments' => ['blue', 'calendar'],
  ];
  [$tone, $icon] = $sections[request()->path()] ?? [null, null];
@endphp

<div class="page-heading{{ $tone ? ' tone-'.$tone : '' }}">
  <div class="heading-main">
    <a class="back-link" href="{{ $back }}" aria-label="{{ $backLabel }}">
      <svg class="icon"><use href="#i-chevron-left"/></svg>
    </a>
    @if ($icon)
      <span class="heading-ic" aria-hidden="true"><svg class="icon"><use href="#i-{{ $icon }}"/></svg></span>
    @endif
    <div>
      <p class="eyebrow">{{ $eyebrow }}</p>
      <h2>{{ $title }}</h2>
      @isset($description)
        <p>{{ $description }}</p>
      @endisset
    </div>
  </div>
</div>

@include('partials.record-tabs')

<p id="msg" class="form-message" role="alert"></p>
<div id="log"></div>