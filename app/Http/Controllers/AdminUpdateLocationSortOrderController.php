<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUpdateLocationSortOrderController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'locations' => ['required', 'array'],
            'locations.*' => ['required', 'integer'],
        ]);

        $newSortOrder = $request->get('locations');
        DB::transaction(static function () use ($newSortOrder) {
            foreach ($newSortOrder as $index => $id) {
                Location::findOrFail($id)->update(['sort_order' => $index]);
            }
        });
    }
}
