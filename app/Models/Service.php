<?php

namespace App\Models;

use App\Http\Resources\CompanyServiceStateResource;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\HttpFoundation\Request;
class Service extends Model
{
    use HasFactory, Sluggable, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'order',
        'image'
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
                'unique' => true,
                'uniqueSuffix' => function ($slug, $separator, $list) {
                    // Genera un sufijo numérico que se incrementa automáticamente
                    return count($list);
                }
            ]
        ];
    }

    public function category():BelongsTo
    {
        return $this->BelongsTo(Category::class);
    }
    public function companies():BelongsToMany
    {
        return $this->BelongsToMany(Company::class);
    }
    public function questions():HasMany
    {
        return $this->hasMany(Question::class)->orderBy('id', 'asc');
    }
    public function companyServiceZip():HasMany
    {
        return $this->hasMany(CompanyServiceZip::class);
    }
    public function companyServiceState():HasMany
    {
        return $this->hasMany(CompanyServiceState::class);
    }
    public function zipcodes():BelongsToMany
    {
        return $this->belongsToMany(Zipcode::class,'company_service_zip', 'service_id', 'zipcode_id');
    }
    public function states():BelongsToMany
    {
        return $this->belongsToMany(State::class,'company_service_state', 'service_id', 'state_id')->withTimestamps()->withPivot(['company_id']);
    }

    public function statesCompany($company_id)
    {
        return $this->belongsToMany(State::class,'company_service_state', 'service_id', 'state_id')->withTimestamps()->withPivot(['company_id'])->where('company_id', $company_id);
    }
    public function getQuestionsPaginatedAttribute()
    {
        return $this->questions()->paginate(1);
    }

    public function stateList($company_id){
        return CompanyServiceStateResource::collection($this->states);
    }

    public function servicesRegion(Request $request){

        $this->companyServiceZip->where('company_id', 52)->where('region_text','Montgomery')->get();
    }



}
