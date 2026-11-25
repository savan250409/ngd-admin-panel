{{--
    Category picker. Each option carries data-apps (the app ids it belongs to) so the
    app filter on list pages can narrow it.
    @param \Illuminate\Support\Collection $categories  @param int|null $selected  @param array $disabledIds
--}}
@php $disabledIds = $disabledIds ?? []; @endphp
<div class="mb-3">
    <label class="form-label" for="category_id">Category <span class="req">*</span></label>
    <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
        <option value="">— Select category —</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" data-apps="{{ $c->apps->pluck('id')->implode(',') }}"
                @selected(old('category_id', $selected) == $c->id)
                @disabled(in_array($c->id, $disabledIds) && old('category_id', $selected) != $c->id)>
                {{ $c->name }}@if ($c->apps->isNotEmpty()) — {{ $c->apps->pluck('app_name')->implode(', ') }}@endif{{ in_array($c->id, $disabledIds) ? ' (already added)' : '' }}{{ $c->status ? '' : ' (inactive)' }}
            </option>
        @endforeach
    </select>
    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($categories->isEmpty())
        <div class="hint text-warning"><i class="bi bi-exclamation-triangle"></i> No categories yet — create one first.</div>
    @endif
</div>
