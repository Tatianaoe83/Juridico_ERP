<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un endoso de extensión de una póliza de obra: recorre su fin de vigencia
 * sin cambiarle el número. Guarda el fin anterior para poder deshacerlo, la
 * prima adicional que se cobró y, si se subió, el documento del endoso.
 */
class ConstructionPolicyEndorsement extends Model
{
    /** Lo que se acepta como documento del endoso: PDF o imagen. */
    public const DOCUMENT_TYPES = 'pdf,jpg,jpeg,png,webp';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'previous_valid_until' => 'date',
            'valid_until' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ConstructionPolicy::class, 'construction_policy_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
