<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ config('app.name') }}</title>
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; color: #52525b; }
        a { color: inherit; }
    </style>
</head>
<body>
    <p><a href="{{ $cancelUrl }}">{{ __('Back') }}</a></p>
    <script>
        @if ($sandbox)
            Paddle.Environment.set('sandbox');
        @endif
        Paddle.Initialize({
            token: @json($token),
            checkout: { settings: { successUrl: @json($successUrl) } },
            eventCallback: function (event) {
                if (event.name === 'checkout.closed') {
                    window.location.href = @json($cancelUrl);
                }
            },
        });
    </script>
</body>
</html>
