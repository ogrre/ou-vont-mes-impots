<?php

namespace App\Services\Api;

use App\Models\Classification;
use App\Models\ClassificationItem;
use App\Models\Dataset;
use App\Models\FinancialObservation;
use App\Support\DecimalMoney;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PublicFinanceQuery
{
    private const STATE_CLASSIFICATION = 'state_budget_programme_action';

    /** @return array<string,mixed> */
    public function budgetStateMissions(int $year): array
    {
        $classification = Classification::query()->where('code', self::STATE_CLASSIFICATION)->firstOrFail();
        $missions = $classification->items()->whereNull('parent_id')->whereJsonContains('metadata->level', 'mission')->with('children.children.observations.dataset.source', 'children.children.observations.datasetFile')->get();

        return ['year' => $year, 'items' => $missions->map(fn (ClassificationItem $item): array => $this->budgetNode($item, $year, 'mission'))->values()->all(), 'coverage' => $this->budgetCoverage($year)];
    }

    /** @return array<string,mixed> */
    public function budgetStateMission(int $year, string $mission): array
    {
        $item = $this->findBudgetItem($mission, 'mission');
        abort_if($item === null, 404);

        return ['year' => $year, 'item' => $this->budgetNode($item, $year, 'mission'), 'coverage' => $this->budgetCoverage($year)];
    }

    /** @return array<string,mixed> */
    public function budgetStateProgramme(int $year, string $programme): array
    {
        $item = $this->findBudgetItem($programme, 'programme');
        abort_if($item === null, 404);

        return ['year' => $year, 'item' => $this->budgetNode($item, $year, 'programme'), 'coverage' => $this->budgetCoverage($year)];
    }

    /** @return array<string,mixed> */
    public function budgetStateProgrammeActions(int $year, string $programme): array
    {
        $item = $this->findBudgetItem($programme, 'programme');
        abort_if($item === null, 404);
        $actions = $item->children()->with('observations.dataset.source', 'observations.datasetFile')->get();

        return ['year' => $year, 'programme' => $this->budgetNode($item, $year, 'programme', false), 'items' => $actions->map(fn (ClassificationItem $action): array => $this->budgetNode($action, $year, $this->level($action)))->values()->all(), 'coverage' => $this->budgetCoverage($year)];
    }

    /** @return array<string,mixed> */
    public function budgetStateAction(int $year, string $action): array
    {
        $item = ClassificationItem::query()->whereHas('classification', fn ($q) => $q->where('code', self::STATE_CLASSIFICATION))->where(fn ($q) => $q->where('code', $action)->orWhere('slug', $action))->with('observations.dataset.source', 'observations.datasetFile')->first();
        abort_if($item === null, 404);

        return ['year' => $year, 'item' => $this->budgetNode($item, $year, $this->level($item)), 'coverage' => $this->budgetCoverage($year)];
    }

    /** @return array<string,mixed> */
    public function budgetStateDistribution(int $year, ?string $mission, ?string $programme, string $measurement, string $stage, string $unit): array
    {
        $measure = match ($measurement) {
            'commitment_authorization', 'payment_credit' => $measurement,
            default => abort(422, 'Measurement non supportée.'),
        };
        $stageKey = match ($stage) {
            'initial_budget' => 'lfi',
            'executed' => 'execution',
            default => abort(422, 'Étape budgétaire non supportée.'),
        };
        abort_unless(in_array($unit, ['amount', 'percent', 'per_100'], true), 422, 'Unité non supportée.');

        if ($programme !== null) {
            $parent = $this->findBudgetItem($programme, 'programme');
            abort_if($parent === null, 404);
            $children = $parent->children()->with('observations.dataset.source', 'observations.datasetFile')->get()->map(fn (ClassificationItem $child): array => $this->budgetNode($child, $year, $this->level($child)))->filter(fn (array $child): bool => ($child['contributes_to_program_total'] ?? false) === true)->values();
            $scope = 'programme:'.$parent->code;
        } elseif ($mission !== null) {
            $parent = $this->findBudgetItem($mission, 'mission');
            abort_if($parent === null, 404);
            $children = $parent->children()->with('children.observations.dataset.source', 'children.observations.datasetFile')->get()->map(fn (ClassificationItem $child): array => $this->budgetNode($child, $year, 'programme'))->values();
            $scope = 'mission:'.$parent->code;
        } else {
            $classification = Classification::query()->where('code', self::STATE_CLASSIFICATION)->firstOrFail();
            $children = $classification->items()->whereNull('parent_id')->whereJsonContains('metadata->level', 'mission')->with('children.children.observations.dataset.source', 'children.children.observations.datasetFile')->get()->map(fn (ClassificationItem $child): array => $this->budgetNode($child, $year, 'mission'))->values();
            $scope = 'budget_state';
        }

        $items = $children->map(function (array $child) use ($measure, $stageKey): array {
            $field = $measure === 'commitment_authorization' ? 'ae' : 'cp';
            $amount = (string) ($child[$field][$stageKey] ?? '0.00');
            $status = $child['quality']['status'] ?? 'validated';

            return ['code' => $child['code'], 'label' => $child['label'], 'amount' => $status === 'not_importable' ? null : $amount, 'quality_status' => $status, 'quality' => $child['quality'], 'provenance' => $child['provenance']];
        });
        $included = $items->filter(fn (array $item): bool => $item['amount'] !== null);
        $denominator = DecimalMoney::sum($included->pluck('amount'));
        $items = $items->map(function (array $item) use ($denominator, $unit): array {
            $percent = $item['amount'] === null || $denominator === '0.00' ? null : bcmul(bcdiv($item['amount'], $denominator, 8), '100', 2);
            $item['percent'] = $percent;
            $item['per_100'] = $percent;
            $item['value'] = $unit === 'amount' ? $item['amount'] : $percent;

            return $item;
        })->values();
        $excluded = $items->filter(fn (array $item): bool => $item['amount'] === null);
        $qualityStatuses = $items->groupBy('quality_status');
        $quality = ['status' => $excluded->isNotEmpty() ? 'review_required' : 'validated', 'coverage_percent' => $excluded->isEmpty() ? '100.00' : null, 'included_amount' => $denominator, 'excluded_amount' => null, 'excluded_items' => $excluded->map(fn (array $item): array => ['code' => $item['code'], 'label' => $item['label'], 'status' => $item['quality_status']])->values()->all(), 'validated_items' => $qualityStatuses->get('validated', collect())->count(), 'review_required_items' => $qualityStatuses->get('review_required', collect())->count(), 'not_importable_items' => $qualityStatuses->get('not_importable', collect())->count()];

        return ['year' => $year, 'scope' => $scope, 'measurement' => $measure, 'stage' => $stage, 'unit' => $unit, 'denominator' => $denominator, 'denominator_type' => 'sum_of_importable_children', 'items' => $items->all(), 'quality' => $quality, 'coverage' => $this->budgetCoverage($year)];
    }

    /** @return array<string,int> */
    private function budgetCoverage(int $year): array
    {
        $reportPath = base_path("data/processed/rap/{$year}/report.json");
        $report = is_file($reportPath) ? json_decode((string) File::get($reportPath), true) : [];
        $parsed = (int) ($report['global_summary']['parsed_programs'] ?? $report['parsed'] ?? 0);
        $review = (int) ($report['global_summary']['review_required_programs'] ?? count($report['divergences'] ?? []));
        $notImportable = count(array_unique(array_map(fn (array $error): string => (string) ($error['program'] ?? ''), array_filter($report['errors'] ?? [], fn (array $error): bool => isset($error['program'])))));

        return ['programmes_total' => 183, 'parsed' => $parsed, 'validated' => max(0, $parsed - $review), 'review_required' => $review, 'not_importable' => $notImportable];
    }

    private function findBudgetItem(string $value, string $level): ?ClassificationItem
    {
        return ClassificationItem::query()->whereHas('classification', fn ($q) => $q->where('code', self::STATE_CLASSIFICATION))->whereJsonContains('metadata->level', $level)->where(fn ($q) => $q->where('code', $value)->orWhere('slug', $value)->orWhere('official_label', $value))->with('children.observations.dataset.source', 'children.observations.datasetFile', 'observations.dataset.source', 'observations.datasetFile')->first();
    }

    /** @return array<string,mixed> */
    private function budgetNode(ClassificationItem $item, int $year, string $level, bool $includeChildren = true): array
    {
        $observations = $item->relationLoaded('observations') ? $item->observations : $item->observations()->with('dataset.source', 'datasetFile')->get();
        $status = $this->budgetQuality($item, $observations);
        $node = ['code' => $item->code, 'label' => $item->official_label, 'year' => $year, 'hierarchy_level' => $level, 'parent_action_code' => $item->metadata['parent_action_code'] ?? null, 'contributes_to_program_total' => $item->metadata['contributes_to_program_total'] ?? true, 'ae' => $this->budgetAmounts($observations, 'commitment_authorization'), 'cp' => $this->budgetAmounts($observations, 'payment_credit'), 'quality' => $status, 'provenance' => $this->budgetProvenance($observations)];
        if ($includeChildren) {
            $children = $item->children()->with('observations.dataset.source', 'observations.datasetFile')->get();
            $childNodes = $children->map(fn (ClassificationItem $child): array => $this->budgetNode($child, $year, $this->level($child)))->values()->all();
            $node['children'] = $childNodes;
            if ($childNodes !== []) {
                $node['quality'] = $this->worstQuality($status, $childNodes);
                if ($node['provenance']['source'] === null) {
                    $node['provenance'] = $childNodes[0]['provenance'] ?? $node['provenance'];
                }
            }
            if (in_array($level, ['mission', 'programme'], true)) {
                $contributing = collect($childNodes)->filter(fn (array $child): bool => ($child['contributes_to_program_total'] ?? true) === true);
                $node['ae'] = $this->sumBudgetNodeAmounts($contributing, 'ae');
                $node['cp'] = $this->sumBudgetNodeAmounts($contributing, 'cp');
            }
        }

        return $node;
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $nodes
     * @return array{lfi:string,execution:string}
     */
    private function sumBudgetNodeAmounts(Collection $nodes, string $field): array
    {
        $lfi = $nodes->map(fn (array $node): string => (string) ($node[$field]['lfi'] ?? '0.00'));
        $execution = $nodes->map(fn (array $node): string => (string) ($node[$field]['execution'] ?? '0.00'));

        return ['lfi' => DecimalMoney::sum($lfi), 'execution' => DecimalMoney::sum($execution)];
    }

    /**
     * @param  array{status:string,reason:string,source:mixed,source_page:mixed}  $current
     * @param  list<array<string,mixed>>  $children
     * @return array{status:string,reason:string,source:mixed,source_page:mixed}
     */
    private function worstQuality(array $current, array $children): array
    {
        $rank = ['validated' => 0, 'review_required' => 1, 'not_importable' => 2];
        foreach ($children as $child) {
            $quality = $child['quality'] ?? [];
            if (($rank[$quality['status'] ?? 'validated'] ?? 0) > ($rank[$current['status']] ?? 0)) {
                $current = ['status' => $quality['status'], 'reason' => $quality['reason'], 'source' => $quality['source'] ?? $current['source'], 'source_page' => $quality['source_page'] ?? $current['source_page']];
            }
        }

        return $current;
    }

    /**
     * @param  Collection<int,FinancialObservation>  $observations
     * @return array{lfi:string,execution:string}
     */
    private function budgetAmounts(Collection $observations, string $measure): array
    {
        $rows = $observations->filter(fn (FinancialObservation $observation): bool => $observation->measure?->value === $measure);

        return ['lfi' => DecimalMoney::sum($rows->filter(fn ($row): bool => $row->budget_stage?->value === 'initial_budget')->pluck('amount')), 'execution' => DecimalMoney::sum($rows->filter(fn ($row): bool => $row->budget_stage?->value === 'execution')->pluck('amount'))];
    }

    /**
     * @param  Collection<int,FinancialObservation>  $observations
     * @return array{source:mixed,source_url:mixed,source_page:mixed,dataset:mixed,raw_label:mixed}
     */
    private function budgetProvenance(Collection $observations): array
    {
        $observation = $observations->first();
        $metadata = $observation === null ? [] : ($observation->metadata ?? []);

        return ['source' => $observation?->dataset?->source?->name, 'source_url' => $metadata['source_url'] ?? $observation?->dataset?->source?->homepage_url, 'source_page' => $metadata['source_page'] ?? null, 'dataset' => $observation?->dataset?->slug, 'raw_label' => $metadata['raw_label'] ?? $observation?->classificationItem?->official_label];
    }

    /**
     * @param  Collection<int,FinancialObservation>  $observations
     * @return array{status:string,reason:string,source:mixed,source_page:mixed}
     */
    private function budgetQuality(ClassificationItem $item, Collection $observations): array
    {
        $program = $item->code !== null ? explode('-', $item->code)[0] : null;
        $reportPath = base_path('data/processed/rap/2024/report.json');
        $report = is_file($reportPath) ? json_decode((string) File::get($reportPath), true) : [];
        $notImportable = collect((array) ($report['errors'] ?? []))->contains(fn (mixed $error): bool => is_array($error) && (string) ($error['program'] ?? '') === $program);
        $review = $notImportable || $observations->contains(fn (FinancialObservation $observation): bool => ($observation->metadata['review_required'] ?? false) === true);
        $provenance = $this->budgetProvenance($observations);

        return ['status' => $notImportable ? 'not_importable' : ($review ? 'review_required' : 'validated'), 'reason' => $notImportable ? 'Le RAP n’est pas importable dans le modèle détaillé.' : ($review ? 'Le contrôle de cohérence du RAP nécessite une revue.' : 'Aucune divergence de validation enregistrée.'), 'source' => $provenance['source'], 'source_page' => $provenance['source_page']];
    }

    private function level(ClassificationItem $item): string
    {
        return (string) ($item->metadata['level'] ?? (str_contains((string) $item->code, '.') ? 'sub_action' : 'action'));
    }

    /** @return list<int> */
    public function years(): array
    {
        return FinancialObservation::query()->whereHas('importBatch', fn ($q) => $q->where('status', 'completed'))->distinct()->orderBy('year')->pluck('year')->map(fn ($year) => (int) $year)->all();
    }

    /** @return list<array<string,mixed>> */
    public function sources(): array
    {
        return Dataset::query()->with('source')->whereHas('observations.importBatch', fn ($q) => $q->where('status', 'completed'))->orderBy('name')->get()->map(fn (Dataset $dataset) => [
            'code' => $dataset->slug, 'name' => $dataset->name, 'source' => $dataset->source->name,
            'publisher' => $dataset->source->publisher, 'accounting_system' => $dataset->accounting_system,
            'scope' => $dataset->scope, 'first_year' => $dataset->first_year, 'last_year' => $dataset->last_year,
        ])->all();
    }

    /** @return list<array<string,mixed>> */
    public function categories(string $classificationCode, ?int $parentId = null): array
    {
        $classification = Classification::query()->where('code', $classificationCode)->firstOrFail();

        return $classification->items()->where('parent_id', $parentId)->orderBy('official_label')->get()->map(fn ($item) => [
            'id' => $item->id, 'code' => $item->code, 'slug' => $item->slug, 'name' => $item->official_label,
            'description' => $item->description, 'parent_id' => $item->parent_id,
        ])->all();
    }

    /** @return array<string,mixed> */
    public function overview(int $year): array
    {
        return [
            'year' => $year,
            'public_finances' => $this->nationalAccountsTotals($year),
            'institutional_distribution' => $this->institutionalDistribution($year),
            'functional_distribution' => $this->cofogDistribution($year),
            'state_budget' => $this->stateBudgetOverview($year),
            'revenues' => $this->revenuesOverview($year),
            'methodology' => [
                'national_accounts' => 'Comptes nationaux INSEE : administrations publiques consolidées.',
                'budget_accounting' => 'Budget de l’État : crédits de paiement exécutés du PLRG/RAP.',
                'separation_rule' => 'Les comptes nationaux et la comptabilité budgétaire sont exposés séparément et ne sont jamais additionnés.',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function nationalAccountsTotals(int $year): array
    {
        $rows = $this->overviewRows($year, 'insee-t-3201', 'national_accounts');
        $expenditure = $this->labelRow($rows, 'Total des dépenses');
        $revenue = $this->labelRow($rows, 'Total des recettes');
        $balance = $this->labelRow($rows, 'Capacité (+) ou besoin (-) de financement');

        return [
            'scope' => 'general_government', 'accounting_basis' => 'national_accounts',
            'measurement_type' => 'expenditure_and_revenue', 'stage' => 'execution',
            'consolidation' => 'consolidated', 'year' => $year,
            'amount' => $expenditure?->amount, 'expenditure' => $this->amountBlock($expenditure),
            'revenue' => $this->amountBlock($revenue), 'balance' => $this->amountBlock($balance),
            'dataset' => 'insee-t-3201', 'source' => 'INSEE',
            'quality' => $this->availabilityQuality($expenditure !== null && $revenue !== null, 'Total APU INSEE consolidé disponible dans T_3201.'),
        ];
    }

    /** @return array<string,mixed> */
    private function institutionalDistribution(int $year): array
    {
        $rows = collect(['central_government' => 'insee-t-3202', 'local_government' => 'insee-t-3205', 'social_security' => 'insee-t-3212'])
            ->map(fn (string $dataset): ?FinancialObservation => $this->labelRow($this->overviewRows($year, $dataset, 'national_accounts'), 'Total des dépenses'))
            ->filter()->values();
        $reference = $this->labelRow($this->overviewRows($year, 'insee-t-3201', 'national_accounts'), 'Total des dépenses');
        $names = ['central_government' => 'Administrations centrales', 'local_government' => 'Administrations publiques locales', 'social_security' => 'Administrations de sécurité sociale'];
        $items = $rows->map(function (FinancialObservation $row) use ($names, $year): array {
            $scope = $row->dataset->scope;

            return [
                'code' => $scope, 'label' => $names[$scope] ?? $scope, 'amount' => $row->amount,
                'year' => $year, 'quality_status' => 'review_required',
                'provenance' => $this->overviewProvenance($row),
            ];
        })->values()->all();
        $quality = $this->availabilityQuality(count($items) === 3, 'T_3215 absent ; distribution construite à partir des tableaux sectoriels disponibles sans reconstruction du total APU.');
        if ($items !== []) {
            $quality['status'] = 'review_required';
        }

        return [
            'scope' => 'general_government', 'accounting_basis' => 'national_accounts',
            'measurement_type' => 'expenditure', 'stage' => 'execution', 'consolidation' => 'consolidated',
            'amount' => $reference?->amount, 'denominator' => $reference?->amount, 'year' => $year,
            'dataset' => 'insee-t-3202,t-3205,t-3212', 'source' => 'INSEE', 'items' => $items,
            'quality' => $quality,
        ];
    }

    /** @return array<string,mixed> */
    private function cofogDistribution(int $year): array
    {
        $rows = FinancialObservation::query()->where('year', $year)->where('measurement_type', 'expenditure')->whereHas('dataset', fn ($query) => $query->where('slug', 'insee-t-3301'))
            ->where('accounting_basis', 'national_accounts')->whereHas('classificationItem', fn ($query) => $query->where('code', 'like', 'GF__')->whereJsonContains('metadata->cofog_level', 1))->whereHas('classificationItem.classification', fn ($query) => $query->where('code', 'cofog'))
            ->whereHas('importBatch', fn ($query) => $query->where('status', 'completed'))->with(['dataset.source', 'classificationItem'])->get();
        if ($rows->isEmpty()) {
            return $this->unavailableBlock($year, 'general_government', 'national_accounts', 'expenditure', 'execution', 'COFOG 2024 n’est pas encore importé.');
        }
        $total = FinancialObservation::query()->where('year', $year)->where('dataset_id', $rows->first()->dataset_id)->whereHas('classificationItem', fn ($query) => $query->where('code', '_Z'))->first();
        $denominator = $total === null ? DecimalMoney::sum($rows->pluck('amount')) : $total->amount;

        return $this->distributionBlock($year, 'general_government', 'national_accounts', 'expenditure', 'execution', 'consolidated', $rows, $denominator);
    }

    /** @return array<string,mixed> */
    private function stateBudgetOverview(int $year): array
    {
        if (Classification::query()->where('code', self::STATE_CLASSIFICATION)->doesntExist()) {
            return $this->unavailableBlock($year, 'french_state_budget', 'budgetary', 'payment_credit', 'execution', 'Les données détaillées du budget de l’État 2024 ne sont pas importées dans cette base.');
        }
        $distribution = $this->budgetStateDistribution($year, null, null, 'payment_credit', 'executed', 'per_100');
        $quality = $distribution['quality'];
        $publishableAmount = ($quality['status'] ?? 'review_required') === 'validated';
        /** @var list<array<string,mixed>> $distributionItems */
        $distributionItems = $distribution['items'];
        $validatedAmount = DecimalMoney::sum(collect($distributionItems)->filter(fn (array $item): bool => ($item['quality_status'] ?? null) === 'validated' && $item['amount'] !== null)->pluck('amount'));

        return [
            'scope' => 'french_state_budget', 'accounting_basis' => 'budgetary', 'measurement_type' => 'payment_credit',
            'stage' => 'execution', 'consolidation' => 'not_consolidated', 'amount' => $publishableAmount ? $distribution['denominator'] : null,
            'year' => $year, 'dataset' => 'state-budget-rap-2024', 'source' => 'PLRG/RAP 2024',
            'distribution' => $distribution['items'], 'denominator' => $publishableAmount ? $distribution['denominator'] : null,
            'known_detailed_amount' => $validatedAmount, 'reference_denominator' => $distribution['denominator'],
            'quality' => $quality, 'coverage' => $distribution['coverage'],
        ];
    }

    /** @return array<string,mixed> */
    private function revenuesOverview(int $year): array
    {
        $nationalRows = $this->overviewRows($year, 'insee-t-3201', 'national_accounts');
        $national = $this->labelRow($nationalRows, 'Total des recettes');
        $stateRows = FinancialObservation::query()->where('year', $year)->where('accounting_basis', 'budgetary')->where('measurement_type', 'revenue')->where('budget_stage', 'execution')->whereJsonContains('metadata->csv_level', 3)->whereHas('dataset', fn ($query) => $query->where('scope', 'state_budget'))->whereHas('importBatch', fn ($query) => $query->where('status', 'completed'))->with(['dataset.source'])->get();

        return [
            'year' => $year,
            'public_revenues' => $this->blockMetadata('general_government', 'national_accounts', 'revenue', 'execution', 'consolidated', 'insee-t-3201', 'INSEE', $this->availabilityQuality($national !== null, 'Recettes APU consolidées INSEE.')) + ['amount' => $national?->amount, 'year' => $year, 'unit' => 'EUR'],
            'state_budget_revenues' => $stateRows->isEmpty() ? $this->unavailableBlock($year, 'state_budget', 'budgetary', 'revenue', 'execution', 'Recettes budgétaires de l’État 2024 non importées dans un dataset canonique.') : $this->blockMetadata('state_budget', 'budgetary', 'revenue', 'execution', 'not_consolidated', 'state-budget-revenue-execution-2024', 'PLRG 2024', $this->availabilityQuality(true, 'Recettes budgétaires de l’État disponibles.')) + ['amount' => DecimalMoney::sum($stateRows->pluck('amount')), 'year' => $year],
        ];
    }

    /** @return Collection<int,FinancialObservation> */
    private function overviewRows(int $year, string $datasetSlug, string $basis): Collection
    {
        return FinancialObservation::query()->where('year', $year)->where('accounting_basis', $basis)->whereHas('dataset', fn ($query) => $query->where('slug', $datasetSlug))->whereHas('importBatch', fn ($query) => $query->where('status', 'completed'))->with(['dataset.source', 'datasetFile', 'classificationItem'])->get();
    }

    /** @param Collection<int,FinancialObservation> $rows */
    private function labelRow(Collection $rows, string $label): ?FinancialObservation
    {
        return $rows->first(fn (FinancialObservation $row): bool => trim((string) $row->classificationItem->official_label) === $label);
    }

    /** @return array<string,mixed> */
    private function amountBlock(?FinancialObservation $row): array
    {
        return ['amount' => $row?->amount, 'unit' => 'EUR', 'year' => $row?->year, 'dataset' => $row?->dataset?->slug, 'source' => $row?->dataset?->source?->name, 'source_page' => $row?->metadata['source_page'] ?? null];
    }

    /**
     * @param  array<string,mixed>  $quality
     * @return array<string,mixed>
     */
    private function blockMetadata(string $scope, string $basis, string $measurement, string $stage, string $consolidation, ?string $dataset, ?string $source, array $quality): array
    {
        return compact('scope', 'basis', 'measurement', 'stage', 'consolidation', 'dataset', 'source', 'quality') + ['accounting_basis' => $basis, 'measurement_type' => $measurement];
    }

    /** @return array<string,mixed> */
    private function unavailableBlock(int $year, string $scope, string $basis, string $measurement, string $stage, string $reason): array
    {
        return $this->blockMetadata($scope, $basis, $measurement, $stage, 'unknown', null, null, $this->availabilityQuality(false, $reason)) + ['year' => $year, 'amount' => null, 'items' => [], 'denominator' => null];
    }

    /** @return array<string,mixed> */
    private function availabilityQuality(bool $available, string $reason): array
    {
        return ['status' => $available ? 'validated' : 'not_importable', 'reason' => $reason, 'coverage_percent' => $available ? '100.00' : '0.00'];
    }

    /** @return array<string,mixed> */
    private function overviewProvenance(FinancialObservation $row): array
    {
        return ['dataset' => $row->dataset->slug, 'source' => $row->dataset->source->name, 'source_url' => $row->dataset->source->homepage_url, 'source_page' => $row->metadata['source_page'] ?? null, 'raw_label' => $row->metadata['raw_label'] ?? $row->classificationItem->official_label];
    }

    /**
     * @param  Collection<int,FinancialObservation>  $rows
     * @return array<string,mixed>
     */
    private function distributionBlock(int $year, string $scope, string $basis, string $measurement, string $stage, string $consolidation, Collection $rows, string $denominator): array
    {
        $items = $rows->map(fn (FinancialObservation $row): array => ['code' => $row->classificationItem->code, 'label' => $row->classificationItem->official_label, 'amount' => $row->amount, 'percent' => $denominator === '0.00' ? null : bcmul(bcdiv($row->amount, $denominator, 8), '100', 2), 'per_100' => $denominator === '0.00' ? null : bcmul(bcdiv($row->amount, $denominator, 8), '100', 2), 'quality_status' => 'validated', 'provenance' => $this->overviewProvenance($row)])->values()->all();

        return ['year' => $year, 'scope' => $scope, 'accounting_basis' => $basis, 'measurement_type' => $measurement, 'stage' => $stage, 'consolidation' => $consolidation, 'amount' => $denominator, 'denominator' => $denominator, 'items' => $items, 'quality' => ['status' => 'validated', 'coverage_percent' => '100.00', 'included_amount' => $denominator, 'excluded_amount' => null, 'excluded_items' => []]];
    }

    /** @return list<array{year:int,amount:string}> */
    public function history(string $metric, ?string $classification, ?string $category, ?string $scope, int $from, int $to, ?string $accountingBasis = null): array
    {
        $query = FinancialObservation::query()->whereBetween('year', [$from, $to])->where('measurement_type', $metric)->whereHas('importBatch', fn ($q) => $q->where('status', 'completed'));
        if ($scope) {
            $query->whereHas('accountingScope', fn ($q) => $q->where('code', $scope));
        }
        if ($classification) {
            $query->whereHas('classificationItem.classification', fn ($q) => $q->where('code', $classification));
        }
        if ($category) {
            $query->whereHas('classificationItem', fn ($q) => $q->where('slug', $category)->orWhere('code', $category));
        }
        if ($accountingBasis) {
            $query->where('accounting_basis', $accountingBasis);
        }

        return $query->get()->groupBy('year')->map(fn (Collection $rows, $year) => ['year' => (int) $year, 'amount' => DecimalMoney::sum($rows->pluck('amount'))])->sortBy('year')->values()->all();
    }

    /** @return array{query:string,year:int|null,items:list<array<string,mixed>>} */
    public function search(string $term, ?int $year, ?string $scope, ?string $types, int $limit = 20): array
    {
        $wantedTypes = collect(explode(',', (string) $types))->map(fn (string $type): string => trim($type))->filter()->values();
        $items = ClassificationItem::query()->with(['classification', 'parent.parent.parent.parent'])
            ->when($scope !== null, fn ($query) => $query->whereHas('classification', fn ($classification) => $classification->where('code', $scope)))
            ->when($year !== null, fn ($query) => $query->whereHas('observations', fn ($observations) => $observations->where('year', $year)->whereHas('importBatch', fn ($batch) => $batch->where('status', 'completed'))))
            ->get();
        $needle = Str::lower(Str::ascii(trim($term)));
        $results = $items->map(function (ClassificationItem $item) use ($needle, $year, $wantedTypes): ?array {
            $type = $this->searchType($item);
            if ($wantedTypes->isNotEmpty() && ! $wantedTypes->contains($type)) {
                return null;
            }
            $rawLabel = (string) ($item->metadata['raw_label'] ?? '');
            $label = Str::lower(Str::ascii($item->official_label));
            $code = Str::lower(Str::ascii((string) $item->code));
            $raw = Str::lower(Str::ascii($rawLabel));
            if (! Str::contains($label, $needle) && ! Str::contains($code, $needle) && ! Str::contains($raw, $needle)) {
                return null;
            }
            $score = Str::contains($label, $needle) ? (Str::startsWith($label, $needle) ? 80 : 50) : 20;
            if ($code === $needle) {
                $score += 100;
            }
            $parents = $this->searchParents($item);
            $observations = $year === null ? collect() : $item->observations()->where('year', $year)->whereHas('importBatch', fn ($batch) => $batch->where('status', 'completed'))->get();
            $observation = $observations->first(fn (FinancialObservation $row): bool => $row->measure?->value === 'payment_credit' && $row->budget_stage?->value === 'execution') ?? $observations->first();

            return ['type' => $type, 'code' => $item->code, 'label' => $item->official_label, 'year' => $year, 'scope' => $item->classification->code, 'classification' => $item->classification->code, 'parent' => $parents !== [] ? $parents[count($parents) - 1] : null, 'breadcrumb' => array_merge(array_reverse($parents), [['type' => $type, 'code' => $item->code, 'label' => $item->official_label]]), 'amount' => $observation?->amount, 'quality_status' => 'validated', 'score' => $score];
        })->filter()->sortByDesc('score')->take($limit)->values()->all();

        return ['query' => $term, 'year' => $year, 'items' => array_map(fn (array $result): array => collect($result)->except('score')->all(), $results)];
    }

    private function searchType(ClassificationItem $item): string
    {
        if ($item->classification->code === self::STATE_CLASSIFICATION) {
            return (string) ($item->metadata['level'] ?? 'classification');
        }
        if ($item->classification->code === 'cofog') {
            return 'cofog';
        }
        if ($item->classification->code === 'state_budget_revenue') {
            return 'revenue';
        }

        return 'classification';
    }

    /** @return list<array{type:string,code:string|null,label:string}> */
    private function searchParents(ClassificationItem $item): array
    {
        $parents = [];
        $parent = $item->parent;
        while ($parent !== null) {
            $parents[] = ['type' => $this->searchType($parent), 'code' => $parent->code, 'label' => $parent->official_label];
            $parent = $parent->parent;
        }

        return $parents;
    }
}
