<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class Feature extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (self $feature): void {
            if ($feature->isDirty('key')) {
                throw new LogicException('Module definition keys are immutable.');
            }
        });

        // Definitions are release data. Retire them instead so assignment and audit history
        // always retains a valid module reference.
        static::deleting(fn (): never => throw new LogicException('Module definitions cannot be deleted. Retire the definition instead.'));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
