<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'exercise_id', 'performed_at', 'set_index', 'weight', 'reps'
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
}
