<?php

namespace XboardFerrari\Models;

use App\Models\CfWorker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgetunnelSubscription extends Model
{
    protected $table = 'v2_edgetunnel_subscription';

    protected $casts = [
        'group_ids' => 'array',
        'enabled' => 'boolean',
        'show' => 'boolean',
        'dns_cf_worker_id' => 'integer',
    ];

    protected $fillable = [
        'name', 'url', 'group_ids', 'enabled', 'show', 'sort', 'last_sync_at',
        'dns_cf_worker_id', 'dns_zone_domain',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(EdgetunnelNode::class, 'subscription_id');
    }

    public function dnsWorker(): BelongsTo
    {
        return $this->belongsTo(CfWorker::class, 'dns_cf_worker_id');
    }
}
