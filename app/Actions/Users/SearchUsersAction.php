<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchUsersAction
{
    public function execute(string $query, ?string $excludeUserId = null, int $perPage = 15): LengthAwarePaginator
    {
        $normalized = mb_strtolower(trim($query));
        $contains = '%'.$this->escapeLike($normalized).'%';
        $collapsed = preg_replace('/(.)\1+/u', '$1', $normalized) ?: $normalized;
        $containsCollapsed = '%'.$this->escapeLike($collapsed).'%';
        $loose = mb_strlen($normalized) >= 3 ? $this->looseLikePattern($normalized) : null;
        $looseMaxLen = mb_strlen($normalized) + 8;

        return User::query()
            ->with('profile')
            ->where('status', UserStatus::Active)
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->where(function ($builder) use ($contains, $containsCollapsed, $loose, $looseMaxLen): void {
                $builder
                    ->where(function ($exact) use ($contains, $containsCollapsed): void {
                        $exact->whereRaw('LOWER(username) LIKE ? ESCAPE \'!\'', [$contains])
                            ->orWhereRaw('LOWER(username) LIKE ? ESCAPE \'!\'', [$containsCollapsed])
                            ->orWhereHas('profile', function ($profileQuery) use ($contains, $containsCollapsed): void {
                                $profileQuery->whereRaw('LOWER(display_name) LIKE ? ESCAPE \'!\'', [$contains])
                                    ->orWhereRaw('LOWER(display_name) LIKE ? ESCAPE \'!\'', [$containsCollapsed]);
                            });
                    })
                    ->when($loose, function ($outer) use ($loose, $looseMaxLen): void {
                        $outer->orWhere(function ($fuzzy) use ($loose, $looseMaxLen): void {
                            $fuzzy->whereRaw('LENGTH(username) <= ?', [$looseMaxLen])
                                ->whereRaw('LOWER(username) LIKE ? ESCAPE \'!\'', [$loose]);
                        })->orWhereHas('profile', function ($profileQuery) use ($loose, $looseMaxLen): void {
                            $profileQuery->whereRaw('LENGTH(display_name) <= ?', [$looseMaxLen])
                                ->whereRaw('LOWER(display_name) LIKE ? ESCAPE \'!\'', [$loose]);
                        });
                    });
            })
            ->orderByRaw(
                'CASE
                    WHEN LOWER(username) LIKE ? ESCAPE \'!\' THEN 0
                    WHEN LOWER(username) LIKE ? ESCAPE \'!\' THEN 1
                    ELSE 2
                 END',
                [$contains, $containsCollapsed],
            )
            ->orderBy('username')
            ->paginate($perPage);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * Motif « sous-séquence » : gary → %g%a%r%y% (tolère lettres en trop, ex. garry).
     */
    private function looseLikePattern(string $normalized): string
    {
        $chars = preg_split('//u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return '%'.implode('%', array_map(fn (string $char) => $this->escapeLike($char), $chars)).'%';
    }
}
