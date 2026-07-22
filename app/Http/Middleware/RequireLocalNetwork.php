<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth on top of the Tailscale/LAN firewall: reject any request
 * whose client IP is not within the configured local CIDRs.
 */
class RequireLocalNetwork
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('vault.network.allowed_cidrs', []);

        if (empty($allowed) || ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(403, 'The vault is only reachable from the local network.');
        }

        return $next($request);
    }
}
