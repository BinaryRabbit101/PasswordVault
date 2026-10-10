<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lowercases and trims the `email` field on the auth routes, so phones that
 * capitalise the first letter still sign in, reset and register (SQLite `=`
 * is case-sensitive and every stored email is lowercase).
 */
class NormalizeEmailInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        return $next($request);
    }
}
