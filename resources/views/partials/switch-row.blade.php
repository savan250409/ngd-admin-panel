{{-- Form toggle. @param string $name  @param string $title  @param string $desc  @param bool $checked --}}
<div class="toggle-row mb-3">
    <div>
        <div class="t">{{ $title }}</div>
        <div class="d">{{ $desc }}</div>
    </div>
    <div class="form-check form-switch m-0">
        <input type="hidden" name="{{ $name }}" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" value="1" @checked($checked) aria-label="{{ $title }}">
    </div>
</div>
