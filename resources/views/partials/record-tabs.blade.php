@php
  $current = request()->path();
  $recordTabs = [
    ['label' => 'Water', 'href' => '/water', 'icon' => 'droplet'],
    ['label' => 'Sleep', 'href' => '/sleep', 'icon' => 'moon'],
    ['label' => 'Activity', 'href' => '/walking', 'icon' => 'activity'],
    ['label' => 'Meals', 'href' => '/meals', 'icon' => 'meal'],
    ['label' => 'Weight', 'href' => '/weight', 'icon' => 'scale'],
    ['label' => 'Mood', 'href' => '/mood', 'icon' => 'smile'],
    ['label' => 'Cycle', 'href' => '/period', 'icon' => 'cycle', 'cycle' => true],
  ];
@endphp

<nav class="record-tabs" aria-label="Record categories">
  @foreach ($recordTabs as $tab)
    <a
      href="{{ $tab['href'] }}"
      class="{{ $current === ltrim($tab['href'], '/') ? 'active' : '' }}"
      @if (!empty($tab['cycle'])) data-cycle hidden @endif
    >{{ $tab['label'] }}</a>
  @endforeach
  <a href="/records" class="record-tabs-more">All records</a>
</nav>
