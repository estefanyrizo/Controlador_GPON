<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;


class User extends Authenticatable implements JWTSubject
{
use HasFactory, Notifiable;


protected $guarded = ['id'];


protected $hidden = ['password', 'remember_token'];


public function setPasswordAttribute($value) { $this->attributes['password'] = bcrypt($value); }


public function getJWTIdentifier() { return $this->getKey(); }
public function getJWTCustomClaims() { return []; }


public function userType() { return $this->belongsTo(UserType::class, 'user_type_id'); }
public function isp() { return $this->belongsTo(Isp::class, 'isp_id'); }
public function activityLogs() { return $this->hasMany(ActivityLog::class, 'user_id'); }

public function findForPassport($username)
{
    return $this->where('username', $username)->first();
}


}