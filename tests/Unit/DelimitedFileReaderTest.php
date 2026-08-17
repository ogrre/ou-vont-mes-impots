<?php

namespace Tests\Unit;

use App\Services\Imports\DelimitedFileReader;
use App\Services\Imports\Exceptions\InvalidSourceDataException;
use PHPUnit\Framework\TestCase;

class DelimitedFileReaderTest extends TestCase
{
    public function test_it_streams_rows_with_their_physical_line_numbers(): void
    {
        $rows = iterator_to_array((new DelimitedFileReader)->rows(
            dirname(__DIR__).'/Fixtures/plrg/valid.csv',
        ));

        $this->assertSame([1, 2, 3], array_keys($rows));
        $this->assertSame('Éducation', $rows[2][0]);
    }

    public function test_it_rejects_an_unreadable_path(): void
    {
        $this->expectException(InvalidSourceDataException::class);
        iterator_to_array((new DelimitedFileReader)->rows('/definitely/missing.csv'));
    }
}
