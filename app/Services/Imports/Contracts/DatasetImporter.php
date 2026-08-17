<?php

namespace App\Services\Imports\Contracts;

use App\Models\DatasetFile;
use App\Models\ImportBatch;

interface DatasetImporter
{
    public function import(DatasetFile $datasetFile, string $path): ImportBatch;
}
