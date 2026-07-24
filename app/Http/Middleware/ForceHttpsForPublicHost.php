<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsForPublicHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() === 'hcis.ensys.id' && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
