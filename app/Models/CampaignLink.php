<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', //id
        'landing_page', //where the link is going to redirect to
        'link_token', //unique token for the link
        'project_code', //project_code
        'source', //utm_source
        'medium', // utm_medium
        'term', //utm_term
        'content', //utm_content
        'target', //target
        'tagged_url', //tagged url
        'campaign_id', //campaign_id
        'campaign_token', //campaign_token
        'custom_parameters', //custom parameters
        'description', //description
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
