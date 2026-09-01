<?php

namespace App\Services;

use App\Models\Lodge;

class LodgeModuleExecutionGuard
{
    public function __construct(private readonly LodgeModuleState $states)
    {
    }

    /**
     * Queue and scheduled callers must use this at execution time, not when work is queued.
     * The result is deliberately explicit so a disabled module is a safe skip, not a failure.
     *
     * @return array{status: 'ready'|'skipped_ineffective', lodge: Lodge|null}
     */
    public function check(int|Lodge $lodge, string $module): array
    {
        $lodge = $lodge instanceof Lodge ? $lodge->fresh() : Lodge::find($lodge);

        if (! $lodge || ! $this->states->isEffective($lodge, $module)) {
            return ['status' => 'skipped_ineffective', 'lodge' => $lodge];
        }

        return ['status' => 'ready', 'lodge' => $lodge];
    }
}
