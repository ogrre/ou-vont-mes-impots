<?php

namespace App\Services\Api;

use App\Models\FinancialObservation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StateRevenueQuery
{
    public function __construct(private readonly DatasetProvenancePresenter $provenance) {}

    /** @return array<string, mixed> */
    public function get(int $year, string $status): array
    {
        $observations = FinancialObservation::query()
            ->with([
                'accountingScope',
                'budgetComponent',
                'classificationItem.classification',
                'dataset.source',
                'datasetFile',
                'importBatch',
            ])
            ->where('year', $year)
            ->where('status', $status)
            ->where('flow_type', 'revenue')
            ->whereHas('accountingScope', fn ($query) => $query->where('code', 'french_state_budget'))
            ->whereHas('classificationItem.classification', fn ($query) => $query->where('code', 'state_budget_revenue'))
            ->whereHas('importBatch', fn ($query) => $query->where('status', 'completed'))
            ->orderBy('source_row_number')
            ->get();

        if ($observations->isEmpty()) {
            throw new NotFoundHttpException('Aucune recette importée ne correspond à ces filtres.');
        }

        $first = $observations->first();

        return [
            'period' => $year,
            'scope' => [
                'code' => $first->accountingScope->code,
                'label' => $first->accountingScope->name,
                'budget_component' => $first->budgetComponent->code,
            ],
            'status' => $status,
            'flow_type' => 'revenue',
            'classification' => 'revenue',
            'currency' => 'EUR',
            'aggregation_warning' => 'Les lignes comprennent des détails, sous-totaux, prélèvements et totaux : elles ne doivent pas être additionnées entre elles.',
            'items' => $observations->map(fn (FinancialObservation $observation) => [
                'slug' => $observation->classificationItem->slug,
                'label' => $observation->classificationItem->official_label,
                'amount' => $observation->amount,
                'is_aggregate' => $observation->classificationItem->metadata['aggregation_role'] === 'aggregate',
                'is_deduction' => $observation->metadata['is_deduction'],
                'source_row_number' => $observation->source_row_number,
            ])->all(),
            'source' => $this->provenance->present($first->dataset, $first->datasetFile, $first->importBatch),
        ];
    }
}
