<?php

namespace App\Http\Controllers;

use App\Data\SpouseAdminData;
use App\Http\Requests\UpdateUserSpouseRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UpdateUserSpouseController extends Controller
{
    /**
     * Attaches or detaches a spouse on its own, leaving the rest of the edit form unsaved.
     * The reciprocal link on the other user is kept up to date by the model's own events.
     */
    public function __invoke(UpdateUserSpouseRequest $request, User $user): JsonResponse
    {
        $spouseId = $request->validated('spouse_id');

        $user->spouse_id = $spouseId;

        if ($spouseId) {
            // Holding a spouse is what says a user is married, as it does for the spouse themselves
            $user->marital_status = 'married';
        }

        $user->save();
        $user->refresh();

        return response()->json([
            'spouse' => $user->spouse ? SpouseAdminData::from($user->spouse) : null,
        ]);
    }
}
