@extends('layouts.module-index', ['searchPlaceholder' => 'Search category, count or #id…'])

@section('thead')
    <th>#</th><th>Frame</th><th>Thumbnail</th><th>Category</th><th>Count</th><th>Status</th><th class="text-end">Actions</th>
@endsection

@section('tbody')
    @forelse ($items as $item)
        <tr>
            <td class="id-badge">{{ $item->id }}</td>
            <td>@include('partials.thumb', ['url' => $item->fileUrl('frame'), 'class' => 'thumb-lg'])</td>
            <td>@include('partials.thumb', ['url' => $item->fileUrl('thumbnail')])</td>
            <td>@include('partials.category-cell')</td>
            <td><span class="chip chip-brand"><i class="bi bi-images"></i>{{ $item->count }} {{ \Illuminate\Support\Str::plural('photo', $item->count) }}</span></td>
            <td>@include('partials.switch', ['url' => route($module . '.items.toggle', $item->id), 'value' => $item->status])</td>
            <td>@include('partials.actions', ['edit' => route($module . '.items.edit', $item->id), 'delete' => route($module . '.items.destroy', $item->id)])</td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty-state"><i class="bi {{ $cfg['icon'] }}"></i>No templates found.</div></td></tr>
    @endforelse
@endsection
