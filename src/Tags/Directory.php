<?php

namespace Goldnead\Directory\Tags;

use Goldnead\Directory\Services\Collections;
use Goldnead\Directory\Services\ProfileSearch;
use Illuminate\Support\Facades\Validator;
use Statamic\Facades\Site;
use Statamic\Tags\Tags;

class Directory extends Tags
{
    protected static $handle = 'directory';

    /**
     * {{ directory:search lat="" lng="" radius_km="" q="" city="" type="" page="" per_page="" choir_type="" }}
     * Returns profiles, page, per_page, total, last_page, no_results.
     */
    public function search(): array
    {
        $names = array_merge(['type', 'q', 'city', 'lat', 'lng', 'radius_km', 'page', 'per_page'], Collections::filterColumns());
        $input = array_filter($this->params->only($names)->all(), fn ($v) => $v !== null && $v !== '');

        if (Validator::make($input, ProfileSearch::rules())->fails()) {
            return ['profiles' => [], 'page' => 1, 'per_page' => 0, 'total' => 0, 'last_page' => 1, 'no_results' => true];
        }

        $result = app(ProfileSearch::class)->search($input, Site::current()->handle());

        return ['profiles' => $result['data'], 'no_results' => $result['data'] === []] + $result['meta'];
    }
}
