<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $fillable = [
        'name', 'photo_url', 'reps_schema', 'day_of_week', 'suggested_weight'
    ];

    public function logs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
