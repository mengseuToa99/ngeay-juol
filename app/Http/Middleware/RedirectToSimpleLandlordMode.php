<?php

namespace App\Http\Middleware;

use App\Support\SimpleLandlordMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToSimpleLandlordMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SimpleLandlordMode::shouldRedirectToSimple($request)) {
            return redirect()->route('filament.landlord.pages.simple');
        }

        return $next($request);
    }
}
