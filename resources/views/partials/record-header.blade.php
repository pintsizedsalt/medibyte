@php
  $eyebrow = $eyebrow ?? 'Health record';
  $back = $back ?? '/dashboard';
  $backLabel = $backLabel ?? 'Back to dashboard';
  $add = $add ?? true;
@endphp

<div class="page-heading">
  <div class="heading-main">
    <a class="back-link" href="{{ $back }}" aria-label="{{ $backLabel }}">
      <svg class="icon"><use href="#i-chevron-left"/></svg>
    </a>
    <div>
      <p class="eyebrow">{{ $eyebrow }}</p>
      <h2>{{ $title }}</h2>
      @isset($description)
        <p>{{ $description }}</p>
      @endisset
    </div>
  </div>

  @if ($add)
    <button type="button" class="btn btn-primary" onclick="window.addLogRecord && window.addLogRecord()">
      <svg class="icon"><use href="#i-plus"/></svg>
      Add record
    </button>
  @endif
</div>

@include('partials.record-tabs')

<p id="msg" class="form-message" role="alert"></p>
<div id="log"></div>
