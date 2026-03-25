<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Http\Resources\CompanyResource;
use App\Notifications\CompanyCreatedNotification;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Stripe\StripeClient;
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
        private function getStripeClient()
    {
        return new StripeClient(config('app.stripe_pk'));
    }
        public function setLastMethodPaymentAsDefault()
    {
        $methods = $this->getDefaultPaymentMethodId();

        if (count($methods) > 0) {
            $stripe = $this->getStripeClient();
            $last_method = $methods[0];
            $stripe->customers->update(
                $this->stripe_client_id,
                [
                    'invoice_settings' => [
                        'default_payment_method' => $last_method->id,
                    ],
                ]
            );
        }
    }
    public function getDefaultPaymentMethodId()
    {
        if ($this->stripe_client_id) {
            $stripe = $this->getStripeClient();
            $customer = $stripe->customers->retrieve($this->stripe_client_id);
            $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method;

            $methods = $stripe->paymentMethods->all([
                'customer' => $this->stripe_client_id,
                'type' => 'card',
            ]);
            $methods2 = [];
            $methods2 = array_map(function ($method) use ($defaultPaymentMethodId) {
                if ($defaultPaymentMethodId == $method->id) {
                    $method->default = true;
                } else {
                    $method->default = false;
                }
                return $method;
            }, $methods['data']);
            $methods['data'] = $methods2;
            return $methods['data'];
        } else {
            return null;
        }
    }

    public function addCompany($request){

        $company = Company::create([
            'name' => $request->company_name,
            'email' => $this->email,
            'description' => $request->description,
            'phone' => $request->phone,
            'address_line1' => $request->address_line1,
            'city' => $request->city,
            'zip_code' => $request->zip_code
        ]);

        try {
            $data = [
                'firstname' => $this->name,
                'lastname' => $this->surname,
                'email' => $this->email,
                'company' => $request->company_name,
                'phone' => $request->phone,
                'country' => $request->country,
                'city' => $request->city,
                'state' => $request->state['name_en'],
                'zipcode' => $request->zip_code,
                'tags' => 'company'
            ];
            Mautic::createContact($data);
        } catch (Exception $e) {
            return $e;
        }
        if ($request->filled('state')) {
            $company->state_id = $request->state["id"];
        }

        if ($request->filled('services')) {
            foreach ($request->services as $key => $service) {
                $company->services()->syncWithoutDetaching($service["id"]);
            }
        }
        if ($request->filled('categories')) {
            foreach ($request->categories as $key => $category) {
                $company->categories()->syncWithoutDetaching($category["id"]);
            }
        }
        if ($request->filled('phone_2')) {
            $company->phone_2 = $request->phone_2;
        }
        if ($request->filled('phone')) {
            $company->phone = $request->phone;
        }

        if ($request->filled('address_line2')) {
            $company->address_line2 = $request->address_line2;
        }

        if ($request->filled('social_facebook')) {
            $company->social_facebook = $request->social_facebook;
        }

        if ($request->filled('social_x')) {
            $company->social_x = $request->social_x;
        }

        if ($request->filled('social_youtube')) {
            $company->social_youtube = $request->social_youtube;
        }

        if ($request->filled('video_url')) {
            $company->video_url = $request->video_url;
        }

        $company->save();
        //Add company to user
        $this->companies()->syncWithoutDetaching($company->id);
        $company->createCompanyNotificationToAdmin();

        $company->updateRagN8n();

        return new CompanyResource($company);
    }

}
