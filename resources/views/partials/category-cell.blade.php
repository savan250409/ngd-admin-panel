{{-- @param \App\Models\BaseItem $item --}}
<div class="fw-semibold">{{ $item->category?->name ?? '—' }}</div>
@php $apps = $item->category?->apps; @endphp
@if ($apps && $apps->isNotEmpty())
    <small class="text-muted">{{ $apps->pluck('app_name')->implode(', ') }}</small>
@endif
