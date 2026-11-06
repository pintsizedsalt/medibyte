{{-- A lead time the person types themselves, kept beside the notices on offer. --}}
<div class="lead-group custom-leads"
     data-custom-leads="{{ $feature }}"
     data-units="{{ implode(',', array_keys($units)) }}"
     data-suffix="{{ $suffix }}"
     data-ceiling="{{ $ceiling }}">
  <h4>Custom</h4>
  <div class="chip-grid custom-chips"></div>
  <button type="button" class="btn btn-ghost btn-small custom-add" data-custom-add>
    <svg class="icon"><use href="#i-plus"/></svg>
    Add a custom reminder
  </button>

  <div class="custom-form" hidden>
    <label class="field custom-amount">
      <span>How far ahead</span>
      <input type="number" min="1" max="999" step="1" value="15" inputmode="numeric" data-custom-amount>
    </label>
    <label class="field custom-unit">
      <span>Unit</span>
      <select data-custom-unit>
        @foreach ($units as $unit => $factor)
          <option value="{{ $unit }}">{{ $unit }}</option>
        @endforeach
      </select>
    </label>
    <p class="custom-suffix">{{ $suffix }}</p>
    <p class="form-message custom-error" role="alert"></p>
    <div class="form-actions custom-actions">
      <button type="button" class="btn btn-ghost btn-small" data-custom-cancel>Cancel</button>
      <button type="button" class="btn btn-primary btn-small" data-custom-confirm>Add reminder</button>
    </div>
  </div>
</div>
