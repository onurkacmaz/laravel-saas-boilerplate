@extends('mail.layout')

@section('content')
    <h2>Payment Failed ⚠️</h2>

    <p>Hello {{ $user->name }},</p>

    <p>We encountered an issue while processing your payment for {{ $organization->name }}.</p>

    <div class="alert alert-danger">
        <strong>Payment Details:</strong><br>
        Amount: ${{ number_format($amount / 100, 2) }} {{ strtoupper($currency) }}<br>
        Organization: {{ $organization->name }}<br>
        @if(isset($failureMessage))
            Reason: {{ $failureMessage }}
        @endif
    </div>

    <p><strong>What happens next?</strong></p>

    <ul>
        <li>Please update your payment method to avoid service interruption</li>
        <li>We'll automatically retry the payment in the next few days</li>
        <li>If payment fails multiple times, your subscription may be suspended</li>
    </ul>

    <p style="text-align: center;">
        <a href="{{ config('app.frontend_url') }}/settings/billing" class="button">Update Payment Method</a>
    </p>

    <p>If you need assistance, please don't hesitate to contact our support team.</p>
@endsection
