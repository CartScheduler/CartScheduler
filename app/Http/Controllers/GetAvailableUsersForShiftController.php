<?php

namespace App\Http\Controllers;

use App\Actions\GetAvailableUsersForShift;
use App\Data\ExtendedUserData;
use App\Models\Shift;
use Illuminate\Http\Request;

class GetAvailableUsersForShiftController extends Controller
{
    public function __construct(private readonly GetAvailableUsersForShift $getUsersForShift)
    {
    }

    public function __invoke(Request $request, Shift $shift)
    {
        $request->validate([
            'date'                        => ['required', 'date'],
            'showAll'                     => ['nullable', 'boolean'],
            'showOnlyResponsibleBros'     => ['nullable', 'boolean'],
            'hidePublishers'              => ['nullable', 'boolean'],
            'showOnlyElders'              => ['nullable', 'boolean'],
            'showOnlyMinisterialServants' => ['nullable', 'boolean'],
        ]);

        return ExtendedUserData::collect(
            $this->getUsersForShift->execute(
                shift: $shift,
                date: $request->date('date'),
                showOnlyAvailable: !$request->boolean('showAll'),
                showOnlyResponsibleBros: $request->boolean('showOnlyResponsibleBros'),
                hidePublishers: $request->boolean('hidePublishers'),
                showOnlyElders: $request->boolean('showOnlyElders'),
                showOnlyMinisterialServants: $request->boolean('showOnlyMinisterialServants'),
            ),
        );
    }
}
