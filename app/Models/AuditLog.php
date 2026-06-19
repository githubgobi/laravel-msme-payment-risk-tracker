<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Immutable — no updates, no soft deletes
    public $timestamps = false;
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'model_type',
        'model_id',
        'event',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event'      => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function record(
        string $modelType,
        int $modelId,
        AuditEvent $event,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): self {
        return static::create([
            'tenant_id'  => auth()->user()?->tenant_id,
            'user_id'    => auth()->id(),
            'model_type' => $modelType,
            'model_id'   => $modelId,
            'event'      => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
