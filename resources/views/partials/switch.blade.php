{{-- Inline toggle. @param string $url  @param bool $value  @param string $field  @param string $on  @param string $off --}}
@php $field = $field ?? 'status'; $on = $on ?? 'Active'; $off = $off ?? 'Inactive'; @endphp
<div class="form-check form-switch d-flex align-items-center gap-2 m-0">
    <input class="form-check-input m-0" type="checkbox" role="switch" @checked($value)
           data-toggle-url="{{ $url }}" data-field="{{ $field }}" data-on="{{ $on }}" data-off="{{ $off }}" aria-label="Toggle {{ $field }}">
    <small class="text-muted" data-toggle-label>{{ $value ? $on : $off }}</small>
</div>
