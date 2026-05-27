<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $promotion->title ?? 'Special Promotion' }}</title>
    <style>
        body {
            font-family: 'Outfit', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f7f9fa;
            color: #1a202c;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f7f9fa;
            padding-bottom: 40px;
        }
        .main-table {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #edf2f7;
        }
        .banner {
            width: 100%;
            display: block;
        }
        .content {
            padding: 40px 30px;
            text-align: center;
        }
        .preheader {
            color: #718096;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .title {
            color: #1a202c;
            font-size: 28px;
            font-weight: 900;
            line-height: 1.2;
            margin: 0 0 16px 0;
        }
        .description {
            color: #4a5568;
            font-size: 16px;
            line-height: 1.6;
            margin: 0 0 24px 0;
        }
        .badge {
            display: inline-block;
            background: linear-gradient(135deg, #FFE100 0%, #FDE31E 100%);
            color: #000000;
            font-size: 24px;
            font-weight: 900;
            padding: 12px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(253, 227, 30, 0.3);
            margin-bottom: 24px;
        }
        .promo-code-container {
            background-color: #f7fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin: 0 auto 30px auto;
            max-width: 300px;
        }
        .promo-code-label {
            color: #718096;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .promo-code-value {
            font-family: 'Courier New', Courier, monospace;
            font-size: 22px;
            font-weight: 700;
            color: #2d3748;
            letter-spacing: 0.1em;
        }
        .btn-cta {
            display: inline-block;
            background-color: #000000;
            color: #ffffff !important;
            font-size: 16px;
            font-weight: 800;
            text-decoration: none;
            padding: 16px 36px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
        }
        .footer {
            padding: 30px;
            text-align: center;
            background-color: #1a202c;
            color: #a0aec0;
            font-size: 13px;
        }
        .footer a {
            color: #FFE100;
            text-decoration: none;
            font-weight: 600;
        }
        .footer-logo {
            font-size: 18px;
            font-weight: 900;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .expiry-text {
            color: #e53e3e;
            font-size: 13px;
            font-weight: 700;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Spacer -->
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td height="30"></td>
            </tr>
        </table>

        <!-- Main Body -->
        <table class="main-table" cellspacing="0" cellpadding="0">
            <!-- Banner Image -->
            @if(!empty($promotion->banner_image))
            <tr>
                <td>
                    <img class="banner" src="{{ url('storage/' . $promotion->banner_image) }}" alt="Special Offer Banner" width="600">
                </td>
            </tr>
            @endif

            <!-- Main Content -->
            <tr>
                <td class="content">
                    <div class="preheader">Exclusive Offer</div>
                    <h1 class="title">{{ $promotion->title ?? $promotion->name }}</h1>
                    
                    @if(!empty($promotion->description))
                        <p class="description">{{ $promotion->description }}</p>
                    @endif

                    <!-- Discount Badge -->
                    <div class="badge">
                        @if($promotion->discount_type === 'percentage')
                            {{ round($promotion->discount_value) }}% OFF
                        @elseif($promotion->discount_type === 'fixed')
                            ₱{{ number_format($promotion->discount_value, 2) }} OFF
                        @else
                            FREE SHIPPING
                        @endif
                    </div>

                    <!-- Promo Code Box -->
                    @if(!empty($promotion->promo_code))
                        <div class="promo-code-container">
                            <div class="promo-code-label">Use Code At Checkout</div>
                            <div class="promo-code-value">{{ $promotion->promo_code }}</div>
                        </div>
                    @endif

                    <!-- Call To Action -->
                    <div>
                        <a class="btn-cta" href="{{ url('/') }}" target="_blank">SHOP THE DEAL NOW</a>
                    </div>

                    @if(!empty($promotion->expiration_date))
                        <div class="expiry-text">
                            *Offer expires on {{ \Carbon\Carbon::parse($promotion->expiration_date)->format('F d, Y') }}
                        </div>
                    @endif
                </td>
            </tr>

            <!-- Premium Sleek Footer -->
            <tr>
                <td class="footer">
                    <div class="footer-logo">DSC STICKER</div>
                    <p>You received this email because you're a verified customer of DSC Sticker and opted in to receive promotional emails.</p>
                    <p style="margin-bottom: 0;">&copy; {{ date('Y') }} DSC Sticker. All rights reserved. | <a href="{{ url('/unsubscribe') }}">Unsubscribe</a></p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
