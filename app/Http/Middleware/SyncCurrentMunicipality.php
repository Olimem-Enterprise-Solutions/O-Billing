<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentMunicipality;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the active Filament tenant (Municipality) into the CurrentMunicipality
 * singleton so global scopes and services filter to the right tenant. Runs as
 * Filament tenant middleware, after the tenant has been resolved.
 */
class SyncCurrentMunicipality
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            app(CurrentMunicipality::class)->set($tenant->getKey());
        }

        return $next($request);
    }
}
