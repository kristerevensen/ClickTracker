<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_name',
        'project_code',
        'campaign_token',
        'created_by',
        'start',
        'end',
        'status',
        'reporting',
        'force_lowercase',
        'utm_activated',
        'monitor_urls',
        'description',
    ];

    // Define relationship with the Project model
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_code', 'project_code');
    }

    // Define relationship with the User model
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
