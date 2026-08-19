<?php

namespace XboardFerrari\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgetunnelNode extends Model
{
    protected $table = 'v2_edgetunnel_node';

    protected $casts = [
        'enabled' => 'boolean',
        'show' => 'boolean',
        'online' => 'boolean',
    ];

    protected $fillable = [
        'subscription_id', 'name', 'host', 'port', 'uuid', 'path',
        'sni', 'host_header', 'fingerprint', 'security', 'type',
        'transport',
        'enabled', 'show', 'sort', 'latency', 'online', 'last_check_at',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(EdgetunnelSubscription::class, 'subscription_id');
    }
}
