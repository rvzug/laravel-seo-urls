# Automaticly create SEO-urls and redirects while Laravel models are saved 

> Use sluggabe SEO-urls for models, respecting SEO-rules like the cannonical-url principle, avoiding duplicate content and keeping track of the historical seo-urls of the model

> WIP: This documentation/README is a speedy draft to point out most of the features. It should already cover the basics of this package.

## Introduction
Using slugs is a relative common feature in webapplications to define pretty - and human readable - urls. Laravel itself makes it possible to use slugs while using [route model binding](https://laravel.com/docs/11.x/routing#customizing-the-key). Also there are many packages which makes it possible to use slugs as url-pointer to the right model. Especially when using the [Spatie Sluggable](https://github.com/spatie/laravel-sluggable) package you can make powerfull sluggable urls.

This works fine, but from a Search Engine Optimalisation (SEO) perspective it is a potential risk when the slug changes: The old url is no longer reachable and will result in an increse of _broken links_. If broken links are not handled correctly it can result in an increase of SEO-penalties and a decreice of the position in the search engines.

To prevent this, it's common practice to keep the slug as a '_permalink_', and just never update the slug. But when you deliberitly want to update the slug, eg. when you made a typo, or you give the end-user control over the slug, there are no possibilities to fix that. If you do this right after the creation of the url, thats fine. But if you find a typo after couple of weeks, or 
decide it is better to change the slug, you have to manually create a redirect.

This package makes it possible to:
* Create (and handle) slugs on multiple models
* Add the ability to change the slugs
* Keep a history of old slugs
* Automaticly redirect (or forward) old url-slugs to the most recent slug
* Provides multiple helper-functions to retreive the correct seo-urls

## Routing principle
This package assumes you have already 'non-sluggable' routes for your models. Like showing a topic, it is common practice to create a route that handles the url ```domain.com/topic/{id}```.

Like:
```php
Route::get('/topic/{id}', [TopicController::class, 'show'])->name('topic.show');
```
Typically the ```TopicController::show()``` method will return a response with the view of the topic. The package uses this routes as _internal routes_ to forward the user to the correct view. 

This package also assumes the route has a defined route-name on those routes.

When using this package, you'll have to keep that routes. When a seo-url is used to navigate to a view, it will **forward** the request inside the application to that originally defined route, while keeping the browsers' url to the seo-url:

```domain.com/why-you-should-avoid-broken-links >>-forward->> domain.com/topic/123```

The browser will display: ```domain.com/why-you-should-avoid-broken-links```
The internal route is used: ```/topic/123```

### Redirecting old seo-urls
If an old ```{slug}``` is used, this package will **redirect** the browser to the most recently correct ```{slug}```. And than **forwards** to the internal route for displaying the correct view:

```domain.com/why-yuo-should-avoid-brken-lnk >>-301->> domain.com/why-you-should-avoid-broken-links >>-forward->> domain.com/topic/123```

The browser will display: ```domain.com/why-you-should-avoid-broken-links```
The internal route is used: ```/topic/123```

### Disabling the internal routes
It is still possible to browse to the internal url, like ```domain.com/topic/123```. The topic will be shown. If you don't want this behaviour, you can add the provided ```InterceptInternalRoute```-middleware to the route:

```php
use Rvzug\LaravelSeoUrls\Http\Middleware\InterceptInternalRoute;

// ...

Route::get('topic/{id}', [TopicController::class, 'show'])
    ->name('topic.show')
    ->middleware(InterceptInternalRoute::class)
```

This middleware will **intercept** the internal route, **redirect** to the seo-url (shown in the browser) and than **forward** to the internal route again to display the view.

The browser will display: ```domain.com/why-you-should-avoid-broken-links```
The internal route is used: ```/topic/123```

_It will use the route-name and provided route-parameters (in this case: id) to search for corresponding seo-urls._ If no model is found, it will return [ModelNotFoundException / 404-error](https://laravel.com/docs/11.x/eloquent#not-found-exceptions).

### Prevent duplicate-content penalties
If you want to keep the default behaviour for internal routes, so ```domain.com/topic/123``` just displays the view without redirecting to the seo-url, you should implement the following snippet in the ```<head>``` section of your blade-templates:

```
<link rel="canonical" href="{{ cannonicalUrl($model) }}" />
```
See [Google Search Central](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls?sjid=5657758936932945557-EU) for more information about the cannonical url principle and avoid duplicate-content penalties.

# Documentation

## Installation
Require the package:
```composer require rvzug/laravel-seo-urls```

Publish the migration files:
```php artisan vendor:publish ...```

Run the migrations to add a _seo_urls_ table to your database:
```php artisan migrate```

## Configuration
This package works out-of-the-box without changing the configuration. But if you want to change the default configuration, you can publish the package config file:
```php artisan vendor:publish ...```

### Default configuration
```
```

## Quick start
To use SEO-urls, you should implement three snippets of code into your application:

1) Add the HasSeoUrls-trait to your models that should generate seo-urls:
   
```php
// Models/Topic.php

use Rvzug\LaravelSeoUrls\Models\Traits\HasSeoUrls

class Topic extends Model
{
    use HasSeoUrls;
    ...
}
   ```
2) Implement the ```getRouteName()```-method on the model, to define to wich route the detail-route should point:
```php
    protected function getRouteName()
    {
        return 'topics.show';
    }
```

