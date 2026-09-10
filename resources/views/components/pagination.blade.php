@if ($paginator->hasPages())
    <nav aria-label="صفحه‌بندی" dir="rtl" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
        @if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <p class="small text-muted mb-0">
                نمایش {{ $paginator->firstItem() }} تا {{ $paginator->lastItem() }} از {{ $paginator->total() }} مورد
            </p>
        @endif
        <ul class="pagination pagination-sm flex-wrap mb-0">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">قبلی</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a></li>
            @endif
            @foreach ($elements ?? [] as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}" aria-label="صفحه {{ $page }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a></li>
            @else
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">بعدی</span></li>
            @endif
        </ul>
    </nav>
@endif
