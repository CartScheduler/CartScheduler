<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Tags\Tag;

class ReportTagsSortOrderController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
        ]);

        $ids = $request->get('ids');
        Tag::setNewOrder($ids);

        return response('', 204);
    }
}
