@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = collect([1, 2, $current - 1, $current, $current + 1, $last - 1, $last])
            ->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
    @endphp
    <nav aria-label="Pagination">
        <ul class="pagination pagination-sm">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $paginator->previousPageUrl() ?? '#' }}" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>
            </li>
            @foreach ($pages as $i => $p)
                @if ($i > 0 && $p - $pages[$i - 1] > 1)
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                @endif
                <li class="page-item {{ $p === $current ? 'active' : '' }}">
                    <a class="page-link" href="{{ $paginator->url($p) }}">{{ $p }}</a>
                </li>
            @endforeach
            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                <a class="page-link" href="{{ $paginator->nextPageUrl() ?? '#' }}" aria-label="Next"><i class="bi bi-chevron-right"></i></a>
            </li>
        </ul>
    </nav>
@endif
