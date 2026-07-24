<?php

namespace App\Http\Middleware;

use App\Models\CoreApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCoreApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return response()->json(['message' => 'Sesi Core tidak ditemukan.'], 401);
        }

        $token = CoreApiToken::query()
            ->with('employee.company', 'employee.department', 'employee.position')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token || $token->expires_at->isPast() || ! $token->employee?->canLoginToCore()) {
            $token?->delete();

            return response()->json(['message' => 'Sesi Core sudah berakhir.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $token->employee);
        $request->attributes->set('core_api_token', $token);

        return $next($request);
    }
}
