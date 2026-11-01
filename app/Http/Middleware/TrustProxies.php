<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Trust all proxies: production runs behind Hostinger's CDN/anti-bot
     * layer (hcdn), whose forwarding IP we don't control, so the app must
     * rely on its X-Forwarded-* headers to detect HTTPS correctly.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * Deliberately excludes X-Forwarded-For: we don't control the CDN's
     * forwarding IP, so trusting that header would let a client spoof
     * $request->ip() (e.g. REMOTE_ADDR) and bypass IP-based throttling on
     * /login and other rate-limited routes. Hostinger's CDN already sets
     * REMOTE_ADDR to the real visitor IP, so client IP detection doesn't
     * need the header anyway — only scheme/host/port detection does.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}
