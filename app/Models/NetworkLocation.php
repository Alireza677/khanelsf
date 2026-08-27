<?php

namespace App\Models;

use App\Enums\NetworkLocationStatus;
use App\Enums\NetworkLocationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use App\Support\IranProvinces;

class NetworkLocation extends Model
{
    use HasFactory;

    protected $fillable = ['name','type','province_code','city','contact_name','position','mobile','phone','email','address','latitude','longitude','description','status','sort_order'];
    protected function casts(): array { return ['type'=>NetworkLocationType::class,'status'=>NetworkLocationStatus::class,'latitude'=>'decimal:7','longitude'=>'decimal:7','sort_order'=>'integer']; }
    protected static function booted(): void
    {
        static::saving(function (self $location): void {
            if (! IranProvinces::valid($location->province_code)) {
                throw ValidationException::withMessages(['province_code' => 'کد استان معتبر نیست.']);
            }
        });
    }
    public function scopeActive(Builder $query): Builder { return $query->where('status', NetworkLocationStatus::Active->value); }
    public function scopeOrdered(Builder $query): Builder { return $query->orderBy('sort_order')->orderBy('name'); }
}
