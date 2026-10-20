<?php

namespace Features\Webhooks\Shared\Support;

use Features\Webhooks\Shared\Rules\SafeWebhookUrl;

final class WebhookRules
{
    /**
     * Validation rules shared by the create and update requests.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'url' => [$required, 'string', 'url:http,https', 'max:500', new SafeWebhookUrl],
            'events' => [$required, 'array', 'min:1', 'max:100'],
            'events.*' => ['required', 'string', 'distinct', 'max:100', 'regex:/^(\*|[a-z0-9_]+(\.[a-z0-9_*]+)*)$/'],
            'secret' => ['sometimes', 'nullable', 'string', 'min:16', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'headers' => ['sometimes', 'nullable', 'array', 'max:20'],
            // Header names must be valid tokens and may not override transport or signature headers.
            'headers.*' => ['nullable', 'string', 'max:1000', 'not_regex:/[\r\n]/'],
            'timeout' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'retry_limit' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ];
    }

    /**
     * Header names reserved by the delivery mechanism.
     *
     * @return list<string>
     */
    public static function reservedHeaders(): array
    {
        return ['host', 'content-type', 'content-length', 'user-agent', 'x-webhook-signature', 'x-webhook-timestamp', 'x-webhook-event', 'x-webhook-delivery', 'transfer-encoding', 'connection'];
    }

    /**
     * Drop invalid or reserved custom headers.
     *
     * @param  array<string, mixed>|null  $headers
     * @return array<string, string>
     */
    public static function sanitizeHeaders(?array $headers): array
    {
        $clean = [];

        foreach ($headers ?? [] as $name => $value) {
            if (! is_string($name) || ! preg_match('/^[A-Za-z0-9-]{1,100}$/', $name)) {
                continue;
            }

            if (in_array(strtolower($name), self::reservedHeaders(), true) || ! is_scalar($value)) {
                continue;
            }

            $clean[$name] = str_replace(["\r", "\n"], '', (string) $value);
        }

        return $clean;
    }
}
