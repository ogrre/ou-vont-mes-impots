<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datasets', function (Blueprint $table): void {
            $table->string('accounting_system')->nullable()->after('description');
            $table->string('scope')->nullable()->after('accounting_system');
            $table->string('frequency')->nullable()->after('scope');
            $table->string('unit')->nullable()->after('frequency');
            $table->unsignedSmallInteger('first_year')->nullable()->after('year');
            $table->unsignedSmallInteger('last_year')->nullable()->after('first_year');
        });

        Schema::table('accounting_scopes', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('accounting_scopes')->restrictOnDelete();
            $table->string('scope_type')->nullable()->after('description');
        });

        Schema::table('financial_observations', function (Blueprint $table): void {
            $table->string('measurement_type')->nullable()->after('status');
            $table->string('accounting_basis')->nullable()->after('measurement_type');
            $table->string('budget_stage')->nullable()->after('accounting_basis');
            $table->string('ae_cp')->nullable()->after('budget_stage');
            $table->boolean('is_consolidated')->nullable()->after('ae_cp');
            $table->foreignId('institution_scope_id')->nullable()->after('accounting_scope_id')->constrained('accounting_scopes')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->after('classification_item_id')->constrained('classification_items')->restrictOnDelete();
            $table->index(['year', 'measurement_type']);
            $table->index(['dataset_id', 'year']);
            $table->index(['accounting_scope_id', 'year']);
            $table->index(['institution_scope_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_observations', function (Blueprint $table): void {
            $table->dropIndex(['year', 'measurement_type']);
            $table->dropIndex(['dataset_id', 'year']);
            $table->dropIndex(['accounting_scope_id', 'year']);
            $table->dropIndex(['institution_scope_id', 'year']);
            $table->dropConstrainedForeignId('institution_scope_id');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['measurement_type', 'accounting_basis', 'budget_stage', 'ae_cp', 'is_consolidated']);
        });

        Schema::table('accounting_scopes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('scope_type');
        });

        Schema::table('datasets', function (Blueprint $table): void {
            $table->dropColumn(['accounting_system', 'scope', 'frequency', 'unit', 'first_year', 'last_year']);
        });
    }
};
