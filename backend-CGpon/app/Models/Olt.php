<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OLT extends Model
{
    use HasFactory;

    protected $table = 'olts';

    protected $guarded = ['id'];

    public function status(): BelongsTo {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function customers(): HasMany {
        return $this->hasMany(Customer::class, 'olt_id');
    }

    public function isps(): BelongsToMany {
        return $this->belongsToMany(ISP::class, 'isp_olt', 'olt_id', 'isp_id')
                    ->using(IspOlt::class)
                    ->withPivot('relation_name', 'relation_notes', 'status_id')
                    ->withTimestamps();
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query) {
        $activeStatusId = Status::where('code', 'active')->value('id');
        return $query->where($this->table.'.status_id', $activeStatusId);
    }

}
