<?php

namespace Rvzug\LaravelSeoUrls\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Rvzug\LaravelSeoUrls\Models\SeoUrl;
use Rvzug\LaravelSeoUrls\Models\Traits\HasSeoUrls;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SeoUrls
{
    /**
     * Handle a slug request and return the response
     *
     * @throws NotFoundHttpException
     */
    public static function handleSlug(string $slug): Response|string
    {
        $model = config('seo-urls.model', SeoUrl::class);

        $seoUrls = $model::query()
            ->with('parent')
            ->where('slug', $slug)
            ->orderByDesc('is_canonical')
            ->orderByDesc('updated_at')
            ->get();

        if ($seoUrls->isEmpty()) {
            throw new NotFoundHttpException();
        }

        /** @var Router $router */
        $router = app('router');

        foreach ($seoUrls as $seoUrl) {
            // if the slug is canonical, the route is executed as an isolated request inside the application, and
            //   returned to the visitor in a response.
            // The current response will keep the current url as the canonical url
            if ($seoUrl->is_canonical) {
                $route = $router->getRoutes()->getByName($seoUrl->route_name) ?? null;

                if (is_null($route)) {
                    throw new \Exception(sprintf('Tried to show model %s::%s via route-name `%s`. The SEO-url is found, but has no corresponding route-name. Did you change the route-name recently? Use `php artisan seourls:rename-route-name {old} {new}` to apply the new route name to relevant seo-urls.', $seoUrl->model_type, $seoUrl->model_id, $seoUrl->route_name), 500);
                }

                if ($route instanceof Route) {
                    $route->parameters = $seoUrl->route_parameters;

                    return $route->run();
                }
            }

            // if the slug has a redirect-parent, redirect to that parent and handle that slug in a new request
            if ($seoUrl->redirect_to_seo_url_id && $seoUrl->parent instanceof SeoUrl) {
                return new RedirectResponse($seoUrl->parent->slug, 301);
            }

            // if non of the above, redirect to the original route
            return redirect()->route($seoUrl->route_name, $seoUrl->route_parameters, (int) $seoUrl->redirect ?? null);
        }

        throw new NotFoundHttpException();
    }

    /**
     * Return the seoUrl for the given model
     *
     * @param  HasSeoUrls  $model
     *
     * @throws \Exception
     */
    public static function createSeoUrlForModel(Model $model, $mustBeCannonical = false): string
    {
        if (! in_array(HasSeoUrls::class, class_uses($model), true)) {
            throw new \Exception(sprintf('Model %s does not use HasSeoUrls trait, so it is not possible to parse a SeoUrl for the model', get_class($model)));
        }

        dd($model);

        /** @var HasSeoUrls $model */
        $model->loadMissing('seoUrls');

        dd($model->seoUrls->isEmpty());

        if (! $model->seoUrls->isEmpty()) {
            $canonicalSeoUrl = $model->seoUrls->firstWhere('is_canonical', true);
            if ($canonicalSeoUrl instanceof SeoUrl) {
                return route('seo', ['slug' => $canonicalSeoUrl->slug]);
            }

            if (! $mustBeCannonical) {
                $nonParentSeoUrl = $model->seoUrls->firstWhere('redirect_to_seo_url_id', null);
                if ($nonParentSeoUrl instanceof SeoUrl) {
                    return route('seo', ['slug' => $nonParentSeoUrl->slug]);
                }
            }
        }

        Log::warning(sprintf('Model %s::%s did not have any valid seo urls. But the model is called by the SeoUrls::createSeoUrlForModel()/seoUrl() helper. The getRouteName/getRouteParameters methods are used, but can result is unexpected results if not configured correctly.', get_class($model), $model->getKey()));

        return route($model->getRouteName(), $model->getRouteParameters());
    }

    public static function redirectInternalRoute(string $routeName, Request $request): ?RedirectResponse
    {
        // retreive the parameter-structure for this request
        $routeParameterStructure = SeoUrl::query()
            ->where('route_name', $routeName)
            ->select(['model_type', 'route_parameters'])
            ->limit(1)
            ->first();

        // if no structure is found, do nothing
        if (! $routeParameterStructure instanceof SeoUrl) {
            return null;
        }

        // retreive the cannonical seo-url based on the request parameters of the current request-route
        $redirectSeoUrlQuery = SeoUrl::query()
            ->where('route_name', $routeName)
            ->where('model_type', $routeParameterStructure->model_type)
            ->orderByDesc('is_canonical')
            ->orderByDesc('updated_at');

        foreach ($request->route()->parameters() as $key => $value) {
            $redirectSeoUrlQuery->whereRaw('JSON_EXTRACT(route_parameters, "$.'.$key.'") = '.$value.'');
        }

        // get the first seo-url that matches the current request, if none found, use the Fail method to throw a 404
        $redirectSeoUrl = $redirectSeoUrlQuery->firstOrFail();

        return redirect($redirectSeoUrl->slug, 301);
    }
}

if (! function_exists('seoUrl')) {
    /**
     * Return the seoUrl for the given model
     *
     * @throws \Exception
     */
    function seoUrl(Model $model, $cannonical = false): string
    {
        return SeoUrls::createSeoUrlForModel($model, $cannonical);
    }
}

if (! function_exists('cannonicalUrl')) {
    /**
     * Return the canonnicalUrl for the given model
     *
     * @throws \Exception
     */
    function cannonicalUrl(Model $model): string
    {
        return SeoUrls::createSeoUrlForModel($model, true);
    }
}
