<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    use HasFactory;

    protected $table = 'statuses';

    protected $guarded = ['id'];

    public function users(): HasMany {
        return $this->hasMany(User::class, 'status_id');
    }

    public function customers(): HasMany {
        return $this->hasMany(Customer::class, 'status_id');
    }

    public function isps(): HasMany {
        return $this->hasMany(ISP::class, 'status_id');
    }
}
