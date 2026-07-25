<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected const SUPPORTED_LOCALES = ['en', 'fr'];

    /**
     * The frontend sends its current i18n locale on every API request so
     * validation messages (and anything else translated on this side)
     * match whatever language the user has selected, not the server default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Locale');

        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
