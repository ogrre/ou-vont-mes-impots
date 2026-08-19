<?php

namespace App\Services\Api;

use App\Enums\FinancialMeasure;
use App\Models\FinancialObservation;
use App\Support\DecimalMoney;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StateExpenditureQuery
{
    private const MEASURES = [
        'ae' => FinancialMeasure::CommitmentAuthorization,
        'commitment_authorization' => FinancialMeasure::CommitmentAuthorization,
        'cp' => FinancialMeasure::PaymentCredit,
        'payment_credit' => FinancialMeasure::PaymentCredit,
    ];

    public function __construct(private readonly DatasetProvenancePresenter $provenance) {}

    /**
     * @return array{
     *     period: int,
     *     scope: array{code: string, label: string},
     *     status: 'executed',
     *     flow_type: 'expenditure',
     *     measure: array{code: string, official_label: string},
     *     classification: string,
     *     currency: 'EUR',
     *     total: string,
     *     percentage_denominator: array{amount: string, description: string},
     *     items: list<array{
     *         code: string|null,
     *         slug: string,
     *         label: string,
     *         amount: string,
     *         percentage: string|null,
     *         components: list<array{code: string, label: string, amount: string}>
     *     }>,
     *     source: array<string, mixed>
     * }
     */
    public function get(int $year, string $classification, string $measureInput): array
    {
        $measure = self::MEASURES[$measureInput];
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
            ->where('status', 'executed')
            ->where('flow_type', 'expenditure')
            ->where('measure', $measure->value)
            ->whereHas('accountingScope', fn ($query) => $query->where('code', 'french_state_budget'))
            ->whereHas('classificationItem.classification', fn ($query) => $query->where('code', 'state_budget_'.$classification))
            ->whereHas('importBatch', fn ($query) => $query->where('status', 'completed'))
            ->get();

        if ($observations->isEmpty()) {
            throw new NotFoundHttpException('Aucune dépense importée ne correspond à ces filtres.');
        }

        $total = DecimalMoney::sum($observations->pluck('amount'));
        $items = $observations
            ->groupBy('classification_item_id')
            ->map(fn (Collection $group) => $this->item($group, $total))
            ->sort(fn (array $left, array $right) => DecimalMoney::compare($right['amount'], $left['amount']))
            ->values()
            ->all();
        $first = $observations->first();

        return [
            'period' => $year,
            'scope' => [
                'code' => $first->accountingScope->code,
                'label' => $first->accountingScope->name,
            ],
            'status' => 'executed',
            'flow_type' => 'expenditure',
            'measure' => [
                'code' => $measure->value,
                'official_label' => $measure->officialLabel(),
            ],
            'classification' => $classification,
            'currency' => 'EUR',
            'total' => $total,
            'percentage_denominator' => [
                'amount' => $total,
                'description' => 'Total du même exercice, périmètre, statut, mesure, classification et ensemble de composantes budgétaires.',
            ],
            'items' => $items,
            'source' => $this->provenance->present($first->dataset, $first->datasetFile, $first->importBatch),
        ];
    }

    /**
     * @param  Collection<int, FinancialObservation>  $observations
     * @return array{
     *     code: string|null,
     *     slug: string,
     *     label: string,
     *     amount: string,
     *     percentage: string|null,
     *     components: list<array{code: string, label: string, amount: string}>
     * }
     */
    private function item(Collection $observations, string $total): array
    {
        $first = $observations->first();
        $amount = DecimalMoney::sum($observations->pluck('amount'));

        return [
            'code' => $first->classificationItem->code,
            'slug' => $first->classificationItem->slug,
            'label' => $first->classificationItem->official_label,
            'amount' => $amount,
            'percentage' => $total === '0.00' ? null : bcmul(bcdiv($amount, $total, 8), '100', 2),
            'components' => $observations
                ->sortBy('budgetComponent.code')
                ->map(fn (FinancialObservation $observation) => [
                    'code' => $observation->budgetComponent->code,
                    'label' => $observation->budgetComponent->official_label,
                    'amount' => $observation->amount,
                ])
                ->values()
                ->all(),
        ];
    }
}
