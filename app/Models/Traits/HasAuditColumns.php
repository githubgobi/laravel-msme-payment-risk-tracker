<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasAuditColumns
{
    public static function bootHasAuditColumns(): void
    {
        static::creating(function ($model) {
            if (! $model->created_by && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (! $model->updated_by && auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
