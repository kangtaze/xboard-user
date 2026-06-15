<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CfWorker extends Model
{
    protected $table = 'v2_cf_worker';

    const STATUS_PENDING = 0;
    const STATUS_DEPLOYED = 1;
    const STATUS_ERROR = 2;

    protected $fillable = [
        'name',
        'cf_account_id',
        'cf_api_token',
        'cf_zone_id',
        'domain',
        'worker_name',
        'kv_namespace_id',
        'xboard_node_id',
        'status',
        'error_msg',
        'deployed_at',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
        'status' => 'integer',
        'xboard_node_id' => 'integer',
    ];

    protected $hidden = [
        'cf_api_token',
    ];

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => '未部署',
            self::STATUS_DEPLOYED => '已部署',
            self::STATUS_ERROR => '错误',
            default => '未知',
        };
    }
}
