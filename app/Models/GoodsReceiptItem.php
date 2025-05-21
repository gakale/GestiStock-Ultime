<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\UnitConversionService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class GoodsReceiptItem extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = true;

    protected $fillable = [
        'goods_receipt_id',
        'product_id',
        'purchase_order_item_id',
        'quantity_ordered',
        'transaction_unit_id',
        'transaction_quantity',
        'destination_location_id', // NOUVEAU: Emplacement de rangement pour cet item reçu
        'quantity_received', // Quantité en unité de STOCK du produit
        'unit_price',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:2',
        'transaction_quantity' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    public function transactionUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'transaction_unit_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function (GoodsReceiptItem $item) {
            if ($item->product_id && $item->transaction_unit_id && !is_null($item->transaction_quantity) && $item->transaction_quantity != 0) {
                // Eager load product et ses unités pour éviter requêtes N+1
                $product = Product::with('stockUnit')->find($item->product_id);
                $transactionUnit = UnitOfMeasure::find($item->transaction_unit_id);

                if ($product && $product->stockUnit && $transactionUnit) {
                    $conversionService = app(UnitConversionService::class);
                    try {
                        $item->quantity_received = $conversionService->convert(
                            (float)$item->transaction_quantity,
                            $transactionUnit,
                            $product->stockUnit, // Unité de stock du produit
                            $product // Contexte du produit
                        );
                        
                        Log::info('[GoodsReceiptItem Saving] Conversion réussie', [
                            'product' => $product->name,
                            'transaction_quantity' => $item->transaction_quantity,
                            'transaction_unit' => $transactionUnit->symbol,
                            'stock_unit' => $product->stockUnit->symbol,
                            'quantity_received' => $item->quantity_received
                        ]);

                    } catch (\InvalidArgumentException $e) {
                        Log::error('[GoodsReceiptItem Saving] Erreur de conversion', [
                            'product' => $product->name,
                            'error' => $e->getMessage(),
                            'transaction_quantity' => $item->transaction_quantity,
                            'transaction_unit' => $transactionUnit->symbol,
                            'stock_unit' => $product->stockUnit->symbol
                        ]);

                        // Option 1: Bloquer la sauvegarde (désactivé pour permettre la sauvegarde)
                        // throw $e;
                        
                        // Option 2: Mettre quantity_received à 0 (au lieu de null pour respecter la contrainte NOT NULL)
                        $item->quantity_received = 0;
                        
                        // Logguer l'erreur pour suivi
                        Log::warning('[GoodsReceiptItem] Erreur de conversion, quantity_received mis à 0', [
                            'product_id' => $product->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    $missingData = [];
                    if (!$product) $missingData[] = 'produit';
                    if ($product && !$product->stockUnit) $missingData[] = 'unité de stock';
                    if (!$transactionUnit) $missingData[] = 'unité de transaction';

                    Log::warning('[GoodsReceiptItem Saving] Données manquantes pour la conversion', [
                        'missing' => implode(', ', $missingData),
                        'product_id' => $item->product_id,
                        'transaction_unit_id' => $item->transaction_unit_id
                    ]);

                    // Si même unité ou pas d'unité de stock, utiliser la quantité de transaction
                    if ($product && $transactionUnit && 
                        (!$product->stock_unit_id || $product->stock_unit_id === $item->transaction_unit_id)) {
                        $item->quantity_received = (float) $item->transaction_quantity;
                    } else {
                        throw new \InvalidArgumentException(
                            'Impossible de convertir la quantité : ' . implode(' et ', $missingData) . ' manquant(s)'
                        );
                    }
                }
            } elseif ($item->transaction_quantity === 0) {
                $item->quantity_received = 0;
            } elseif (is_null($item->transaction_quantity)) {
                // Si transaction_quantity est null, on met quantity_received à 0 pour respecter la contrainte NOT NULL
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
    
    /**
     * Relation vers l'emplacement de destination où l'article sera stocké
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }
}