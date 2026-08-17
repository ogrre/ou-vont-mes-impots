<?php

namespace App\Services\Validation;

use App\Models\FinancialObservation;
use Illuminate\Support\Collection;

class StateExpenditureReconciliation
{
    /** @return array{valid: bool, comparisons: array<int, array<string, mixed>>, discrepancies: array<int, array<string, mixed>>} */
    public function validate(int $year = 2025): array
    {
        $totals = FinancialObservation::query()
            ->join('classification_items', 'classification_items.id', '=', 'financial_observations.classification_item_id')
            ->join('classifications', 'classifications.id', '=', 'classification_items.classification_id')
            ->join('budget_components', 'budget_components.id', '=', 'financial_observations.budget_component_id')
            ->where('financial_observations.year', $year)
            ->where('financial_observations.status', 'executed')
            ->where('financial_observations.flow_type', 'expenditure')
            ->selectRaw('financial_observations.measure, classifications.code as classification, budget_components.code as component, SUM(financial_observations.amount) as total')
            ->groupBy('financial_observations.measure', 'classifications.code', 'budget_components.code')
            ->get();

        $comparisons = [];
        $discrepancies = [];

        foreach ($totals->groupBy(fn ($row) => $row->measure->value.'|'.$row->component) as $key => $group) {
            [$measure, $component] = explode('|', $key, 2);
            $values = $this->classificationValues($group);
            $comparison = compact('measure', 'component', 'values');
            $comparisons[] = $comparison;

            if (count($values) !== 3 || count(array_unique(array_values($values))) !== 1) {
                $discrepancies[] = $comparison;
            }
        }

        return [
            'valid' => $comparisons !== [] && $discrepancies === [],
            'comparisons' => $comparisons,
            'discrepancies' => $discrepancies,
        ];
    }

    /** @return array<string, string> */
    private function classificationValues(Collection $group): array
    {
        return $group->mapWithKeys(function ($row): array {
            $classification = str_replace('state_budget_', '', $row->classification);

            return [$classification => $this->canonicalDecimal((string) $row->total)];
        })->sortKeys()->all();
    }

    private function canonicalDecimal(string $value): string
    {
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return $value;
        }

        return (ltrim($matches[1], '0') ?: '0').'.'.str_pad(substr($matches[2] ?? '', 0, 2), 2, '0');
    }
}