3) Add the seo-url-route to your routes, **after** any home route (and **before** a [fallback-route](https://laravel.com/docs/11.x/routing#fallback-routes) if defined):
```php
// routes/web.php

// Default home route
Route::get('/', function () { // Original home route
    return view('welcome');
});

// Added route to handle slugs
Route::any('/{slug}', function (string $slug) {
    return SeoUrls::handleSlug($slug);
})->name('seo');

// Fallback route (@see: https://laravel.com/docs/11.x/routing#fallback-routes)
Route::fallback(function () {
    // ...
});
// ...
```

If you plan to use forward slashes (```/```) in your seo-urls, it is mandatory to chain the the ```->where('slug', '.*')``` method to the slug-route. See [Laravel documentation](https://laravel.com/docs/11.x/routing#parameters-encoded-forward-slashes).

```php
Route::any('/{slug}', function (string $slug) {
    return SeoUrls::handleSlug($slug);
})->where('slug', '.*')->name('seo');
```

At this moment, while creating a model that uses the trait, it will be available on the url:
```domain.com/{model}-{id}```

## Models
You can change the generated slug by adding the ```generateSeoUrlSlug()```-method in your model:

```php
use Illuminate\Support\Str;

protected function generateSeoUrlSlug(): string
{
    return Str::slug($this->attributes['title']);
}
```
The method should return a string, that defines the slug you want. You can make use of the models' attributes like ```$this->title```. Sometimes even better; use the attributes-array ```$this->attributes['title']```

### Example: Date-based slugs
It is also possible to create a nice date-based seo-url, like ```/2024/03/01/why-you-should-use-date-pages```

```php
use Carbon\Carbon;
use Illuminate\Support\Str;

protected function generateSeoUrlSlug(): string
{
    return Str::slug(sprintf('%s/%s/%s/%s', 
        (new Carbon($this->attributes['created_at']))->format('Y'),
        (new Carbon($this->attributes['created_at']))->format('m'),
        (new Carbon($this->attributes['created_at']))->format('d'),
        $this->attributes['title'])
    );
}
```

## Saving models
When saving models, the slug will be generated based on the following priority
1) If you set ```$model->slug``` before you use ```$model->save()``` this will be used
2) If you add the ```generateSeoUrlSlug()``` will be used as fallbacl
3) The package fallback is ```{{model_name}}-{{primary-key}}```

### Disable automaticly saving seo-urls
If you don't want to save a seo-url while saving, set the ```$model->dontUpdateSeoUrl = true;``` property to ```true```

### SeoUrl-relation
The ```HasSeoUrls```-trait adds a relation  ```$model->seoUrls()``` to your model. This contains a collection of ```SeoUrl``` models. This model has the following attributes:

| Attribute | Type | Default value |
|--------------------------|----------------|----------------|
| id | int | auto-increment |
| slug | varchar(255) |   |
| model_type | varchar(255)* |   |
| model_id | int* |   |
| redirect | enum(null, 301, 302) | 301 |
| redirect_to_seo_url_id | int | null |
| route_name | varchar(255) | models-route-name |
| route_parameters | json | models-route-parameters |
| is_canonical | tinyint(1) | false |
| created_at | datetime | now() |
| updated_at | datetime | null |

\* the ```model_type```/```model_id``` are a [polymorphic relation](https://laravel.com/docs/11.x/eloquent-relationships#polymorphic-relationships) to the model that created the seo-url

The [Eloquent events](https://laravel.com/docs/11.x/eloquent#events) are used to save the seo-url relation while saving the parent model.