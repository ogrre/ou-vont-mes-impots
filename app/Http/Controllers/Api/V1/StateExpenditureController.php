<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StateExpenditureIndexRequest;
use App\Services\Api\StateExpenditureQuery;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class StateExpenditureController extends Controller
{
    /**
     * Dépenses exécutées de l’État
     *
     * Retourne les dépenses exécutées du budget de l’État français selon une classification officielle. AE et CP sont des mesures distinctes et ne sont jamais additionnées.
     */
    #[Response(type: "array{period: int, scope: array{code: string, label: string}, status: 'executed', flow_type: 'expenditure', measure: array{code: string, official_label: string}, classification: string, currency: 'EUR', total: string, percentage_denominator: array{amount: string, description: string}, items: list<array{code: string|null, slug: string, label: string, amount: string, percentage: string|null, components: list<array{code: string, label: string, amount: string}>}>, source: array<string, mixed>}")]
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
