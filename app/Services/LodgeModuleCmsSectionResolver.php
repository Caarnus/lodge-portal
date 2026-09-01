<?php

namespace App\Services;

use App\Models\Lodge;

class LodgeModuleCmsSectionResolver
{
    public function __construct(private readonly LodgeModuleState $states)
    {
    }

    /**
     * Module section implementations pass the public lodge explicitly. The fallback is
     * section-defined (usually omission) and never alters the stored page or section.
     */
    public function resolve(Lodge $lodge, string $module, callable $section, mixed $unavailable = null): mixed
    {
        return $this->states->isEffective($lodge, $module) ? $section() : $unavailable;
    }
}
