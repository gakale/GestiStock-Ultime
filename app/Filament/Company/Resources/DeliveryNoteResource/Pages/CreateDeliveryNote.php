<?php

namespace App\Filament\Company\Resources\DeliveryNoteResource\Pages;

use App\Filament\Company\Resources\DeliveryNoteResource;
use App\Models\SalesOrder; // Importer
use App\Models\DeliveryNoteItem; // Pour les types d'items
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr; // Pour Arr::except

class CreateDeliveryNote extends CreateRecord
{
    protected static string $resource = DeliveryNoteResource::class;

    public ?SalesOrder $salesOrder = null; // Propriété pour stocker le SalesOrder source

    public function mount(): void
    {
        // Vérifier si un sales_order_id est passé dans l'URL
        $salesOrderId = request()->query('sales_order_id');
        if ($salesOrderId) {
            $this->salesOrder = SalesOrder::with('items.product', 'client')->find($salesOrderId);
            if ($this->salesOrder) {
                // Pré-remplir le formulaire
                $this->form->fill($this->prepareInitialDataFromSalesOrder($this->salesOrder));
            }
        }
        parent::mount();
    }

    protected function prepareInitialDataFromSalesOrder(SalesOrder $so): array
    {
        $itemsData = [];
        foreach ($so->items as $soItem) {
            $quantityToShip = $soItem->quantity_ordered - $soItem->quantity_shipped;
            if ($quantityToShip > 0) {
                $itemsData[] = [
                    'product_id' => $soItem->product_id,
                    'sales_order_item_id' => $soItem->id, // Assurez-vous que ce champ est dans fillable de DeliveryNoteItem et dans le Repeater
                    'product_name' => $soItem->product_name,
                    'product_sku' => $soItem->product_sku,
                    'quantity_ordered' => $soItem->quantity_ordered, // Quantité totale du BC
                    'quantity_shipped' => $quantityToShip, // Quantité restante à livrer
                ];
            }
        }

        return [
            'client_id' => $so->client_id,
            'sales_order_id' => $so->id, // Champ caché ou désactivé dans le formulaire
            'delivery_date' => now()->toDateString(),
            'status' => 'draft',
            'shipping_address_line1' => $so->client?->billing_address_line1, // Ou une adresse de livraison spécifique du SO
            'shipping_address_line2' => $so->client?->billing_address_line2,
            'shipping_city' => $so->client?->billing_city,
            'shipping_postal_code' => $so->client?->billing_postal_code,
            'shipping_country' => $so->client?->billing_country,
            'items' => $itemsData,
        ];
    }

    // Optionnel: Mutate data avant création si besoin
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si sales_order_id vient de l'URL mais n'est pas un champ du formulaire visible,
        // il faut s'assurer qu'il est ajouté ici si le modèle DeliveryNote l'attend.
        if ($this->salesOrder && !isset($data['sales_order_id'])) {
            $data['sales_order_id'] = $this->salesOrder->id;
        }
        return $data;
    }
}
