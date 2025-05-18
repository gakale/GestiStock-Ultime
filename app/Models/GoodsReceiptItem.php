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

class GoodsReceiptItem extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = true; // Activer les timestamps si besoin

    protected $fillable = [
        'goods_receipt_id',
        'product_id',
        'purchase_order_item_id',
        'quantity_ordered',
        'transaction_unit_id',
        'transaction_quantity',
        'quantity_received',
        'unit_price',
        // 'batch_number',
        // 'expiry_date',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:2',
        'transaction_quantity' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_price' => 'decimal:2',
        // 'expiry_date' => 'date',
    ];

    // Relation avec l'unité de transaction
    public function transactionUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'transaction_unit_id');
    }
    
    // Observer pour gérer la mise à jour du stock (voir Étape 4)
    
    // Méthode pour convertir la quantité de transaction en quantité de stock
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if ($item->product_id && $item->transaction_unit_id && !is_null($item->transaction_quantity)) {
                $product = $item->product; // Eager load si possible avant
                $transactionUnit = UnitOfMeasure::find($item->transaction_unit_id); // Eager load si possible

                if ($product && $product->stockUnit && $transactionUnit) {
                    try {
                        // Utiliser le service de conversion d'unités
                        $conversionService = App::make(UnitConversionService::class);
                        $item->quantity_received = $conversionService->convert(
                            $product,
                            (float)$item->transaction_quantity,
                            $transactionUnit,
                            $product->stockUnit
                        );
                    } catch (\Exception $e) {
                        // Journaliser l'erreur
                        Log::error('Erreur de conversion d\'unité', [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'transaction_unit_id' => $item->transaction_unit_id,
                            'transaction_unit_name' => $transactionUnit->name,
                            'stock_unit_id' => $product->stock_unit_id,
                            'stock_unit_name' => $product->stockUnit->name,
                            'transaction_quantity' => $item->transaction_quantity,
                            'error' => $e->getMessage()
                        ]);
                        
                        // Relancer l'exception pour que l'utilisateur soit informé
                        throw $e;
                    }
                } else {
                    // Si pas de produit, pas d'unité de stock ou pas d'unité de transaction, pas de conversion.
                    $item->quantity_received = $item->transaction_quantity;
                    
                    // Journaliser l'avertissement
                    if ($product && !$product->stock_unit_id) {
                        Log::warning('Conversion impossible : pas d\'unité de stock définie pour le produit', [
                            'product_id' => $product->id,
                            'product_name' => $product->name
                        ]);
                    }
                }
            } elseif (is_null($item->transaction_quantity)) {
                // Si transaction_quantity est NULL, utiliser une valeur par défaut de 0
                // pour éviter la violation de contrainte NOT NULL
                $item->quantity_received = 0;
            }
        });
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}