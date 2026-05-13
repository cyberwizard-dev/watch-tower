<?php

namespace App\Watch\Stats;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class StatsPaginator
{
    /**
     * Sort an in-memory list, then paginate it, then wrap in a LengthAwarePaginator
     * so the response shape matches Laravel pagination (links, from, to, etc.).
     *
     * @template T of array<string, mixed>
     *
     * @param  list<T>  $items
     * @param  array<string, 'numeric'|'string'>  $sortable  Map of sort key => type
     * @return LengthAwarePaginator<array-key, T>
     */
    public static function paginate(
        array $items,
        array $sortable,
        ?string $sort,
        ?string $dir,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $sortKey = $sort !== null && array_key_exists($sort, $sortable) ? $sort : null;
        $direction = $dir === 'asc' ? 'asc' : 'desc';

        if ($sortKey !== null) {
            $type = $sortable[$sortKey];
            usort($items, function (array $a, array $b) use ($sortKey, $type, $direction): int {
                $av = $a[$sortKey] ?? null;
                $bv = $b[$sortKey] ?? null;
                if ($av === null && $bv === null) {
                    return 0;
                }
                if ($av === null) {
                    return 1;
                }
                if ($bv === null) {
                    return -1;
                }
                $cmp = $type === 'numeric'
                    ? ($av <=> $bv)
                    : strcmp((string) $av, (string) $bv);

                return $direction === 'asc' ? $cmp : -$cmp;
            });
        }

        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($items, $offset, $perPage);

        $paginator = new LengthAwarePaginator(
            $paged,
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return $paginator->withQueryString();
    }
}
