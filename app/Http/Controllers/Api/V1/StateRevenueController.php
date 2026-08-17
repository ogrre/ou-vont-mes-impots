<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StateRevenueIndexRequest;
use App\Services\Api\StateRevenueQuery;
use Illuminate\Http\JsonResponse;

class StateRevenueController extends Controller
{
    public function __invoke(StateRevenueIndexRequest $request, StateRevenueQuery $query): JsonResponse
    {
        $filters = $request->validated();

        return response()->json($query->get((int) $filters['year'], $filters['status']));
    }
}
