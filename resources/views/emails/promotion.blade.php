<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { background: #ffe100; padding: 40px 20px; text-align: center; }
        .content { padding: 40px; text-align: center; color: #1f2937; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #9ca3af; background: #f3f4f6; }
        .button { display: inline-block; padding: 16px 32px; background: #000; color: #fff; text-decoration: none; border-radius: 12px; font-weight: bold; margin-top: 20px; }
        h1 { margin: 0; font-size: 24px; color: #000; }
        p { line-height: 1.6; margin: 16px 0; }
        .badge { background: #fee2e2; color: #ef4444; padding: 4px 12px; border-radius: 9999px; font-size: 14px; font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 New Promotion Alert!</h1>
        </div>
        <div class="content">
            <span class="badge">{{ $promotion['discount_type'] === 'fixed' ? '₱' . $promotion['discount_value'] : $promotion['discount_value'] . '%' }} OFF</span>
            <h2>{{ $promotion['name'] }}</h2>
            <p>{{ $promotion['description'] }}</p>
            @if($promotion['min_amount'] > 0)
                <p style="font-size: 14px; color: #6b7280;">Valid for orders above ₱{{ number_format($promotion['min_amount'], 2) }}</p>
            @endif
            <a href="{{ config('app.frontend_url') }}" class="button">Shop Now</a>
        </div>
        <div class="footer">
            <p>You're receiving this because you're a valued customer of DSC Printing Services.</p>
            <p>Don't want to receive these emails? <a href="{{ config('app.frontend_url') }}/customer-settings">Update your preferences here</a>.</p>
        </div>
    </div>
</body>
</html>
