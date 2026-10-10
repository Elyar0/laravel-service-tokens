<?php

namespace Elyar\ServiceTokens\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTokenPermission extends Model
{
    protected $guarded = [];
    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        return $this->method . '@' . $this->uri;
    }

    public function serviceToken(): BelongsTo
    {
        return $this->belongsTo(ServiceToken::class);
    }
}
