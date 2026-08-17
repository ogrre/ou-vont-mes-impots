<?php

namespace App\Console\Commands;

use App\Models\DatasetFile;
use App\Services\Imports\Exceptions\DuplicateImportException;
use App\Services\Imports\StateBudgetRevenueXlsxImporter;
use App\Services\Imports\StateExpenditurePlrgImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportDataset extends Command
{
    protected $signature = 'dataset:import {descriptor : Identifiant du descripteur connu} {path : Chemin du fichier source}';

    protected $description = 'Importe un fichier financier à partir de son descripteur sémantique';

    public function handle(
        StateExpenditurePlrgImporter $expenditureImporter,
        StateBudgetRevenueXlsxImporter $revenueImporter,
    ): int {
        $descriptor = DatasetFile::query()->where('slug', $this->argument('descriptor'))->first();

        if ($descriptor === null) {
            $this->error('Descripteur inconnu. Exécutez php artisan db:seed puis vérifiez son identifiant.');

            return self::FAILURE;
        }

        try {
            $importer = match ($descriptor->metadata['importer'] ?? null) {
                'state_expenditure_plrg' => $expenditureImporter,
                'state_budget_revenue_xlsx' => $revenueImporter,
                default => throw new \RuntimeException('Aucun importeur associé à ce descripteur.'),
            };
            $batch = $importer->import($descriptor, $this->argument('path'));
        } catch (DuplicateImportException $exception) {
            $this->warn($exception->getMessage());

            return self::INVALID;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Import #{$batch->id} terminé : {$batch->rows_read} lignes lues, {$batch->rows_imported} observations.");

        return self::SUCCESS;
    }
}
