<?php

namespace App\Console\Commands;

use App\Services\Validation\StateExpenditureReconciliation;
use Illuminate\Console\Command;

class ValidateStateExpenditure extends Command
{
    protected $signature = 'data:validate {dataset : Dataset à valider (state-expenditure-2025)}';

    protected $description = 'Réconcilie les totaux PLRG entre mission, ministère et nature';

    public function handle(StateExpenditureReconciliation $reconciliation): int
    {
        if ($this->argument('dataset') !== 'state-expenditure-2025') {
            $this->error('Jeu de données non pris en charge.');

            return self::INVALID;
        }

        $result = $reconciliation->validate(2025);

        foreach ($result['comparisons'] as $comparison) {
            $this->line($comparison['measure'].' / '.$comparison['component']);

            foreach ($comparison['values'] as $classification => $total) {
                $this->line("  {$classification}: {$total} EUR");
            }
        }

        if (! $result['valid']) {
            $this->error(count($result['discrepancies']).' écart(s) ou groupe(s) incomplet(s) détecté(s).');

            return self::FAILURE;
        }

        $this->info('Les trois classifications se réconcilient exactement pour chaque mesure et composante.');

        return self::SUCCESS;
    }
}
