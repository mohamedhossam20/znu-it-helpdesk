<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PWA sends the language the person picked; otherwise use their profile.
 */
class SetLocaleFromRequest
{
    private const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED);
        $header = $request->header('Accept-Language');

        if (! $header || ! in_array(substr($header, 0, 2), self::SUPPORTED, true)) {
            $locale = $request->user()?->locale ?? config('app.locale');
        }

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
