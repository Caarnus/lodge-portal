<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LodgeModuleStateChanged
{
    use Dispatchable, SerializesModels;

    /** @param array{is_available: bool, is_enabled: bool, is_effective: bool} $before
     *  @param array{is_available: bool, is_enabled: bool, is_effective: bool} $after */
    public function __construct(
        public readonly int $lodgeId,
        public readonly string $moduleKey,
        public readonly array $before,
        public readonly array $after,
        public readonly int $actorId,
        public readonly string $control,
    ) {
    }
}
