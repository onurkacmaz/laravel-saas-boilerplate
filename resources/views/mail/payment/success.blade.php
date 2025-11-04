@extends('mail.layout')

@section('content')
    <h2>Payment Successful ✓</h2>

    <p>Hello {{ $user->name }},</p>

    <p>We've successfully processed your payment for {{ $organization->name }}.</p>

    <div class="alert alert-success">
        <strong>Payment Details:</strong><br>
        Amount: ${{ number_format($amount / 100, 2) }} {{ strtoupper($currency) }}<br>
        Date: {{ now()->format('M d, Y H:i:s') }}<br>
        Organization: {{ $organization->name }}
    </div>

    <p>Your subscription is now active and you can continue enjoying all the features of your plan.</p>

    <p style="text-align: center;">
        <a href="{{ config('app.frontend_url') }}/settings/billing" class="button">View Billing Details</a>
    </p>

    <p>Thank you for your business!</p>
@endsection
