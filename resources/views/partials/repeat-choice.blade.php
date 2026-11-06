{{-- Whether an unopened notice keeps arriving, chosen from the same four rates the
     server accepts, so the segmented control is the list rather than a guess at it. --}}
<div class="lead-group repeat-group">
  <h4>{{ $title }}</h4>
  <p class="repeat-hint">{{ $hint }}</p>
  <fieldset class="seg" id="{{ $feature }}_repeat_minutes">
    <legend class="sr-only">{{ $title }}</legend>
    <label class="seg-item">
      <input type="radio" name="{{ $feature }}_repeat" value="">
      <span>Do not repeat</span>
    </label>
    @foreach (\App\Models\User::REPEAT_INTERVALS as $minutes => $label)
      <label class="seg-item">
        <input type="radio" name="{{ $feature }}_repeat" value="{{ $minutes }}">
        <span>{{ $label }}</span>
      </label>
    @endforeach
  </fieldset>
</div>
