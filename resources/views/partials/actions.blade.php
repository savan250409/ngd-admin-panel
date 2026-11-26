{{-- Row actions. @param string $edit  @param string $delete  @param string|null $confirm  @param string|null $confirm2 (second alert) --}}
<div class="d-flex justify-content-end gap-1">
    @isset($extra){!! $extra !!}@endisset
    <a href="{{ $edit }}" class="btn btn-soft btn-icon" title="Edit"><i class="bi bi-pencil"></i></a>
    <form method="POST" action="{{ $delete }}" data-confirm="{{ $confirm ?? 'This record and its files will be permanently deleted.' }}" @isset($confirm2) data-confirm2="{{ $confirm2 }}" @endisset>
        @csrf @method('DELETE')
        <button class="btn btn-light border btn-icon text-danger" title="Delete"><i class="bi bi-trash3"></i></button>
    </form>
</div>
