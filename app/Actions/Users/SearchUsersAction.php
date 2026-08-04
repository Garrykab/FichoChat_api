<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchUsersAction
{
    public function execute(string $query, ?string $excludeUserId = null, int $perPage = 15): LengthAwarePaginator
    {
        $term = '%'.mb_strtolower(trim($query)).'%';

        return User::query()
            ->with('profile')
            ->where('status', UserStatus::Active)
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->where(function ($builder) use ($term): void {
                $builder->whereRaw('LOWER(username) LIKE ?', [$term])
                    ->orWhereHas('profile', function ($profileQuery) use ($term): void {
                        $profileQuery->whereRaw('LOWER(display_name) LIKE ?', [$term]);
                    });
            })
            ->orderBy('username')
            ->paginate($perPage);
    }
}
