<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Response;

class DeleteShiftsController extends Controller
{
    public function __invoke(Shift $shift)
    {
        $shift->delete();

        return response('', Response::HTTP_NO_CONTENT);
    }
}
