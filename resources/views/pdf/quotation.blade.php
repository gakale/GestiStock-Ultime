<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Devis {{ $quotation->quotation_number }}</title>
    <style>
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 12px; 
            color: #333;
            line-height: 1.5;
        }
        .container { 
            width: 100%; 
            margin: 0; 
            padding: 20px; 
            border: 1px solid #eee; 
        }
        .header h1 { 
            margin: 0; 
            font-size: 24px; 
            color: #2c3e50;
        }
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        .company-details {
            float: left;
            width: 45%;
            margin-bottom: 20px;
        }
        .client-details {
            float: right;
            width: 45%;
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        .document-details {
            clear: both;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .document-details h2 {
            margin-top: 0;
            color: #3498db;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        table th {
            background-color: #f2f2f2;
            text-align: left;
        }
        .totals {
            float: right;
            width: 35%;
        }
        .totals table {
            width: 100%;
        }
        .totals table td {
            border: none;
            padding: 5px;
        }
        .totals table tr:last-child td {
            border-top: 1px solid #ddd;
            font-weight: bold;
        }
        .notes {
            clear: both;
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #777;
        }
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
                <h3>Devis pour :</h3>
                <p><strong>{{ $quotation->client->getDisplayNameAttribute() }}</strong></p>
                <p>{{ $quotation->client->address_line1 }}</p>
                @if($quotation->client->address_line2)
                    <p>{{ $quotation->client->address_line2 }}</p>
                @endif
                <p>{{ $quotation->client->postal_code }} {{ $quotation->client->city }}</p>
                @if($quotation->client->vat_number)
                    <p>TVA: {{ $quotation->client->vat_number }}</p>
                @endif
            </div>
        </div>

        <div class="document-details">
            <h2>Devis N° : {{ $quotation->quotation_number }}</h2>
            <p><strong>Date du Devis :</strong> {{ $quotation->quotation_date->format('d/m/Y') }}</p>
            <p><strong>Valide jusqu'au :</strong> {{ $quotation->expiry_date->format('d/m/Y') }}</p>
            @if($quotation->user)
                <p><strong>Conseiller :</strong> {{ $quotation->user->name }}</p>
            @endif
            @if($quotation->reference)
                <p><strong>Référence :</strong> {{ $quotation->reference }}</p>
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
                @foreach($quotation->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->product?->name ?? $item->description }}
                        @if($item->product?->sku) <br><small>SKU: {{ $item->product->sku }}</small> @endif
                    </td>
                    <td style="text-align:right;">{{ number_format($item->quantity, 2, ',', ' ') }}{{ $item->transactionUnit ? ' ' . $item->transactionUnit->symbol : '' }}</td>
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
                        <td style="text-align:right;">{{ number_format($quotation->subtotal, 2, ',', ' ') }} €</td>
                    </tr>
                    {{-- Remise globale si applicable aux devis --}}
                    @if(isset($quotation->global_discount_amount) && $quotation->global_discount_amount > 0)
                    <tr>
                        <td>Remise Globale :</td>
                        <td style="text-align:right;">- {{ number_format($quotation->global_discount_amount, 2, ',', ' ') }} €</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Montant Taxes :</td>
                        <td style="text-align:right;">{{ number_format($quotation->taxes_amount, 2, ',', ' ') }} €</td>
                    </tr>
                    {{-- Frais de port si applicable aux devis --}}
                    @if(isset($quotation->shipping_charges) && $quotation->shipping_charges > 0)
                    <tr>
                        <td>Frais de port :</td>
                        <td style="text-align:right;">{{ number_format($quotation->shipping_charges, 2, ',', ' ') }} €</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="font-size: 1.2em;"><strong>Total TTC :</strong></td>
                        <td style="text-align:right; font-size: 1.2em;"><strong>{{ number_format($quotation->total_amount, 2, ',', ' ') }} €</strong></td>
                    </tr>
                </table>
            </div>
        </div>

        @if($quotation->terms_conditions)
        <div class="notes">
            <strong>Termes et Conditions :</strong><br>
            {!! nl2br(e($quotation->terms_conditions)) !!}
        </div>
        @endif

        <div class="footer">
            <p>Ce devis est valable jusqu'au {{ $quotation->expiry_date->format('d/m/Y') }}.</p>
            <p>Pour accepter ce devis, veuillez nous le retourner signé avec la mention "Bon pour accord".</p>
            
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
