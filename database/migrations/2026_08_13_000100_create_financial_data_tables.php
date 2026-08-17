<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('publisher')->nullable();
            $table->string('homepage_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_official')->default(false);
            $table->timestamps();
        });

        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('source_url')->nullable();
            $table->string('download_url')->nullable();
            $table->string('publication_title')->nullable();
            $table->date('publication_date')->nullable();
            $table->timestampTz('downloaded_at')->nullable();
            $table->string('license_name')->nullable();
            $table->string('license_url')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('dataset_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('expected_filename')->nullable();
            $table->jsonb('metadata');
            $table->timestamps();
        });

        Schema::create('accounting_scopes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_components', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('official_label')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('classifications', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('classification_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classification_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('classification_items')->restrictOnDelete();
            $table->string('code')->nullable();
            $table->string('official_label');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['classification_id', 'official_label']);
            $table->unique(['classification_id', 'slug']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->restrictOnDelete();
            $table->foreignId('dataset_file_id')->constrained()->restrictOnDelete();
            $table->string('filename');
            $table->char('checksum', 64);
            $table->string('status');
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedBigInteger('rows_read')->default(0);
            $table->unsignedBigInteger('rows_imported')->default(0);
            $table->unsignedBigInteger('rows_rejected')->default(0);
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['dataset_file_id', 'checksum']);
        });

        Schema::create('financial_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->restrictOnDelete();
            $table->foreignId('dataset_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->foreignId('accounting_scope_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_component_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('classification_item_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('measure')->nullable();
            $table->string('flow_type');
            $table->decimal('amount', 22, 2);
            $table->char('currency', 3)->default('EUR');
            $table->unsignedBigInteger('source_row_number')->nullable();
            $table->string('source_identifier')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique([
                'dataset_file_id', 'year', 'accounting_scope_id', 'budget_component_id',
                'classification_item_id', 'status', 'measure', 'flow_type',
            ], 'financial_observations_semantic_unique');
            $table->unique(['dataset_file_id', 'source_identifier'], 'financial_observations_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_observations');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('classification_items');
        Schema::dropIfExists('classifications');
        Schema::dropIfExists('budget_components');
        Schema::dropIfExists('accounting_scopes');
        Schema::dropIfExists('dataset_files');
        Schema::dropIfExists('datasets');
        Schema::dropIfExists('sources');
    }
};
