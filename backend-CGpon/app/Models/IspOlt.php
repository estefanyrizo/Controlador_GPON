<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class IspOlt extends Pivot
{
    protected $table = 'isp_olt';

    protected $guarded = ['id'];

    // Tell Laravel to treat this Pivot as a model with timestamps
    public $incrementing = true;

    public function isp(): BelongsTo
    {
        return $this->belongsTo(ISP::class, 'isp_id');
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(OLT::class, 'olt_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
