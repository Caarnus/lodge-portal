<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['email' => 'string'];
    }

    public function setEmailAttribute(?string $email): void
    {
        $this->attributes['email'] = $email === null ? null : strtolower($email);
    }
}
