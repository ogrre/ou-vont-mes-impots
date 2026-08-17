<?php

namespace Tests\Feature;

use App\Models\Dataset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatasetPublishabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_critical_provenance_fields_are_required_for_publication(): void
    {
        $this->seed(DatabaseSeeder::class);
        $dataset = Dataset::where('slug', 'state-expenditure-execution-2025')->with('source')->firstOrFail();
        $dataset->source->update(['publisher' => 'Producteur officiel', 'is_official' => true]);
        $dataset->update([
            'publication_title' => 'Titre officiel',
            'downloaded_at' => '2026-01-01 12:00:00',
            'license_name' => 'Licence Ouverte',
        ]);

        $this->assertTrue($dataset->fresh()->isPublishable());

        foreach (['source_url', 'publication_title', 'downloaded_at', 'license_name', 'year'] as $field) {
            $fresh = $dataset->fresh();
            $original = $fresh->{$field};
            $fresh->update([$field => null]);
            $this->assertFalse($fresh->fresh()->isPublishable(), "{$field} doit être obligatoire.");
            $fresh->update([$field => $original]);
        }

        foreach (['accounting_scope', 'reporting_period', 'unit', 'status'] as $field) {
            $fresh = $dataset->fresh();
            $metadata = $fresh->metadata;
            unset($metadata[$field]);
            $fresh->update(['metadata' => $metadata]);
            $this->assertFalse($fresh->fresh()->isPublishable(), "metadata.{$field} doit être obligatoire.");
            $metadata[$field] = $dataset->metadata[$field];
            $fresh->update(['metadata' => $metadata]);
        }

        $dataset->source->update(['is_official' => false]);
        $this->assertFalse($dataset->fresh()->isPublishable());
        $dataset->source->update(['is_official' => true, 'publisher' => null]);
        $this->assertFalse($dataset->fresh()->isPublishable());
    }
}
