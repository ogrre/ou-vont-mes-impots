<?php

namespace App\Services\Imports;

use App\Services\Imports\Exceptions\InvalidSourceDataException;
use Generator;
use SplFileObject;

class DelimitedFileReader
{
    /** @return Generator<int, array<int, string|null>> */
    public function rows(string $path, string $delimiter = ';'): Generator
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidSourceDataException("Fichier introuvable ou illisible : {$path}");
        }

        $file = new SplFileObject($path, 'rb');
        $file->setCsvControl($delimiter, '"', '');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);

        foreach ($file as $index => $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            yield $index + 1 => $row;
        }
    }
}
