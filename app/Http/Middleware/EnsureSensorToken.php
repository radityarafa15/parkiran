<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSensorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('parkir.sensor_token');

        if (! is_string($expected) || $expected === ''
            || ! hash_equals($expected, (string) $request->header('X-Sensor-Token'))) {
            return response()->json(['message' => 'Token sensor tidak valid.'], 401);
        }

        return $next($request);
    }
}
