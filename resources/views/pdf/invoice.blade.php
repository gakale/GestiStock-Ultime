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
            @if(!empty($company['logo_path']))
                @php
                    // Vérifier si le fichier existe
                    $logoPath = storage_path('app/public/' . $company['logo_path']);
                    $logoExists = file_exists($logoPath);
                @endphp
                
                @if($logoExists)
                    <img src="{{ $logoPath }}" alt="Logo" style="max-height: 80px; display: block; margin-bottom: 10px;"/>
                @else
                    <h1>{{ $company['name'] }}</h1>
                    <!-- Logo introuvable: {{ $company['logo_path'] }} -->
                @endif
            @else
                <h1>{{ $company['name'] }}</h1>
            @endif
        </div>

        <div class="clearfix">
            <div class="company-details">
                <h3>{{ $company['name'] }}</h3>
                @if(!empty($company['address_line1']))<p>{{ $company['address_line1'] }}</p>@endif
                @if(!empty($company['address_line2']))<p>{{ $company['address_line2'] }}</p>@endif
                <p>
                    @if(!empty($company['postal_code'])){{ $company['postal_code'] }}@endif
                    @if(!empty($company['city'])) {{ $company['city'] }}@endif
                </p>
                @if(!empty($company['country']))<p>{{ $company['country'] }}</p>@endif
                @if(!empty($company['phone']))<p>Tél : {{ $company['phone'] }}</p>@endif
                @if(!empty($company['email']))<p>Email : {{ $company['email'] }}</p>@endif
                @if(!empty($company['website']))<p>Web : {{ $company['website'] }}</p>@endif
                @if(!empty($company['vat_number']))<p>N° TVA : {{ $company['vat_number'] }}</p>@endif
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
            @if(!empty($company['payment_terms']))
                <p><strong>Conditions de paiement :</strong> {!! nl2br(e($company['payment_terms'])) !!}</p>
            @endif
            @if(!empty($company['bank_details']))
                <p><strong>Informations Bancaires :</strong><br>{!! nl2br(e($company['bank_details'])) !!}</p>
            @endif
            @if(!empty($company['footer_notes']))
                <hr style="margin-top:10px; margin-bottom:5px; border-top: 1px solid #eee;">
                <p style="font-size: 9px;">{!! nl2br(e($company['footer_notes'])) !!}</p>
            @endif
        </div>
    </div>
</body>
</html>
