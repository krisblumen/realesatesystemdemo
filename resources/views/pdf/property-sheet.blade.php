<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ficha - {{ $property->title }}</title>
    <style>
        @page {
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1f2448;
            margin: 0;
            padding: 0;
        }

        .banner {
            position: relative;
            width: 100%;
            height: 400px;
            background-image: url('{{ public_path('images/assets/pdfs_top_background.png') }}');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: left top;
        }

        .banner .logo {
            position: absolute;
            top: 20px;
            left: 2cm;
            height: 44px;
            width: auto;
        }

        .banner .cover {
            position: absolute;
            top: 90px;
            left: 2cm;
            max-width: 50%;
            max-height: 280px;
        }

        .content {
            padding: 24px 2cm 0 2cm;
        }

        .badges {
            margin-bottom: 6px;
        }

        .badge {
            display: inline-block;
            background-color: #f49500;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 3px;
            margin-right: 6px;
        }

        .badge.outline {
            background-color: #ffffff;
            color: #1f2448;
            border: 1px solid #1f2448;
        }

        h1.title {
            font-size: 22px;
            color: #1f2448;
            margin: 8px 0 4px 0;
        }

        .location {
            font-size: 12px;
            color: #555555;
            margin-bottom: 10px;
        }

        .price {
            font-size: 26px;
            color: #f49500;
            font-weight: bold;
            margin: 6px 0 16px 0;
        }

        .divider {
            border-top: 1px solid #e5e5e5;
            margin: 14px 0;
        }

        .facts-table {
            width: 100%;
            margin-bottom: 6px;
        }

        .facts-table td {
            width: 20%;
            text-align: center;
            padding: 10px 4px;
            background-color: #f7f7f9;
        }

        .facts-table .fact-value {
            font-size: 16px;
            font-weight: bold;
            color: #1f2448;
            display: block;
        }

        .facts-table .fact-label {
            font-size: 9px;
            color: #777777;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1f2448;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 2px solid #f49500;
            display: inline-block;
            padding-bottom: 2px;
        }

        .description {
            font-size: 11px;
            line-height: 1.5;
            color: #333333;
            margin-bottom: 14px;
        }

        .features-list {
            font-size: 11px;
            color: #333333;
            margin-bottom: 14px;
        }

        .features-list span.feature-chip {
            display: inline-block;
            background-color: #eef0f7;
            color: #1f2448;
            padding: 4px 9px;
            border-radius: 3px;
            margin: 0 6px 6px 0;
        }

        .agent-card {
            background-color: #1f2448;
            color: #ffffff;
            padding: 14px 18px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .agent-card .agent-label {
            font-size: 9px;
            color: #f49500;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .agent-card .agent-name {
            font-size: 15px;
            font-weight: bold;
        }

        .agent-card .agent-contact {
            font-size: 11px;
            color: #ffffff;
        }

        .footer {
            background-color: #1f2448;
            color: #ffffff;
            text-align: center;
            font-size: 8px;
            padding: 10px 36px;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
        }
    </style>
</head>
<body>
    <div class="banner">
        <img class="logo" src="{{ public_path('images/brand/logo-on-dark.png') }}" alt="New Hauz">

        @if ($property->hasCoverImage())
            <img class="cover" src="{{ $property->getFirstMedia('cover')?->getPath('web') }}" alt="{{ $property->title }}">
        @endif
    </div>

    <div class="content">
        <div class="badges">
            <span class="badge">{{ $property->operation_type->label() }}</span>
            <span class="badge outline">{{ $property->property_type->label() }}</span>
        </div>

        <h1 class="title">{{ $property->title }}</h1>

        <div class="location">
            {{ $property->zone?->name }}@if($property->colonia), {{ $property->colonia }}@endif
        </div>

        <div class="price">{{ $property->priceLabel() }}</div>

        <table class="facts-table">
            <tr>
                <td>
                    <span class="fact-value">{{ $property->bedrooms ?? '-' }}</span>
                    <span class="fact-label">Rec&aacute;maras</span>
                </td>
                <td>
                    <span class="fact-value">{{ $property->bathrooms ?? '-' }}</span>
                    <span class="fact-label">Ba&ntilde;os</span>
                </td>
                <td>
                    <span class="fact-value">{{ $property->parking_spaces ?? '-' }}</span>
                    <span class="fact-label">Estacionamiento</span>
                </td>
                <td>
                    <span class="fact-value">{{ $property->land_area ? (int) $property->land_area.' m²' : '-' }}</span>
                    <span class="fact-label">Terreno</span>
                </td>
                <td>
                    <span class="fact-value">{{ $property->construction_area ? (int) $property->construction_area.' m²' : '-' }}</span>
                    <span class="fact-label">Construcci&oacute;n</span>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        @if ($property->description)
            <div class="section-title">Descripci&oacute;n</div>
            <p class="description">{{ $property->description }}</p>
        @endif

        @if ($property->features->isNotEmpty())
            <div class="section-title">Caracter&iacute;sticas</div>
            <div class="features-list">
                @foreach ($property->features as $feature)
                    <span class="feature-chip">{{ $feature->name }}</span>
                @endforeach
            </div>
        @endif

        @if ($property->agent)
            <div class="agent-card">
                <div class="agent-label">Tu asesor</div>
                <div class="agent-name">{{ $property->agent->name }}</div>
                <div class="agent-contact">
                    @if ($property->agent->phone)
                        Tel: {{ $property->agent->phone }}
                    @endif
                    @if ($property->agent->whatsapp)
                        &nbsp;&middot;&nbsp; WhatsApp: {{ $property->agent->whatsapp }}
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="footer">
        New Hauz Bienes Ra&iacute;ces &middot; www.newhauz.com.mx &middot; Informaci&oacute;n sujeta a cambios sin previo aviso, no constituye una oferta.
    </div>
</body>
</html>
