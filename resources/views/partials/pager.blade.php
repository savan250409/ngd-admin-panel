{{-- @param \Illuminate\Pagination\LengthAwarePaginator $p --}}
<div class="pager">
    <div>
        @if ($p->total())
            Showing <b>{{ $p->firstItem() }}–{{ $p->lastItem() }}</b> of <b>{{ $p->total() }}</b>
        @else
            No records
        @endif
    </div>
    {{ $p->links('partials.pagination') }}
</div>
