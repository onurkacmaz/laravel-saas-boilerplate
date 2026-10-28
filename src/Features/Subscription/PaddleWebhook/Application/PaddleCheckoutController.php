<?php

namespace Features\Subscription\PaddleWebhook\Application;

use Features\Subscription\Shared\Support\BillingUrls;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The default payment link set in Paddle: the page Paddle sends a checkout
 * to (with its transaction in ?_ptxn=). Paddle.js opens Paddle Checkout over
 * it, then sends the customer to the frontend's success page.
 */
class PaddleCheckoutController
{
    public function __invoke(Request $request): View
    {
        $transactionId = (string) $request->query('_ptxn', '');

        if (! preg_match('/^txn_[a-z0-9]+$/', $transactionId)) {
            $transactionId = '';
        }

        return view('billing::checkout', [
            'token' => (string) config('cashier.client_side_token'),
            'sandbox' => (bool) config('cashier.sandbox'),
            'successUrl' => BillingUrls::success($transactionId),
            'cancelUrl' => BillingUrls::cancel(),
        ]);
    }
}
