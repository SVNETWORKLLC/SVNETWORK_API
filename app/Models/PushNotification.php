<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'device',
        'platform',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
