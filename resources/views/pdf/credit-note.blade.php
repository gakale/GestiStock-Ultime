<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Avoir {{ $creditNote->credit_note_number }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif; /* Choisir une police compatible PDF */
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            margin: 20px;
            padding: 20px;
            border: 1px solid #eee;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .company-details, .client-details {
            width: 45%;
        }
        .title {
            text-align: center;
            margin: 20px 0;
            font-size: 24px;
            color: #2c3e50;
        }
        .invoice-details {
            margin: 20px 0;
            border: 1px solid #eee;
            padding: 10px;
            background-color: #f9f9f9;
        }
        .invoice-details table {
            width: 100%;
        }
        .invoice-details th {
            text-align: left;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f2f2f2;
        }
        .totals {
            width: 40%;
            margin-left: auto;
            margin-top: 20px;
        }
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals th, .totals td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        .totals th {
            background-color: #f2f2f2;
            text-align: left;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #777;
        }
        .notes {
            margin-top: 30px;
            padding: 10px;
            border: 1px solid #eee;
            background-color: #f9f9f9;
        }
        .reason {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #eee;
            background-color: #f9f9f9;
            color: #e74c3c;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-draft {
            background-color: #95a5a6;
            color: white;
        }
        .badge-issued {
            background-color: #3498db;
            color: white;
        }
        .badge-applied {
            background-color: #2ecc71;
            color: white;
        }
        .badge-voided {
            background-color: #e74c3c;
            color: white;
        }
        .related-invoice {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #3498db;
            background-color: #ebf5fb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-details">
                <h2>{{ $company['name'] }}</h2>
                <p>{{ $company['address'] }}</p>
                <p>{{ $company['city'] }}</p>
                <p>Tél: {{ $company['phone'] }}</p>
                <p>Email: {{ $company['email'] }}</p>
                <p>N° TVA: {{ $company['vat_number'] }}</p>
            </div>
            <div class="client-details">
                <h3>Client</h3>
                <p><strong>{{ $creditNote->client->company_name }}</strong></p>
                <p>{{ $creditNote->client->address }}</p>
                <p>{{ $creditNote->client->postal_code }} {{ $creditNote->client->city }}</p>
                <p>{{ $creditNote->client->country }}</p>
                <p>N° TVA: {{ $creditNote->client->vat_number }}</p>
            </div>
        </div>

        <div class="title">
            AVOIR N° {{ $creditNote->credit_note_number }}
            <span class="badge badge-{{ $creditNote->status }}">
                @switch($creditNote->status)
                    @case('draft')
                        Brouillon
                        @break
                    @case('issued')
                        Émis
                        @break
                    @case('applied')
                        Appliqué
                        @break
                    @case('voided')
                        Annulé
                        @break
                    @default
                        {{ $creditNote->status }}
                @endswitch
            </span>
        </div>

        <div class="invoice-details">
            <table>
                <tr>
                    <th>Date d'avoir:</th>
                    <td>{{ \Carbon\Carbon::parse($creditNote->credit_note_date)->format('d/m/Y') }}</td>
                    <th>Motif:</th>
                    <td>
                        @switch($creditNote->reason)
                            @case('returned_goods')
                                Retour de marchandise
                                @break
                            @case('invoice_error')
                                Erreur de facturation
                                @break
                            @case('commercial_gesture')
                                Geste commercial
                                @break
                            @case('other')
                                Autre
                                @break
                            @default
                                {{ $creditNote->reason }}
                        @endswitch
                    </td>
                </tr>
            </table>
        </div>

        @if($creditNote->invoice)
        <div class="related-invoice">
            <strong>Facture d'origine:</strong> {{ $creditNote->invoice->invoice_number }} du {{ \Carbon\Carbon::parse($creditNote->invoice->invoice_date)->format('d/m/Y') }}
        </div>
        @endif

        <div class="reason">
            <strong>Motif de l'avoir:</strong> 
            @switch($creditNote->reason)
                @case('returned_goods')
                    Retour de marchandise
                    @break
                @case('invoice_error')
                    Erreur de facturation
                    @break
                @case('commercial_gesture')
                    Geste commercial
                    @break
                @case('other')
                    Autre
                    @break
                @default
                    {{ $creditNote->reason }}
            @endswitch
            @if($creditNote->restock_items)
                <br><strong>Retour en stock:</strong> Oui
            @endif
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Description</th>
                    <th>Quantité</th>
                    <th>Unité</th>
                    <th>Prix unitaire HT</th>
                    <th>Remise</th>
                    <th>TVA</th>
                    <th>Total TTC</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subtotal = 0;
                    $taxTotal = 0;
                    $grandTotal = 0;
                @endphp
                
                @foreach($creditNote->items as $item)
                    @php
                        $basePrice = $item->quantity * $item->unit_price;
                        $discountAmount = $basePrice * ($item->discount_percentage / 100);
                        $priceAfterDiscount = $basePrice - $discountAmount;
                        $taxAmount = $priceAfterDiscount * ($item->tax_rate / 100);
                        $lineTotal = $priceAfterDiscount + $taxAmount;
                        
                        $subtotal += $priceAfterDiscount;
                        $taxTotal += $taxAmount;
                        $grandTotal += $lineTotal;
                    @endphp
                    <tr>
                        <td>{{ $item->product->name ?? 'Produit supprimé' }}</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ number_format($item->quantity, 2) }}</td>
                        <td>{{ $item->transactionUnit->symbol ?? '' }}</td>
                        <td>{{ number_format($item->unit_price, 2) }} €</td>
                        <td>{{ number_format($item->discount_percentage, 2) }} %</td>
                        <td>{{ number_format($item->tax_rate, 2) }} %</td>
                        <td>{{ number_format($item->line_total, 2) }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <th>Total HT:</th>
                    <td>{{ number_format($subtotal, 2) }} €</td>
                </tr>
                <tr>
                    <th>Total TVA:</th>
                    <td>{{ number_format($taxTotal, 2) }} €</td>
                </tr>
                <tr>
                    <th>Total TTC:</th>
                    <td><strong>{{ number_format($grandTotal, 2) }} €</strong></td>
                </tr>
            </table>
        </div>

        @if($creditNote->notes)
        <div class="notes">
            <h3>Notes</h3>
            <p>{{ $creditNote->notes }}</p>
        </div>
        @endif

        <div class="footer">
            <p>{{ $company['name'] }} - {{ $company['address'] }}, {{ $company['city'] }} - N° TVA: {{ $company['vat_number'] }}</p>
        </div>
    </div>
</body>
</html>
