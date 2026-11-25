{{-- @param string|null $url  (already-built media URL)  @param string $class --}}
@if (!empty($url))
    <img src="{{ $url }}" class="thumb {{ $class ?? '' }}" alt="" loading="lazy">
@else
    <div class="thumb-empty {{ $class ?? '' }}"><i class="bi bi-image"></i></div>
@endif
