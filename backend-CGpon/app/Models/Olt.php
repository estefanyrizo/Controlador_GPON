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

    // Protege solo el ID
    protected $guarded = ['id'];

    /**
     * Relación: OLT tiene muchos clientes
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'olt_id');
    }

    /**
     * Relación: OLT pertenece a muchos ISPs (pivot con booleano)
     */
    public function isps(): BelongsToMany
    {
        return $this->belongsToMany(ISP::class, 'isp_olt', 'olt_id', 'isp_id')
                    ->using(IspOlt::class)
                    ->withPivot('relation_name', 'relation_notes', 'status') // booleano
                    ->withTimestamps();
    }

    /**
     * Scope: filtra solo ISPs activos en el pivot
     */
    public function activeIsps(): BelongsToMany
    {
        return $this->isps()->wherePivot('status', true);
    }

    /**
     * Relación: OLT creado por un usuario
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: solo OLTs activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope: solo OLTs inactivos
     */
    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }
}
