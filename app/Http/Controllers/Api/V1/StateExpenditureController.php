<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StateExpenditureIndexRequest;
use App\Services\Api\StateExpenditureQuery;
use Illuminate\Http\JsonResponse;

class StateExpenditureController extends Controller
{
    public function __invoke(StateExpenditureIndexRequest $request, StateExpenditureQuery $query): JsonResponse
    {
        $filters = $request->validated();

        return response()->json($query->get(
            (int) $filters['year'],
            $filters['classification'],
            $filters['measure'],
        ));
    }
}
