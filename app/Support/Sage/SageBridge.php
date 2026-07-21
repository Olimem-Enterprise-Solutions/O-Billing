<?php

declare(strict_types=1);

namespace App\Support\Sage;

use App\Models\SageOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records a Sage bridge operation and queues its job onto the `sage` queue for
 * the on-site worker to run. Used by the Filament actions in place of calling
 * the Sage services inline (which the cloud app cannot do — it has no Sage
 * connection).
 */
final class SageBridge
{
    /**
     * @param  class-string<\App\Jobs\Sage\SageJob>  $job
     * @param  array<string,mixed>  $params
     */
    public static function queue(string $type, string $job, ?Model $subject = null, array $params = []): SageOperation
    {
        $operation = new SageOperation([
            'type' => $type,
            'status' => SageOperation::STATUS_QUEUED,
            'user_id' => Auth::id(),
            'params' => $params,
        ]);

        if ($subject !== null) {
            $operation->subject()->associate($subject); // stamps subject_type / subject_id
        }

        $operation->save(); // municipality_id stamped by BelongsToMunicipality

        $job::dispatch($operation);

        return $operation;
    }
}
