<?php

namespace Tests\Unit;

use App\Models\ClassificationItem;
use App\Models\Dataset;
use App\Models\FinancialObservation;
use App\Models\Source;
use App\Services\Api\PublicFinanceQuery;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PublicFinanceQueryTest extends TestCase
{
    public function test_it_builds_quality_and_unavailable_blocks(): void
    {
        $query = app(PublicFinanceQuery::class);
        $invoke = $this->invoker($query);

        $this->assertSame(['status' => 'validated', 'reason' => 'ok', 'coverage_percent' => '100.00'], $invoke('availabilityQuality', true, 'ok'));
        $this->assertSame(['status' => 'not_importable', 'reason' => 'missing', 'coverage_percent' => '0.00'], $invoke('availabilityQuality', false, 'missing'));
        $this->assertSame([
            'scope' => 'scope', 'basis' => 'basis', 'measurement' => 'measure', 'stage' => 'stage',
            'consolidation' => 'consolidated', 'dataset' => 'dataset', 'source' => 'source',
            'quality' => ['status' => 'validated'], 'accounting_basis' => 'basis', 'measurement_type' => 'measure',
        ], $invoke('blockMetadata', 'scope', 'basis', 'measure', 'stage', 'consolidated', 'dataset', 'source', ['status' => 'validated']));

        $unavailable = $invoke('unavailableBlock', 2024, 'scope', 'basis', 'measure', 'stage', 'missing');
        $this->assertSame(2024, $unavailable['year']);
        $this->assertNull($unavailable['amount']);
        $this->assertNull($unavailable['denominator']);
        $this->assertSame([], $unavailable['items']);
        $this->assertSame('not_importable', $unavailable['quality']['status']);
    }

    public function test_it_maps_distribution_rows_and_percentages(): void
    {
        $query = app(PublicFinanceQuery::class);
        $source = new Source(['name' => 'Source', 'homepage_url' => 'https://example.test']);
        $dataset = new Dataset(['slug' => 'dataset']);
        $dataset->setRelation('source', $source);
        $item = new ClassificationItem(['code' => 'GF01', 'official_label' => 'Fonction 1']);
        $row = new FinancialObservation(['amount' => '25.00', 'metadata' => ['source_page' => 4]]);
        $row->setRelation('classificationItem', $item);
        $row->setRelation('dataset', $dataset);

        $result = $this->invoker($query)('distributionBlock', 2024, 'scope', 'national_accounts', 'expenditure', 'execution', 'consolidated', new Collection([$row]), '100.00');

        $this->assertSame('100.00', $result['amount']);
        $this->assertSame('100.00', $result['denominator']);
        $this->assertSame('GF01', $result['items'][0]['code']);
        $this->assertSame('Fonction 1', $result['items'][0]['label']);
        $this->assertSame('25.00', $result['items'][0]['amount']);
        $this->assertSame('25.00', $result['items'][0]['percent']);
        $this->assertSame('25.00', $result['items'][0]['per_100']);
        $this->assertSame('validated', $result['items'][0]['quality_status']);
        $this->assertSame('dataset', $result['items'][0]['provenance']['dataset']);
        $this->assertSame('Source', $result['items'][0]['provenance']['source']);
        $this->assertSame(4, $result['items'][0]['provenance']['source_page']);
        $this->assertSame('validated', $result['quality']['status']);
        $this->assertSame('100.00', $result['quality']['coverage_percent']);
        $this->assertSame([], $result['quality']['excluded_items']);
    }

    public function test_it_resolves_explicit_and_implicit_hierarchy_levels(): void
    {
        $query = app(PublicFinanceQuery::class);
        $invoke = $this->invoker($query);

        $explicit = new ClassificationItem(['code' => '01.2', 'metadata' => ['level' => 'custom']]);
        $implicitSubAction = new ClassificationItem(['code' => '01.2', 'metadata' => []]);
        $implicitAction = new ClassificationItem(['code' => '01', 'metadata' => []]);

        $this->assertSame('custom', $invoke('level', $explicit));
        $this->assertSame('sub_action', $invoke('level', $implicitSubAction));
        $this->assertSame('action', $invoke('level', $implicitAction));
    }

    /** @return callable(string, mixed ...$arguments): mixed */
    private function invoker(PublicFinanceQuery $query): callable
    {
        return static function (string $method, mixed ...$arguments) use ($query): mixed {
            $reflection = new \ReflectionMethod($query, $method);
            $reflection->setAccessible(true);

            return $reflection->invoke($query, ...$arguments);
        };
    }
}
