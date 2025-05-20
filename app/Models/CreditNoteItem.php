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
        'stock_unit_quantity', // Ajout de stock_unit_quantity
    ];

    protected $casts = [
        'quantity' => 'decimal:2',    // Changé de integer à decimal pour plus de flexibilité
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
        'stock_unit_quantity' => 'decimal:2', // Ajout du cast pour stock_unit_quantity
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($item) { 
            // S'assurer que le produit et l'unité de transaction sont chargés si possible
            if ($item->product_id && !$item->relationLoaded('product')) {
                $item->load('product.stockUnit'); // Charger le produit et son unité de stock
            }
            if ($item->transaction_unit_id && !$item->relationLoaded('transactionUnit')) {
                $item->load('transactionUnit'); // Charger l'unité de transaction
            }

            // Calcul du total de la ligne
            // Convertir explicitement en float pour éviter les problèmes de type
            $quantity = (float) $item->quantity;
            $unitPrice = (float) $item->unit_price;
            $discountPercentage = (float) $item->discount_percentage;
            $taxRate = (float) $item->tax_rate;
            
            $basePrice = $quantity * $unitPrice;
            $discountAmount = $basePrice * ($discountPercentage / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;
            $taxAmount = $priceAfterDiscount * ($taxRate / 100);
            $item->line_total = round($priceAfterDiscount + $taxAmount, 2);

            // Calcul de stock_unit_quantity
            if ($item->product && $item->quantity && $item->transactionUnit && $item->product->stockUnit) {
                $conversionService = App::make(UnitConversionService::class);
                try {
                    // Appel correct de la méthode convert() avec l'ordre des paramètres approprié
                    $item->stock_unit_quantity = $conversionService->convert(
                        $item->product, // Premier paramètre : le produit
                        (float) $item->quantity, // Deuxième paramètre : la quantité
                        $item->transactionUnit, // Troisième paramètre : l'unité source
                        $item->product->stockUnit // Quatrième paramètre : l'unité cible
                    );
                } catch (\Exception $e) {
                    Log::error("Erreur de conversion d'unité pour CreditNoteItem ID {$item->id}: " . $e->getMessage());
                    // Gérer l'erreur, par exemple en ne définissant pas stock_unit_quantity ou en lançant une exception
                    // Pour l'instant, on logue et on continue, ce qui pourrait laisser stock_unit_quantity à null ou à sa valeur précédente
                }
            } elseif ($item->product && $item->quantity && $item->transaction_unit_id === null && $item->product->stock_unit_id === null) {
                // Si aucune unité de transaction et aucune unité de stock pour le produit, on assume que la quantité est directe.
                // Ou si l'unité de transaction est la même que l'unité de stock (ou les deux sont null, signifiant 'unité de base')
                 $item->stock_unit_quantity = (float) $item->quantity;
            } elseif ($item->product && $item->quantity && $item->transactionUnit && $item->product->stockUnit && $item->transactionUnit->id === $item->product->stockUnit->id) {
                 $item->stock_unit_quantity = (float) $item->quantity;
            }
            // Si les conditions ne sont pas remplies pour la conversion (ex: produit non trouvé, unités manquantes), 
            // stock_unit_quantity ne sera pas calculé ou mis à jour ici.
            // L'observateur pourrait avoir une logique supplémentaire pour cela.
        });
        // Note: L'observer CreditNoteItemObserver gérera la mise à jour du stock
        // et les totaux de CreditNote. Nous avons supprimé les appels ici pour éviter la duplication.
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