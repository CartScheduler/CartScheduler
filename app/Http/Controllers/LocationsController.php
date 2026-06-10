<?php

namespace App\Http\Controllers;

use App\Data\LocationAdminData;
use App\Http\Requests\CreateLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class LocationsController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Location::class, 'location');
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Locations/List', [
            'locations' => LocationAdminData::collect(Location::with('shifts')->get()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Locations/Add', [
            'maxVolunteers' => config('cart-scheduler.max_volunteers_per_location'),
        ]);
    }

    /**
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function store(CreateLocationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::beginTransaction();
        $shifts = $request->validated('shifts');
        unset($data['shifts']);
        $location = Location::create($data);
        foreach ($shifts as $shift) {
            $shiftModel = new Shift();
            $shiftModel->fill($shift);
            $shiftModel->location_id = $location->id;
            $shiftModel->save();
        }
        DB::commit();

        session()->flash('flash.banner', "Location $location->name successfully created.");
        session()->flash('flash.bannerStyle', 'success');

        return Redirect::route('admin.locations.edit', $location);
    }

    public function edit(Location $location): Response
    {
        return Inertia::render('Admin/Locations/Edit', [
            'maxVolunteers' => config('cart-scheduler.max_volunteers_per_location'),
            'location'      => LocationAdminData::from($location->load([
                'shifts' => function ($query) {
                    $query->orderBy('start_time');
                },
            ])),
        ]);
    }

    /**
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     * @throws \RuntimeException
     */
    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        DB::beginTransaction();
        $shifts = $request->validated('shifts');
        // TODO implement the deletion of volunteer shifts when a shift or a location has been disabled. Admin should be notified of this.
        // TODO Note the shift class that has an observer that will delete the volunteer shifts when a shift is
        // TODO restricted by date. A similar mechanism could be used. Also do the same for when a volunteer
        // TODO is 'deactivated'. Also, add some info on the front-end so 'admin' knows that this will happen
        foreach ($shifts as $shift) {
            if (isset($shift['id'])) {
                $shiftModel = Shift::find($shift['id']);
                if (!$shiftModel) {
                    // @codeCoverageIgnoreStart
                    throw new RuntimeException("Shift with an ID of {$shift['id']} belonging to $location->name not found");
                    // @codeCoverageIgnoreEnd
                }
                unset($shift['id']);
            } else {
                $shiftModel = new Shift();
            }
            $shiftModel->fill($shift);
            $shiftModel->save();
        }
        $locationData = $request->validated();
        unset($locationData['shifts']);
        $location->update($locationData);
        DB::commit();

        return Redirect::route('admin.locations.edit', $location);
        //return Redirect::route('admin.locations.edit', $location, \Illuminate\Http\Response::HTTP_SEE_OTHER);
    }

    public function destroy(Location $location): RedirectResponse
    {
        $name = $location->name;
        $location->delete();

        session()->flash('flash.banner', "Location $name successfully deleted.");
        session()->flash('flash.bannerStyle', 'warn');

        return Redirect::route('admin.locations.index');
    }
}
