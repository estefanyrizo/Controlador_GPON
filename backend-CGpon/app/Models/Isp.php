<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ISP extends Model
{
    use HasFactory;

    protected $table = 'isps';

    protected $fillable = [
        'name',
        'description',
        'status_id',
    ];

    public function status(): BelongsTo {
        return $this->belongsTo(Status::class);
    }

    public function olts(): BelongsToMany {
        return $this->belongsToMany(OLT::class, 'isp_olt', 'isp_id', 'olt_id')
                    ->withPivot('relation_name', 'relation_notes', 'status_id')
                    ->withTimestamps();
    }

    public function users(): HasMany {
        return $this->hasMany(User::class, 'isp_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'isp_id');
    }


    public function scopeActive($query) {
        $activeStatusId = Status::where('code', 'active')->value('id');
        return $query->where('status_id', $activeStatusId);
    }
}
