<?php

namespace App\Models;

use Base\Tenant\Models\User as BaseTenantUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends BaseTenantUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return \Illuminate\Support\Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => \Illuminate\Support\Str::substr($word, 0, 1))
            ->implode('');
    }
}
