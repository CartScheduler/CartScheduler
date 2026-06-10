<?php

namespace App\Actions;

use App\Data\OutstandingReportsData;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetOutstandingReports
{
    /**
     * @return Collection<int, OutstandingReportsData>
     */
    public function execute(?User $user = null): Collection
    {
        $reports = DB::query()
            ->select([
                'su.shift_id',
                'su.shift_date',
                'shifts.start_time',
                'shifts.end_time',
                'locations.requires_brother',
                'locations.name as location_name',
            ])
            ->when($user !== null, fn(Builder $query) => $query->addSelect('su.user_id'))
            ->from('shift_user', 'su')
            ->join('shifts', 'shifts.id', '=', 'su.shift_id')
            ->join('locations', 'locations.id', '=', 'shifts.location_id')
            ->leftJoin('reports as r', fn(JoinClause $join) => $join
                ->on('r.shift_id', '=', 'su.shift_id')
                ->on('r.shift_date', '=', 'su.shift_date')
            )
            ->whereTodayOrBefore('su.shift_date')
            ->whereNull('r.shift_id') // Makes sure there are no reports
            ->when($user !== null, fn(Builder $query) => $query->where('user_id', $user->id))
            ->when($user === null, fn(Builder $query) => $query->groupBy([
                'su.shift_id',
                'su.shift_date',
                'shifts.start_time',
                'shifts.end_time',
                'locations.requires_brother',
                'locations.name',
            ]))
            ->orderBy('su.shift_date')
            ->orderBy('shifts.start_time')
            ->get();

        return OutstandingReportsData::collect($reports);
    }
}
