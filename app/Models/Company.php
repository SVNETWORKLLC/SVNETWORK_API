<?php

namespace App\Models;

use App\Notifications\CompanyCreatedNotification;
use App\Notifications\MatchesCompanyAiNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class Company extends Model
{
    use HasFactory, SoftDeletes, Sluggable;
    public $timestamps = true;
    protected $fillable = [
        'name',
        'uuid',
        'description',
        'email',
        'city',
        'phone',
        'phone_2',
        'address_line1',
        'address_line2',
        'social_facebook',
        'social_x',
        'social_youtube',
        'zip_code',
        'video_url',
        'logo_url',
        'cover_url',
        'licence',
        'insurance',
        'is_claimed'
    ];
    protected $dates = ['deleted_at'];
    protected static function boot()
    {
        parent::boot();

        // Registering the creating event
        static::creating(function ($model) {
            $model->uuid = Str::uuid(); // Genera un UUID único
            $model->slug = $model->generateUniqueSlug($model->name);
        });
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
                'unique' => true,
                'separator' => '-',
            ]
        ];
    }
    private function generateUniqueSlug($name)
    {
        // Generar el slug base
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        // Verificar si el slug ya existe
        while (self::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
    public function states(): BelongsToMany
    {
        return $this->belongsToMany(State::class)->withTimestamps();
    }
    public function projects(): BelongsToMany
    {
        return $this->BelongsToMany(Project::class)->orderBy('created_at', 'desc')->withTimestamps();
    }
    public function leads()
    {
        return $this->hasMany(Matches::class, 'company_id', 'id')->orderBy('created_at', 'desc');
    }
    public function quotes()
    {
        return $this->hasMany(Quote::class, 'company_id', 'id')->orderBy('created_at', 'desc');
    }
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withPivot('pause')->withPivot('company_id')->withTimestamps() ?? [];
    }
    public function category()
    {
        $service =  $this->services->first();
        $category = $service->category ?? null;
        return $category->name ?? null;
    }

    public function companyServiceZip():HasMany
    {
        return $this->hasMany(CompanyServiceZip::class);
    }
    public function categories()
    {
        $categories = $this->services
        ->pluck('category')
        ->filter()
        ->unique()
        ->values();

        return $categories;
    }
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->orderBy('updated_at', 'desc');
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function getPublicUrlAttribute()
    {
        return config('app.app_url') . '/companies/' . $this->slug;
    }
    public function getReviewRateAttribute()
    {
        $total = 0;
        $countReviews = $this->reviews->count();
        $sumaReviews = $this->reviews->reduce(function ($suma, $review) {
            return $suma + $review->rate;
        }, 0);

        if ($countReviews > 0) {
            $total = $sumaReviews / $countReviews;
        }

        return floatval(number_format($total, 2));
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function getServicesByCategory($category_id)
    {
        return $this->services()->where('category_id', $category_id)->get();
    }
    public function sendMatchAiNotification($project)
    {
        $adminUsers = $this->users()->where('is_admin', true)->get();
        $link = null;
        //signedUrl route to claim company profile

        foreach ($adminUsers as $user) {
            try {
                $link = 'http://localhost:3000/user/companies/profile/leads/'.$project->id;
                $user->notify(new MatchesCompanyAiNotification($project, $link));

            } catch (\Exception $e) {
                // Capturar el error y almacenarlo en el archivo de log
                \Log::error('Error occurred: '.$e->getMessage(), [
                    'user_id' => $user->id,
                    'company_id' => $this->id,
                ]);
            }
        }
    }

    public function generateClaimUrl()
    {
        $link = URL::temporarySignedRoute(
            'auth.claim-company',
            now()->addMinutes(60),
            [
                'email' => $this->users()->first()->email,
                'user_id' => $this->users()->first()->uuid ?? null,
            ]
        );
        $api_url = config('app.api_url');
        $web_url = config('app.app_url');
        $link = str_replace($api_url, $web_url, $link);
        $link = str_replace('/api', '', $link);
        return $link;
    }
    public function updateRagN8n(){
        try{
            $n8nService = new \App\Services\N8nService('https://n8n.thesvnetwork.com/webhook/55bf450e-8521-4b61-ae78-af0ea2bb7f82');
            $n8nService->send([
                'company_id' => $this->id ?? '',

            ]);
        }catch(\Exception $e){
            \Log::error('Error sending company data to n8n: '.$e->getMessage());
        }
    }

    public function createCompanyNotificationToAdmin()
    {
        $admins = User::where('is_admin', 1)->get();
        $link = config('app.app_url') . '/admin/companies';
        $this->link = $link;
        foreach ($admins as $user) {
            $user->notify(new CompanyCreatedNotification($this));
        }
    }
}
