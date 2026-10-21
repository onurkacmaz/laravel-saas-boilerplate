<?php

namespace Infrastructure\Localization\Middleware;

use Closure;
use Illuminate\Http\Request;
use Infrastructure\Localization\Support\LocaleNegotiator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers in the language the client asks for with Accept-Language, among
 * config('app.supported_locales'): messages, validation errors and e-mails
 * sent during the request.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = LocaleNegotiator::negotiate(
            $request->header('Accept-Language'),
            config('app.supported_locales', [config('app.locale')]),
            config('app.locale'),
        );

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
