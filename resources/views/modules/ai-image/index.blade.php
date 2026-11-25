@extends('layouts.module-index', ['searchPlaceholder' => 'Search prompt, category or #id…'])

@section('thead')
    <th>#</th><th>Image</th><th>Category</th><th>Prompt</th><th>Status</th><th class="text-end">Actions</th>
@endsection

@section('tbody')
    @forelse ($items as $item)
        <tr>
            <td class="id-badge">{{ $item->id }}</td>
            <td>@include('partials.thumb', ['url' => $item->fileUrl('image'), 'class' => 'thumb-lg'])</td>
            <td>@include('partials.category-cell')</td>
            <td>
                <div class="text-clamp" title="{{ $item->prompt }}">{{ $item->prompt }}</div>
                <button class="btn btn-sm btn-link p-0 text-muted" data-copy="{{ $item->prompt }}"><i class="bi bi-copy me-1"></i>Copy prompt</button>
            </td>
            <td>@include('partials.switch', ['url' => route($module . '.items.toggle', $item->id), 'value' => $item->status])</td>
            <td>@include('partials.actions', ['edit' => route($module . '.items.edit', $item->id), 'delete' => route($module . '.items.destroy', $item->id)])</td>
        </tr>
    @empty
        <tr><td colspan="6"><div class="empty-state"><i class="bi {{ $cfg['icon'] }}"></i>No AI images found.</div></td></tr>
    @endforelse
@endsection
