<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the locale stored in the session (set via the language switcher).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $request->cookie('locale')
            ?? config('app.locale');

        if (in_array($locale, config('app.supported_locales', ['en']), true)) {
            App::setLocale($locale);
            
            if (!$request->session()->has('locale')) {
                session(['locale' => $locale]);
            }
        }

        return $next($request);
    }
}
