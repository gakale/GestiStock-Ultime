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
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->foreignUuid('destination_location_id')->nullable()->after('transaction_quantity')
                  ->comment('Emplacement de rangement pour cet item reçu');
            $table->foreign('destination_location_id')
                  ->references('id')->on('locations')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['destination_location_id']);
            $table->dropColumn('destination_location_id');
        });
    }
};
