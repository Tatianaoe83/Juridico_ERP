<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento de una póliza de obra: la póliza misma, anexos… Los de los
 * endosos van con su endoso. El archivo vive en disco; aquí solo queda su
 * ruta y con qué nombre lo subieron.
 */
class ConstructionPolicyDocument extends Model
{
    protected $guarded = [];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ConstructionPolicy::class, 'construction_policy_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
