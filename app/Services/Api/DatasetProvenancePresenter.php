<?php

namespace App\Services\Api;

use App\Models\Dataset;
use App\Models\DatasetFile;
use App\Models\ImportBatch;

class DatasetProvenancePresenter
{
    /** @return array<string, mixed> */
    public function present(Dataset $dataset, DatasetFile $file, ImportBatch $batch): array
    {
        $dataset->loadMissing('source');

        return [
            'dataset' => [
                'id' => $dataset->slug,
                'name' => $dataset->name,
                'publication_title' => $dataset->publication_title,
                'publication_date' => $dataset->publication_date?->toDateString(),
                'source_url' => $dataset->source_url,
                'download_url' => $dataset->download_url,
                'downloaded_at' => $dataset->downloaded_at?->toIso8601String(),
                'license' => [
                    'name' => $dataset->license_name,
                    'url' => $dataset->license_url,
                ],
                'publication_ready' => $dataset->isPublishable(),
            ],
            'publisher' => [
                'name' => $dataset->source->publisher,
                'source_name' => $dataset->source->name,
                'homepage_url' => $dataset->source->homepage_url,
                'official' => $dataset->source->is_official,
            ],
            'file' => [
                'descriptor' => $file->slug,
                'filename' => $batch->filename,
                'checksum_sha256' => $batch->checksum,
            ],
            'import' => [
                'batch_id' => $batch->id,
                'completed_at' => $batch->completed_at?->toIso8601String(),
                'rows_read' => $batch->rows_read,
                'observations_imported' => $batch->rows_imported,
            ],
        ];
    }
}
