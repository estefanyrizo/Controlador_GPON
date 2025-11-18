<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ISP extends Model
{
    use HasFactory;

    protected $table = 'isps';

    protected $fillable = [
        'name',
        'description',
        'status', // booleano: true = activo, false = inactivo
    ];

    /**
     * Relación: ISP tiene muchos OLTs (pivot con status booleano)
     */
    public function olts(): BelongsToMany
    {
        return $this->belongsToMany(OLT::class, 'isp_olt', 'isp_id', 'olt_id')
                    ->withPivot('relation_name', 'relation_notes', 'status') // booleano
                    ->withTimestamps();
    }

    /**
     * Scope: solo ISPs activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope: solo ISPs inactivos
     */
    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

    /**
     * Relación: ISP tiene muchos usuarios
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'isp_id');
    }

    /**
     * Relación: ISP tiene muchos clientes
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'isp_id');
    }
}
