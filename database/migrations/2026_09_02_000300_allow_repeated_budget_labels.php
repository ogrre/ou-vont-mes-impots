<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classification_items', function (Blueprint $table): void {
            $table->dropUnique('classification_items_classification_id_official_label_unique');
        });
    }

    public function down(): void
    {
        Schema::table('classification_items', function (Blueprint $table): void {
            $table->unique(['classification_id', 'official_label']);
        });
    }
};
