<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('slug')->after('name')->nullable();
            $table->unique(['warehouse_id', 'slug']);
        });
        
        // Mettre à jour les enregistrements existants avec un slug basé sur le nom
        DB::statement("UPDATE locations SET slug = LOWER(REPLACE(name, ' ', '-')) WHERE slug IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['warehouse_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
