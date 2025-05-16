<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Facture {{ $invoice->invoice_number }}</title>
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
        .header, .footer {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #222;
        }
        .company-details, .client-details, .invoice-details {
            margin-bottom: 20px;
        }
        .company-details p, .client-details p, .invoice-details p {
            margin: 2px 0;
        }
        .company-details {
            float: left;
            width: 50%;
        }
        .client-details {
            float: right;
            width: 45%;
            text-align: right;
        }
        .invoice-details {
            clear: both;
            padding-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f8f8;
        }
        .totals {
            float: right;
            width: 40%;
        }
        .totals table {
            width: 100%;
        }
        .totals td:first-child {
            text-align: right;
            font-weight: bold;
            width: 60%;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .notes {
            margin-top: 30px;
            font-size: 10px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        /* Ajoutez ici plus de styles selon vos besoins */
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {{-- Vous pourriez afficher un logo ici --}}
            {{-- @if(tenant() && tenant()->logo_path) --}}
            {{-- <img src="{{ storage_path('app/public/' . tenant()->logo_path) }}" alt="Logo" style="max-height: 80px;"/> --}}
            {{-- @else --}}
            <h1>{{ tenant() ? tenant()->name : 'Votre Entreprise' }}</h1>
            {{-- @endif --}}
        </div>

        <div class="clearfix">
            <div class="company-details">
                <h3>{{ tenant() ? tenant()->name : 'Votre Entreprise' }}</h3>
                <p>{{-- Adresse de l'entreprise (à récupérer des settings du tenant) --}}</p>
                <p>{{-- Ville, Code Postal --}}</p>
                <p>{{-- Téléphone --}}</p>
                <p>{{-- Email --}}</p>
                <p>{{-- Numéro TVA/SIRET --}}</p>
            </div>
            <div class="client-details">
                <h3>Facturé à :</h3>
                <p><strong>{{ $invoice->client->getDisplayNameAttribute() }}</strong></p>
                <p>{{ $invoice->client->address_line1 }}</p>
                @if($invoice->client->address_line2) <p>{{ $invoice->client->address_line2 }}</p> @endif
                <p>{{ $invoice->client->postal_code }} {{ $invoice->client->city }}</p>
                <p>{{ $invoice->client->country }}</p>
                @if($invoice->client->phone_number) <p>Tél: {{ $invoice->client->phone_number }}</p> @endif
                @if($invoice->client->email) <p>Email: {{ $invoice->client->email }}</p> @endif
            </div>
        </div>

        <div class="invoice-details">
            <h2>Facture N° : {{ $invoice->invoice_number }}</h2>
            <p><strong>Date de facturation :</strong> {{ $invoice->invoice_date->format('d/m/Y') }}</p>
            <p><strong>Date d'échéance :</strong> {{ $invoice->due_date->format('d/m/Y') }}</p>
            @if($invoice->order_reference)
                <p><strong>Référence Commande Client :</strong> {{ $invoice->order_reference }}</p>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Qté</th>
                    <th>Prix Unit. HT</th>
                    <th>Remise (%)</th>
                    <th>Total HT</th>
                    <th>TVA (%)</th>
                    <th>Total TTC</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->product?->name ?? $item->description }}
                        @if($item->product?->sku) <br><small>SKU: {{ $item->product->sku }}</small> @endif
                    </td>
                    <td style="text-align:right;">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                    <td style="text-align:right;">{{ number_format($item->unit_price, 2, ',', ' ') }} €</td>
                    <td style="text-align:right;">{{ number_format($item->discount_percentage, 2, ',', ' ') }}%</td>
                    @php
                        $basePrice = $item->quantity * $item->unit_price;
                        $discountAmount = $basePrice * ($item->discount_percentage / 100);
                        $priceAfterDiscount = $basePrice - $discountAmount;
                    @endphp
                    <td style="text-align:right;">{{ number_format($priceAfterDiscount, 2, ',', ' ') }} €</td>
                    <td style="text-align:right;">{{ number_format($item->tax_rate, 2, ',', ' ') }}%</td>
                    <td style="text-align:right;">{{ number_format($item->line_total, 2, ',', ' ') }} €</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="clearfix">
            <div class="totals">
                <table>
                    <tr>
                        <td>Sous-total HT :</td>
                        <td style="text-align:right;">{{ number_format($invoice->subtotal, 2, ',', ' ') }} €</td>
                    </tr>
                    <tr>
                        <td>Remise Globale :</td>
                        <td style="text-align:right;">- {{ number_format($invoice->global_discount_amount, 2, ',', ' ') }} €</td>
                    </tr>
                    <tr>
                        <td>Montant Taxes :</td>
                        <td style="text-align:right;">{{ number_format($invoice->taxes_amount, 2, ',', ' ') }} €</td>
                    </tr>
                     @if($invoice->shipping_cost > 0)
                    <tr>
                        <td>Frais de port :</td>
                        <td style="text-align:right;">{{ number_format($invoice->shipping_cost, 2, ',', ' ') }} €</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="font-size: 1.2em;"><strong>Total TTC :</strong></td>
                        <td style="text-align:right; font-size: 1.2em;"><strong>{{ number_format($invoice->total_amount, 2, ',', ' ') }} €</strong></td>
                    </tr>
                    @if($invoice->amount_paid > 0)
                    <tr>
                        <td>Montant Payé :</td>
                        <td style="text-align:right;">- {{ number_format($invoice->amount_paid, 2, ',', ' ') }} €</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Solde Dû :</td>
                        <td style="text-align:right; font-weight: bold;">{{ number_format($invoice->total_amount - $invoice->amount_paid, 2, ',', ' ') }} €</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        @if($invoice->notes_to_client)
        <div class="notes">
            <strong>Notes :</strong><br>
            {!! nl2br(e($invoice->notes_to_client)) !!}
        </div>
        @endif

        <div class="footer">
            <p>Merci pour votre confiance.</p>
            <p>{{-- Conditions de paiement, informations bancaires (à récupérer des settings du tenant) --}}</p>
        </div>
    </div>
</body>
</html>
