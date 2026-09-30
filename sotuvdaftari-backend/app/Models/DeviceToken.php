<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'token', 'platform', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
