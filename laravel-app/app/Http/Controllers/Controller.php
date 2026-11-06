<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Page size from `?per_page=`, clamped to the configured bounds.
     */
    protected function perPage(Request $request): int
    {
        $default = config('tenancy.pagination.default');

        return max(1, min($request->integer('per_page', $default), config('tenancy.pagination.max')));
    }
}
