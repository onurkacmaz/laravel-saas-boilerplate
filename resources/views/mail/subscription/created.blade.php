@extends('mail.layout')

@section('content')
    <h2>Welcome to {{ $plan->name }}! 🎉</h2>

    <p>Hello {{ $user->name }},</p>

    <p>Congratulations! Your subscription to the <strong>{{ $plan->name }}</strong> plan has been activated successfully.</p>

    <div class="alert alert-success">
        <strong>Subscription Details:</strong><br>
        Plan: {{ $plan->name }}<br>
        Price: ${{ number_format($plan->price / 100, 2) }} / {{ $plan->interval }}<br>
        @if($subscription->onTrial())
            Trial Ends: {{ $subscription->trial_ends_at->format('M d, Y') }}<br>
        @endif
        Status: Active
    </div>

    <p>You now have access to all the features included in your plan:</p>

    <ul>
        @if($plan->features)
            @foreach($plan->features as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        @endif
    </ul>

    <p style="text-align: center;">
        <a href="{{ config('app.frontend_url') }}/dashboard" class="button">Go to Dashboard</a>
    </p>

    <p>Thank you for choosing {{ config('app.name') }}!</p>
@endsection
