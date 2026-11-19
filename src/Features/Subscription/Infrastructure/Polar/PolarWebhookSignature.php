<?php

namespace Features\Subscription\Infrastructure\Polar;

use Illuminate\Http\Request;

/**
 * Verifies the signature of a Polar webhook (the Standard Webhooks scheme:
 * webhook-id, webhook-timestamp and webhook-signature headers).
 *
 * Polar signs with one of two keys, depending on when the endpoint's secret
 * was generated: the base64 decoded part after "whsec_" (Standard Webhooks,
 * secrets from 8 September 2026), or the bytes of the whole "whsec_..."
 * string (older secrets). Both are tried.
 */
final class PolarWebhookSignature
{
    /** Seconds a delivery may be older or newer than now, against replays. */
    public const int TOLERANCE = 300;

    public static function verify(Request $request, string $secret): bool
    {
        $id = (string) $request->header('webhook-id', '');
        $timestamp = (string) $request->header('webhook-timestamp', '');
        $signatures = (string) $request->header('webhook-signature', '');

        if ($id === '' || $signatures === '' || ! ctype_digit($timestamp)
            || abs(now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE) {
            return false;
        }

        $content = $id.'.'.$timestamp.'.'.$request->getContent();

        foreach (self::keys($secret) as $key) {
            $expected = base64_encode(hash_hmac('sha256', $content, $key, true));

            foreach (explode(' ', $signatures) as $signature) {
                [$version, $value] = array_pad(explode(',', $signature, 2), 2, '');

                if ($version === 'v1' && hash_equals($expected, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** The signature of a payload, as Polar sends it (for tests and local tools). */
    public static function sign(string $id, int $timestamp, string $payload, string $secret): string
    {
        $key = self::keys($secret)[0];

        return 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$payload, $key, true));
    }

    /**
     * @return list<string>
     */
    private static function keys(string $secret): array
    {
        $encoded = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $decoded = base64_decode($encoded, true);

        return array_values(array_unique(array_filter([$decoded ?: null, $secret])));
    }
}
