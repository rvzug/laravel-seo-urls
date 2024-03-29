<?php

namespace Rvzug\LaravelSeoUrls\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Rvzug\LaravelSeoUrls\Services\SeoUrls;
use Symfony\Component\HttpFoundation\Response;

class InterceptInternalRoute
{
    /**
     * Check if there is a seo-url for the internal route and redirect if found, or throw a 404 error
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()->action['as'] ?? null;

        if (! is_null($routeName)) {
            $redirect = SeoUrls::redirectInternalRoute($routeName, $request);

            if ($redirect instanceof RedirectResponse) {
                return $redirect;
            }

        } else {
            throw new \Exception('Route name not defined while intercept-internal-route middleware is applied to the route');
        }
    }
}
