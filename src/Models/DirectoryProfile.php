<?php

namespace Goldnead\Directory\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryProfile extends Model
{
    protected $table = 'directory_profiles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'team_id' => 'integer',
            'hidden' => 'boolean',
            'published' => 'boolean',
        ];
    }
}
