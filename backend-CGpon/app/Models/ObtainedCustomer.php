<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObtainedCustomer extends Model
{
    use HasFactory;

    protected $table = 'obtained_customers';

    protected $fillable = [
        'olt_id',
        'gpon_interface',
        'customer_name',
        'status',
        'speed',
        'raw_config_section',
        'last_updated_at'
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];

    public function olt(): BelongsTo
    {
        return $this->belongsTo(OLT::class, 'olt_id');
    }
} 