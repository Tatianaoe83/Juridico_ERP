<?php

namespace App\Models;

use App\Support\CoverageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una póliza de obra: asegura una obra, no una unidad. Las vehiculares y las
 * de maquinaria viven en Flotillas (UnitPolicy).
 *
 * Sin semestres ni cuotas: un monto con IVA incluido y su vigencia, que por lo
 * general corre hasta la terminación de la obra. Si la aseguradora la extiende
 * con un endoso, la póliza conserva su número y solo se recorre su fin.
 */
class ConstructionPolicy extends Model
{
    /**
     * Las coberturas, las mismas que el enum de la columna `coverage`: obra
     * civil, responsabilidad civil de construcción, montaje y maquinaria y equipo.
     */
    public const COVERAGES = ['civil_works', 'construction_liability', 'erection', 'machinery_equipment'];

    /** IVA que trae incluido el monto capturado. */
    public const TAX_RATE = 0.16;

    protected $guarded = [];

    /** Vigente, por vencer o vencida, según su fin (el del último endoso). */
    public function status(): string
    {
        return CoverageStatus::of($this->valid_until);
    }

    /**
     * Lo que cuesta en total: el monto de la póliza más la prima de sus
     * endosos, con IVA incluido. Usa `endorsements_sum_amount` si la consulta
     * lo trae (withSum) para no cargar los endosos en la tabla.
     */
    public function total(): float
    {
        return (float) $this->amount + (float) ($this->endorsements_sum_amount ?? $this->endorsements->sum('amount'));
    }

    /**
     * El IVA que ya viene dentro del total.
     *
     * Los montos se capturan como se pagan, o sea con impuesto incluido, así
     * que aquí se desglosa: no se le suma nada.
     */
    public function tax(): float
    {
        return round($this->total() * self::TAX_RATE / (1 + self::TAX_RATE), 2);
    }

    /** El total sin IVA. */
    public function subtotal(): float
    {
        return round($this->total() - $this->tax(), 2);
    }

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /** Los endosos de extensión, del primero al último. */
    public function endorsements(): HasMany
    {
        return $this->hasMany(ConstructionPolicyEndorsement::class)->orderBy('valid_until')->orderBy('id');
    }

    /** Documentos de la póliza, aparte de los de sus endosos. */
    public function documents(): HasMany
    {
        return $this->hasMany(ConstructionPolicyDocument::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
