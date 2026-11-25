{{--
    Drop-zone upload with optional preview.
    @param string $name  @param string $label  @param string $accept  @param bool $required
    @param string|null $hint  @param string|null $current (stored filename, for the label)
    @param string|null $current_url (built media URL, for the preview)  @param bool $image (preview)
    @param bool $multiple
--}}
@php
    $id = 'f_' . str_replace(['[', ']'], '', $name);
    $image = $image ?? true;
    $multiple = $multiple ?? false;
    $current_url = $current_url ?? null;
    // Every image field accepts .webp only.
    $webpOnly = $image && str_contains($accept, 'webp');
    $accept = $webpOnly ? 'image/webp' : $accept;
@endphp
<div class="mb-3">
    <label class="form-label" for="{{ $id }}">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>
    <label class="drop">
        <i class="bi bi-cloud-arrow-up"></i>
        <span><b>Click to upload</b> or drag &amp; drop</span>
        <span class="fname"></span>
        <input type="file" id="{{ $id }}" name="{{ $name }}" accept="{{ $accept }}" @required($required) @if ($multiple) multiple @endif
               @if ($webpOnly) data-webp-only @endif
               @if ($image && !$multiple) data-preview="#{{ $id }}_preview" @endif>
    </label>

    @if ($webpOnly)
        <div class="webp-warning" role="note">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>Only <code>.webp</code> images are allowed. Please convert other formats to WebP before uploading.</span>
        </div>
    @endif

    @if (!empty($hint))<div class="hint">{{ $hint }}</div>@endif
    @error(str_replace('[]', '', $name))<div class="text-danger small mt-1">{{ $message }}</div>@enderror

    @if ($image && !$multiple)
        <div class="preview-box" id="{{ $id }}_preview">
            @if (!empty($current_url))
                <img src="{{ $current_url }}" alt="Current {{ strtolower($label) }}" data-current>
            @endif
        </div>
    @elseif (!empty($current))
        <div class="hint mt-2"><i class="bi bi-paperclip"></i> Current file: <span class="mono">{{ basename($current) }}</span></div>
    @endif
</div>
