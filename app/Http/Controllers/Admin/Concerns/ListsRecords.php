<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

trait ListsRecords
{
    protected array $perPageOptions = [10, 25, 50, 100];

    protected function perPage(Request $request): int
    {
        $n = (int) $request->query('per_page', 10);

        return in_array($n, $this->perPageOptions, true) ? $n : 10;
    }

    protected function searchTerm(Request $request): string
    {
        return trim((string) $request->query('search', ''));
    }
}
