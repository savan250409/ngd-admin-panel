@extends('layouts.module-index', ['searchPlaceholder' => 'Search category, ratio or #id…'])

@section('thead')
    <th>#</th><th>Thumbnail</th><th>Category</th><th>Ratio</th><th>Layers</th><th>Paid</th><th>Status</th><th class="text-end">Actions</th>
@endsection

@section('tbody')
    @forelse ($items as $item)
        @php $layers = count(json_decode($item->json ?? '[]', true)['layers'] ?? []); @endphp
        <tr>
            <td class="id-badge">{{ $item->id }}</td>
            <td>@include('partials.thumb', ['url' => $item->fileUrl('thumbnail'), 'class' => 'thumb-lg'])</td>
            <td>@include('partials.category-cell')</td>
            <td><span class="chip"><i class="bi bi-aspect-ratio"></i>{{ $item->aspect_ratio }}</span></td>
            <td>
                <span class="chip {{ $layers ? 'chip-ok' : 'chip-warn' }}">{{ $layers }} layers</span>
                <div><a class="small" href="{{ $item->fileUrl('zip') }}">Download zip</a></div>
            </td>
            <td>@include('partials.switch', ['url' => route($module . '.items.toggle', $item->id), 'value' => $item->is_paid, 'field' => 'is_paid', 'on' => 'Paid', 'off' => 'Free'])</td>
            <td>@include('partials.switch', ['url' => route($module . '.items.toggle', $item->id), 'value' => $item->status])</td>
            <td>@include('partials.actions', ['edit' => route($module . '.items.edit', $item->id), 'delete' => route($module . '.items.destroy', $item->id)])</td>
        </tr>
    @empty
        <tr><td colspan="8"><div class="empty-state"><i class="bi {{ $cfg['icon'] }}"></i>No dynamic frames found.</div></td></tr>
    @endforelse
@endsection
