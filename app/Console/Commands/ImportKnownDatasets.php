<?php

namespace App\Console\Commands;

use App\Models\DatasetFile;
use App\Services\Imports\Exceptions\DuplicateImportException;
use App\Services\Imports\InseePublicAccountsXlsxImporter;
use App\Services\Imports\InseeCofogXlsxImporter;
use App\Services\Imports\StateBudgetRevenueCsvImporter;
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
        InseePublicAccountsXlsxImporter $inseeImporter,
        InseeCofogXlsxImporter $cofogImporter,
        StateBudgetRevenueCsvImporter $revenueCsvImporter,
    ): int {
        $directory = rtrim((string) $this->argument('path'), DIRECTORY_SEPARATOR);
        $imported = 0;
        $alreadyImported = 0;
        $missing = 0;

        foreach (DatasetFile::query()->orderBy('slug')->get() as $descriptor) {
            if ($descriptor->expected_filename === null) {
                continue;
            }

            $path = $this->findFile($directory, $descriptor->expected_filename);

            if (! is_file($path)) {
                $missing++;

                continue;
            }

            try {
                $importer = match ($descriptor->metadata['importer'] ?? null) {
                    'state_expenditure_plrg' => $expenditureImporter,
                    'state_budget_revenue_xlsx' => $revenueImporter,
                    'insee_public_accounts_xlsx' => $inseeImporter,
                    'insee_cofog_xlsx' => $cofogImporter,
                    'state_budget_revenue_csv' => $revenueCsvImporter,
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

    private function findFile(string $directory, string $filename): string
    {
        $directPath = $directory.DIRECTORY_SEPARATOR.$filename;
        if (is_file($directPath)) {
            return $directPath;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }

        return $directPath;
    }
}
