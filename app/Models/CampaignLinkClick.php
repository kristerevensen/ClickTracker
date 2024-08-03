<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignLinkClick extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'campaign_link_clicks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_agent',
        'referrer',
        'ip',
        'platform',
        'browser',
        'device_type',
        'screen_resolution',
        'language',
        'session_id',
        'click_count',
        'link_token',
    ];

    /**
     * Get the campaign link associated with the click.
     */
    public function campaignLink()
    {
        return $this->belongsTo(CampaignLink::class, 'link_token', 'link_token');
    }
}
