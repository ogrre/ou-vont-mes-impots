<?php

namespace App\Console\Commands;

use App\Models\DatasetFile;
use App\Services\Imports\Exceptions\DuplicateImportException;
use App\Services\Imports\StateBudgetRevenueXlsxImporter;
use App\Services\Imports\StateExpenditurePlrgImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportKnownDatasets extends Command
{
    protected $signature = 'dataset:import-known {path=data : Dossier contenant les fichiers sources connus}';

    protected $description = 'Importe les nouveaux contenus présents pour les descripteurs de datasets connus';

    public function handle(
        StateExpenditurePlrgImporter $expenditureImporter,
        StateBudgetRevenueXlsxImporter $revenueImporter,
    ): int {
        $directory = rtrim((string) $this->argument('path'), DIRECTORY_SEPARATOR);
        $imported = 0;
        $alreadyImported = 0;
        $missing = 0;

        foreach (DatasetFile::query()->orderBy('slug')->get() as $descriptor) {
            if ($descriptor->expected_filename === null) {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$descriptor->expected_filename;

            if (! is_file($path)) {
                $missing++;

                continue;
            }

            try {
                $importer = match ($descriptor->metadata['importer'] ?? null) {
                    'state_expenditure_plrg' => $expenditureImporter,
                    'state_budget_revenue_xlsx' => $revenueImporter,
                    default => throw new \RuntimeException("Aucun importeur associé à {$descriptor->slug}."),
                };
                $batch = $importer->import($descriptor, $path);
                $this->info("{$descriptor->slug} : import #{$batch->id} terminé ({$batch->rows_imported} observations).");
                $imported++;
            } catch (DuplicateImportException) {
                $this->line("{$descriptor->slug} : contenu déjà importé, ignoré.");
                $alreadyImported++;
            } catch (Throwable $exception) {
                $this->error("{$descriptor->slug} : {$exception->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->info("Imports terminés : {$imported} nouveau(x), {$alreadyImported} déjà présent(s), {$missing} fichier(s) absent(s).");

        return self::SUCCESS;
    }
}
