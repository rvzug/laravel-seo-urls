<?php

namespace Rvzug\LaravelSeoUrls\Models\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Rvzug\LaravelSeoUrls\Models\SeoUrl;

/**
 * @param  Collection<SeoUrl>  $seoUrls
 */
trait HasSeoUrls
{
    /**
     * It is possible to prevent the update of the seo urls by setting this property to true before
     *   using the create() or save() method.
     *
     * @var false
     */
    private $dontUpdateSeoUrl = false;

    private $slug = null;

    public static function bootHasSeoUrls(): void
    {
        static::saved(function (self $model) {

            if ($model->dontUpdateSeoUrl) {
                return;
            }

            $model->loadMissing('seoUrls');
            $oldSeoUrls = $model->seoUrls;

            $newSeoUrl = $model->seoUrls()->create([
                'slug' => $model->makeSlug(),
                'route_name' => $model->getRouteName(),
                'route_parameters' => $model->getRouteParameters(),
                'is_canonical' => true,
            ]);

            if (config('seo-urls.seo_url_history_strategy', 'update') === 'update') {
                // dispatch(function () use ($oldSeoUrls, $newSeoUrl){
                SeoUrl::query()
                    ->whereIn('id', $oldSeoUrls->pluck('id'))
                    ->update([
                        'is_canonical' => false,
                        'redirect' => (string) 301,
                        'redirect_to_seo_url_id' => $newSeoUrl->id,
                    ]);
                // })->afterResponse();

            } else {
                // dispatch(function () use ($oldSeoUrls){
                SeoUrl::query()
                    ->whereIn('id', $oldSeoUrls->pluck('id'))
                    ->delete();
                // })->afterResponse();
            }
        });
    }

    public function initializeHasSeoUrls(): void
    {
        if (config('seo-urls.model_defaults.eager_load_seo_urls', false)) {
            $this->with = array_merge($this->with ?? [], ['seoUrls']);
        }

        if (config('seo-urls.model_defaults.touch_update_at_on_seo_urls', false)) {
            $this->touches = array_merge($this->touches ?? [], ['seoUrls']);
        }
    }

    public function seoUrls(): MorphMany
    {
        return $this->morphMany(SeoUrl::class, 'model');
    }

    protected function getRouteName(): string
    {
        throw new \Exception(sprintf('Route name is not defined on Model. Implement getRouteName() method on Model: %s', class_basename($this)));
    }

    protected function getRouteParameters(): array
    {
        return [
            $this->getKeyName() => $this->getKey(),
        ];
    }

    protected function generateSeoUrlSlug(): string
    {
        return Str::slug(sprintf('%s-%s', class_basename($this), '-', $this->getKey()));
    }

    private function makeSlug()
    {
        return $this->slug = $this->slug ?? $this->generateSeoUrlSlug();
    }
}
