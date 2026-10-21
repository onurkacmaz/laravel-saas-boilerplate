<?php

use Infrastructure\Localization\Support\LocaleNegotiator;

it('picks the best supported locale', function (?string $header, string $expected) {
    expect(LocaleNegotiator::negotiate($header, ['en', 'tr'], 'en'))->toBe($expected);
})->with([
    'region tag' => ['tr-TR,tr;q=0.9,en;q=0.8', 'tr'],
    'base language' => ['tr', 'tr'],
    'quality beats order' => ['en;q=0.3, tr;q=0.9', 'tr'],
    'first supported wins' => ['de-DE,de;q=0.9,tr;q=0.5,en;q=0.4', 'tr'],
    'underscore tag' => ['tr_TR', 'tr'],
    'unsupported' => ['de-DE,fr;q=0.9', 'en'],
    'zero quality is refused' => ['tr;q=0, en;q=0.1', 'en'],
    'empty' => ['', 'en'],
    'missing' => [null, 'en'],
    'wildcard' => ['*', 'en'],
]);

it('keeps the client order when qualities are equal', function () {
    expect(LocaleNegotiator::negotiate('tr, en', ['en', 'tr'], 'en'))->toBe('tr')
        ->and(LocaleNegotiator::negotiate('en, tr', ['en', 'tr'], 'en'))->toBe('en');
});
