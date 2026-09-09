<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\PublicFinanceQuery;
use App\Services\Api\HomePagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicFinanceController extends Controller
{
    public function years(PublicFinanceQuery $query): JsonResponse
    {
        return response()->json(['years' => $query->years()]);
    }

    public function sources(PublicFinanceQuery $query): JsonResponse
    {
        return response()->json(['sources' => $query->sources()]);
    }

    public function overview(int $year, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->overview($year));
    }

    public function home(int $year, PublicFinanceQuery $query, HomePagePresenter $presenter): JsonResponse
    {
        return response()->json($presenter->present($query->overview($year)));
    }

    public function budgetStateMissions(int $year, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateMissions($year));
    }

    public function budgetStateMission(int $year, string $mission, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateMission($year, $mission));
    }

    public function budgetStateProgramme(int $year, string $programme, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateProgramme($year, $programme));
    }

    public function budgetStateProgrammeActions(int $year, string $programme, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateProgrammeActions($year, $programme));
    }

    public function budgetStateAction(int $year, string $action, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateAction($year, $action));
    }

    public function budgetStateDistribution(int $year, Request $request, PublicFinanceQuery $query): JsonResponse
    {
        $data = $request->validate(['mission' => ['nullable', 'string'], 'programme' => ['nullable', 'string'], 'measurement' => ['nullable', 'in:commitment_authorization,payment_credit'], 'stage' => ['nullable', 'in:initial_budget,executed'], 'unit' => ['nullable', 'in:amount,percent,per_100']]);
        return response()->json($query->budgetStateDistribution($year, $data['mission'] ?? null, $data['programme'] ?? null, $data['measurement'] ?? 'payment_credit', $data['stage'] ?? 'executed', $data['unit'] ?? 'per_100'));
    }

    public function categories(string $classification, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json(['classification' => $classification, 'categories' => $query->categories($classification)]);
    }

    public function children(string $classification, string $category, PublicFinanceQuery $query): JsonResponse
    {
        $items = $query->categories($classification);
        $parent = collect($items)->first(fn ($item) => $item['slug'] === $category || $item['code'] === $category);

        return response()->json(['classification' => $classification, 'category' => $category, 'categories' => $parent ? $query->categories($classification, $parent['id']) : []]);
    }

    public function history(Request $request, PublicFinanceQuery $query): JsonResponse
    {
        $data = $request->validate(['metric' => ['required', 'in:expenditure,revenue,tax,social_contribution,deficit,debt'], 'classification' => ['nullable', 'string'], 'category' => ['nullable', 'string'], 'scope' => ['nullable', 'string'], 'from' => ['required', 'integer', 'min:1949', 'max:2200'], 'to' => ['required', 'integer', 'min:1949', 'max:2200', 'gte:from']]);

        return response()->json(['metric' => $data['metric'], 'from' => $data['from'], 'to' => $data['to'], 'items' => $query->history($data['metric'], $data['classification'] ?? null, $data['category'] ?? null, $data['scope'] ?? null, $data['from'], $data['to'])]);
    }
}
