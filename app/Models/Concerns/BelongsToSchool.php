<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keeps each school's data separate: queries only see the current school's rows,
 * and new rows are stamped with the current school's id.
 */
trait BelongsToSchool
{
    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $q) {
            if (Tenant::id()) {
                $q->where($q->getModel()->getTable().'.school_id', Tenant::id());
            }
        });

        static::creating(function ($model) {
            if (! $model->school_id) {
                $model->school_id = Tenant::id();
            }
            if (! $model->school_id) {
                throw new \LogicException(static::class.' created without a school.');
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
