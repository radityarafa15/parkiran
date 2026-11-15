<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi halaman admin ke peran tertentu, misalnya role:admin,petugas.
 * Pengguna lain diarahkan ke halaman utama perannya.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasRole(...$roles)) {
            return $next($request);
        }

        return redirect($user->homeUrl())->with('error', 'Halaman itu tidak untuk peran akunmu.');
    }
}
