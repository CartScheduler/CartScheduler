<?php

namespace App\Actions;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class GetOutstandingReportCount
{
    public function execute(): int
    {
        return DB::query()
            ->distinct()
            ->select('su.shift_date', 'su.shift_id')
            ->from('shift_user', 'su')
            ->leftJoin('reports as r', fn(JoinClause $join) => $join
                ->on('r.shift_id', '=', 'su.shift_id')
                ->on('r.shift_date', '=', 'su.shift_date')
            )
            ->whereTodayOrBefore('su.shift_date')
            ->whereNull('r.shift_id') // Makes sure there are no reports
            ->get()
            ->count();
    }
}
