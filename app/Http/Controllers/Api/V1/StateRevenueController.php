<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StateRevenueIndexRequest;
use App\Services\Api\StateRevenueQuery;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class StateRevenueController extends Controller
{
    /**
     * Recettes du budget général de l’État
     *
     * Retourne les recettes selon leur année et leur statut comptable. Une estimation révisée ne constitue pas une exécution.
     */
    #[Response(type: "array{period: int, scope: array{code: string, label: string, budget_component: string}, status: string, flow_type: 'revenue', classification: 'revenue', currency: 'EUR', aggregation_warning: string, items: list<array{slug: string, label: string, amount: string, is_aggregate: bool, is_deduction: bool, source_row_number: int|null}>, source: array<string, mixed>}")]
    public function __invoke(StateRevenueIndexRequest $request, StateRevenueQuery $query): JsonResponse
    {
        $filters = $request->validated();

        return response()->json($query->get((int) $filters['year'], $filters['status']));
    }
}
