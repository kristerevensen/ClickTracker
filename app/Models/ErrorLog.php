<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'link_token',
        'error_message',
        'stack_trace',
        'ip',
        'user_agent',
    ];

    public $timestamps = false; // Disable timestamps if you're not using them
}
