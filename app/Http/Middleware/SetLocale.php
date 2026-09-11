<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_LOCALES = ['tr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $requestedLocale = $request->query('locale');

        if (is_string($requestedLocale) && in_array($requestedLocale, self::SUPPORTED_LOCALES, true)) {
            $request->session()->put('locale', $requestedLocale);
        }

        $locale = $request->session()->get('locale', config('app.locale'));

        if (! is_string($locale) || ! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = config('app.fallback_locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
