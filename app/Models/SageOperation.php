<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMunicipality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A single Sage bridge operation queued for the on-site worker. The cloud UI
 * creates one when the user triggers an import/preview/post/push, then polls it
 * (and receives a notification) for progress and results — the work itself runs
 * asynchronously on the worker next to the council's Sage database.
 */
class SageOperation extends Model
{
    use BelongsToMunicipality;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'municipality_id', 'user_id', 'type', 'status',
        'subject_type', 'subject_id', 'params', 'result', 'message',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The record this operation acts on (BillingRun, Invoice, Customer, …), if any. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function markRunning(): void
    {
        $this->forceFill(['status' => self::STATUS_RUNNING, 'started_at' => Carbon::now()])->save();
    }

    /** @param array<string,mixed>|null $result */
    public function markDone(?array $result = null, ?string $message = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_DONE,
            'result' => $result,
            'message' => $message,
            'finished_at' => Carbon::now(),
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'message' => $message,
            'finished_at' => Carbon::now(),
        ])->save();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
