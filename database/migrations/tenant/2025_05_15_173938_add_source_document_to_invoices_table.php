<?php

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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('source_document_type')->nullable()->comment('Ex: App\\Models\\Quotation');
            $table->uuid('source_document_id')->nullable();
            $table->index(['source_document_type', 'source_document_id'], 'invoice_source_document_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoice_source_document_index');
            $table->dropColumn(['source_document_type', 'source_document_id']);
        });
    }
};
