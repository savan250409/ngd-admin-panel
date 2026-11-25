@if ($errors->any())
    <div class="alert alert-danger border-0 rounded-3 d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-octagon-fill fs-5"></i>
        <div>
            <b>Please fix the following:</b>
            <ul class="mb-0 ps-3 mt-1">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
