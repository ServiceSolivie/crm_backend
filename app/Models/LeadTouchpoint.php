<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadTouchpoint extends Model
{
    protected $fillable = ['lead_id', 'lead_source_id', 'campaign_id', 'external_id', 'payload', 'is_test', 'submitted_at', 'received_at', 'gcl_id', 'adgroup_id', 'creative_id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'is_test' => 'boolean', 'submitted_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
