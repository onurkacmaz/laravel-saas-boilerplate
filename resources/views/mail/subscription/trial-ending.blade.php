@extends('mail.layout')

@section('content')
    <h2>Your Trial is Ending Soon ⏰</h2>

    <p>Hello {{ $user->name }},</p>

    <p>This is a friendly reminder that your trial period for the <strong>{{ $plan->name }}</strong> plan will end in {{ $daysRemaining }} days.</p>

    <div class="alert alert-warning">
        <strong>Trial Details:</strong><br>
        Plan: {{ $plan->name }}<br>
        Trial Ends: {{ $trialEndsAt->format('M d, Y') }}<br>
        Days Remaining: {{ $daysRemaining }}
    </div>

    <p><strong>What happens when the trial ends?</strong></p>

    <ul>
        <li>Your payment method will be charged automatically</li>
        <li>You'll continue to have access to all plan features</li>
        <li>You can cancel anytime before the trial ends</li>
    </ul>

    <p style="text-align: center;">
        <a href="{{ config('app.frontend_url') }}/settings/subscription" class="button">Manage Subscription</a>
    </p>

    <p>Thank you for trying {{ config('app.name') }}!</p>
@endsection
