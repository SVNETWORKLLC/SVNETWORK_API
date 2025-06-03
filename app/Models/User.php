<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'surname',
        'image',
        'pro',
        'phone',
        'description',
        'verified_phone'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'google_id',
        'remember_token',
    ];

    protected $dates = ['deleted_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    protected static function boot()
    {
        parent::boot();

        // Registering the creating event
        static::creating(function ($model) {
            $model->uuid = Str::uuid(); // Genera un UUID único
        });
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('created_at', 'asc');
    }
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->orderBy('created_at', 'asc');
    }
    public function matches(): HasMany
    {
        return $this->hasMany(Project::class)->whereHas('matches')->orderBy('created_at', 'asc');
    }
    public function answers(): BelongsToMany
    {
        return $this->belongsToMany(Answer::class, 'answer_project', 'user_id', 'answer_id');
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transactions::class);
    }

    public function pushNotifications()
    {
        return $this->hasMany(\App\Models\PushNotification::class);
    }

    public function getAvatarTextAttribute(){
        $text = substr($this->name, 0, 1).substr($this->surname, 0, 1);
        return strtoupper($text);
    }

}
