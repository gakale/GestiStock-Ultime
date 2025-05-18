<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\UnitConversionService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class CreditNoteItem extends Model
{
    use HasFactory, HasUuids;
    public $timestamps = true;

    protected $fillable = [
        'credit_note_id', 'product_id', 'invoice_item_id',
        'product_name', 'product_sku', 'transaction_unit_id', 'description',
        'quantity', 'unit_price', 'discount_percentage', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',    // Changé de integer à decimal pour plus de flexibilité
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($item) { // Calcul du total de la ligne
            $basePrice = $item->quantity * $item->unit_price;
            $discountAmount = $basePrice * ($item->discount_percentage / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;
            $taxAmount = $priceAfterDiscount * ($item->tax_rate / 100);
            $item->line_total = $priceAfterDiscount + $taxAmount;
        });
        // L'observer CreditNoteItemObserver gérera la mise à jour du stock
        // et les totaux de CreditNote.
        static::saved(fn ($item) => $item->creditNote?->calculateTotals());
        static::deleted(fn ($item) => $item->creditNote?->calculateTotals());
    }

    public function creditNote(): BelongsTo { return $this->belongsTo(CreditNote::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function invoiceItem(): BelongsTo { return $this->belongsTo(InvoiceItem::class); }
    
    /**
     * Relation avec l'unité de transaction (unité dans laquelle le produit est retourné)
     */
    public function transactionUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'transaction_unit_id');
    }
}