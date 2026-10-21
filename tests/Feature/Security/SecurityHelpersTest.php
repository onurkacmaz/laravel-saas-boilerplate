<?php

use Features\Webhooks\Shared\Rules\SafeWebhookUrl;
use Features\Webhooks\Shared\Support\WebhookUrlGuard;
use Illuminate\Support\Facades\Validator;
use Infrastructure\Security\Middleware\IpWhitelist;
use Infrastructure\Security\Rules\IpOrCidr;
use Infrastructure\Security\Rules\SafeUrl;
use Infrastructure\Security\Support\UrlGuard;

it('refuses private targets unless security.allow_private_urls is on', function (): void {
    expect(UrlGuard::isAllowed('http://10.0.0.1/health'))->toBeFalse()
        ->and(UrlGuard::isAllowed('https://8.8.8.8/health'))->toBeTrue();

    config(['security.allow_private_urls' => true]);

    expect(UrlGuard::isAllowed('http://10.0.0.1/health'))->toBeTrue()
        // Embedded credentials and other schemes stay refused.
        ->and(UrlGuard::isAllowed('http://user:secret@10.0.0.1/health'))->toBeFalse()
        ->and(UrlGuard::isAllowed('ftp://10.0.0.1/health'))->toBeFalse();
});

it('still honors the former webhooks.allow_private_urls setting', function (): void {
    config(['webhooks.allow_private_urls' => true]);

    expect(UrlGuard::isAllowed('http://10.0.0.1/health'))->toBeTrue();
});

it('validates safe URLs with the SafeUrl rule and its former name', function (string $rule): void {
    $validator = fn (string $url) => Validator::make(['url' => $url], ['url' => [new $rule]]);

    expect($validator('https://8.8.8.8/hook')->passes())->toBeTrue()
        ->and($validator('http://169.254.169.254/latest')->errors()->first('url'))->toBe('The url must be a public http(s) URL.');
})->with([SafeUrl::class, SafeWebhookUrl::class]);

it('keeps the former webhook URL guard working', function (): void {
    expect(WebhookUrlGuard::isAllowed('http://127.0.0.1/hook'))->toBeFalse()
        ->and(WebhookUrlGuard::isAllowed('https://8.8.8.8/hook'))->toBeTrue()
        ->and(WebhookUrlGuard::isPublicIp('192.168.1.1'))->toBeFalse();
});

it('validates IP addresses and CIDR ranges', function (string $value, bool $valid): void {
    expect(IpOrCidr::isValid($value))->toBe($valid)
        ->and(Validator::make(['ip' => $value], ['ip' => [new IpOrCidr]])->passes())->toBe($valid);
})->with([
    ['203.0.113.7', true],
    ['10.0.0.0/8', true],
    ['2001:db8::/32', true],
    ['2001:db8::1', true],
    ['10.0.0.0/33', false],
    ['2001:db8::/129', false],
    ['10.0.0.0/x', false],
    ['example.com', false],
]);

it('matches IPs against stored allow-lists', function (): void {
    expect(IpWhitelist::allows('10.1.2.3', ['10.0.0.0/8']))->toBeTrue()
        ->and(IpWhitelist::allows('203.0.113.7', [' 203.0.113.7 ']))->toBeTrue()
        ->and(IpWhitelist::allows('203.0.113.8', ['203.0.113.7', '10.0.0.0/8']))->toBeFalse()
        ->and(IpWhitelist::allows(null, ['10.0.0.0/8']))->toBeFalse()
        ->and(IpWhitelist::allows(null, []))->toBeTrue()
        ->and(IpWhitelist::allows('203.0.113.8', ['', ' ']))->toBeTrue();
});
