<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $guarded = ['id'];

    public function olt() : BelongsTo {
        return $this->belongsTo(OLT::class, 'olt_id');
    }

    public function activityLogs (): HasMany {
        return $this->hasMany(ActivityLog::class, 'customer_id');
    }

    public function isp(): BelongsTo
    {
        return $this->belongsTo(ISP::class, 'isp_id');
    }

}
