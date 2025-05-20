<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            // Ajouter la colonne transaction_unit_id avec une clé étrangère vers unit_of_measures
            $table->foreignUuid('transaction_unit_id')->nullable()->after('tax_rate')
                  ->constrained('unit_of_measures')->nullOnDelete();
            
            // Modifier le type de la colonne quantity pour supporter les décimales
            $table->decimal('quantity', 15, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            // Supprimer la clé étrangère et la colonne
            $table->dropForeign(['transaction_unit_id']);
            $table->dropColumn('transaction_unit_id');
            
            // Remettre la colonne quantity en integer si nécessaire
            $table->integer('quantity')->change();
        });
    }
};
