<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every API call must send "Authorization: Bearer <token>" with the token shown on the API List screen. */
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status'  => false,
                'message' => 'Missing Authorization header. Send: Authorization: Bearer <token>',
            ], 401);
        }

        if (!hash_equals(Setting::apiToken(), $token)) {
            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized: invalid token.',
            ], 401);
        }

        return $next($request);
    }
}
