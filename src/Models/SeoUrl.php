<?php

namespace Rvzug\LaravelSeoUrls\Models;

use Illuminate\Database\Eloquent\Model;

class SeoUrl extends Model
{
    public $casts = [
        'route_parameters' => 'array',
    ];

    public function parent()
    {
        return $this->hasOne(self::class, 'id', 'redirect_to_seo_url_id');
    }
}
