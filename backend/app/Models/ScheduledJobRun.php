<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ScheduledJobRun extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'job_name',
        'status',
        'started_at',
        'finished_at',
        'items_processed',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'items_processed' => 'integer',
        ];
    }
}
