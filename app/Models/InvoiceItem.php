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
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Traits\FormatsActivityLogEvents;

class InvoiceItem extends Model
{
    use HasFactory, HasUuids, LogsActivity, FormatsActivityLogEvents;

    public $timestamps = true;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'product_sku',
        'transaction_unit_id', // Unité de vente
        'description',
        'quantity',            // Quantité dans l'unité de transaction
        'stock_unit_quantity', // Quantité convertie dans l'unité de stock du produit
        'unit_price',          // Prix dans l'unité de transaction
        'discount_percentage',
        'tax_rate',
        'line_total',
        'sales_order_item_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'stock_unit_quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (InvoiceItem $item) {
            // 1. Calculer line_total (basé sur transaction_quantity et unit_price)
            $basePrice = (float)$item->quantity * (float)$item->unit_price;
            $discountAmount = $basePrice * ((float)$item->discount_percentage / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;
            $taxAmount = $priceAfterDiscount * ((float)$item->tax_rate / 100);
            $item->line_total = $priceAfterDiscount + $taxAmount;

            // 2. Calculer stock_unit_quantity (conversion)
            if ($item->product_id && $item->transaction_unit_id && !is_null($item->quantity) && $item->quantity != 0) {
                $product = Product::with('stockUnit')->find($item->product_id);
                $transactionUnit = UnitOfMeasure::find($item->transaction_unit_id);

                if ($product && $product->stockUnit && $transactionUnit) {
                    $conversionService = app(UnitConversionService::class);
                    try {
                        $item->stock_unit_quantity = $conversionService->convert(
                            $product,
                            (float)$item->quantity, // Quantité dans l'unité de transaction
                            $transactionUnit, // Unité source
                            $product->stockUnit // Unité cible
                        );
                        
                        Log::info('[InvoiceItem Saving] Conversion réussie', [
                            'product' => $product->name,
                            'transaction_quantity' => $item->quantity,
                            'transaction_unit' => $transactionUnit->symbol,
                            'stock_unit' => $product->stockUnit->symbol,
                            'stock_unit_quantity' => $item->stock_unit_quantity
                        ]);
                    } catch (\InvalidArgumentException $e) {
                        Log::error('[InvoiceItem Saving] Erreur de conversion', [
                            'product' => $product->name,
                            'error' => $e->getMessage(),
                            'transaction_quantity' => $item->quantity,
                            'transaction_unit' => $transactionUnit->symbol,
                            'stock_unit' => $product->stockUnit->symbol
                        ]);

                        // Mettre stock_unit_quantity à 0 (au lieu de null pour respecter la contrainte NOT NULL)
                        $item->stock_unit_quantity = 0;
                    }
                } else {
                    $missingData = [];
                    if (!$product) $missingData[] = 'produit';
                    if ($product && !$product->stockUnit) $missingData[] = 'unité de stock';
                    if (!$transactionUnit) $missingData[] = 'unité de transaction';

                    Log::warning('[InvoiceItem Saving] Données manquantes pour la conversion', [
                        'missing' => implode(', ', $missingData),
                        'product_id' => $item->product_id,
                        'transaction_unit_id' => $item->transaction_unit_id
                    ]);

                    // Si même unité ou pas d'unité de stock, utiliser la quantité de transaction
                    if ($product && $transactionUnit && 
                        (!$product->stock_unit_id || $product->stock_unit_id === $item->transaction_unit_id)) {
                        $item->stock_unit_quantity = (float) $item->quantity;
                    } else {
                        // Mettre à 0 pour respecter la contrainte NOT NULL
                        $item->stock_unit_quantity = 0;
                    }
                }
            } elseif ($item->quantity === 0) {
                $item->stock_unit_quantity = 0;
            } elseif (is_null($item->quantity)) {
                // Si quantity est null, on met stock_unit_quantity à 0 pour respecter la contrainte NOT NULL
                $item->stock_unit_quantity = 0;
            }
        });

        static::saved(function (InvoiceItem $item) {
            $item->invoice?->calculateTotals();
            // La décrémentation de stock sera gérée par un observer sur InvoiceItem (état "confirmé" de la facture)
        });

        static::deleted(function (InvoiceItem $item) {
            $item->invoice?->calculateTotals();
            // L'incrémentation de stock (si annulation) sera gérée par un observer
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }
    
    /**
     * Relation avec l'unité de transaction (unité dans laquelle le produit est vendu)
     */
    public function transactionUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'transaction_unit_id');
    }
    
    /**
     * Configuration des logs d'activité
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'invoice_id', 'product_id', 'product_name', 'product_sku',
                'transaction_unit_id', 'description', 'quantity', 'stock_unit_quantity', 'unit_price', 'discount_percentage',
                'tax_rate', 'line_total'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function(string $eventName) {
                $invoiceNumber = $this->invoice ? $this->invoice->invoice_number : 'inconnue';
                return "La ligne de facture pour '{$this->product_name}' (Facture n°{$invoiceNumber}) a été {$this->formatEventName($eventName)}.";
            })
            ->useLogName('invoice_item_activity');
    }
}