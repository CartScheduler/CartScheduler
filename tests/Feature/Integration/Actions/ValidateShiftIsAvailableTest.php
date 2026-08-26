<?php

use App\Actions\ValidateShiftIsAvailableAction;
use App\Exceptions\ShiftAvailabilityException;
use App\Models\Location;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->validateShiftIsAvailableAction = new ValidateShiftIsAvailableAction;
});

test('dates within shift availability are working', function () {
    $startDate = Carbon::createFromTimeString('2023-10-01T12:00:00');
    $endDate = $startDate->clone()->endOfMonth();

    $location = Location::factory()->create();

    $shift = Shift::factory()
        ->everyDay9am()
        ->state([
            'available_from' => $startDate->toDateString(),
            'available_to' => $endDate->toDateString(),
        ])
        ->for($location)
        ->create();

    $this->expectNotToPerformAssertions();
    $this->validateShiftIsAvailableAction->execute($shift, $startDate);
    $this->validateShiftIsAvailableAction->execute($shift, $startDate->addDay());
    $this->validateShiftIsAvailableAction->execute($shift, $startDate->setDay(15));
    $this->validateShiftIsAvailableAction->execute($shift, $startDate->endOfMonth());
    $this->validateShiftIsAvailableAction->execute($shift, $startDate->subDay());
});

test('dates before and after shift availability are failing', function () {
    $startDate = Carbon::createFromTimeString('2023-10-01T12:00:00');
    $endDate = $startDate->clone()->endOfMonth();

    $location = Location::factory()->create();

    $shift = Shift::factory()
        ->everyDay9am()
        ->state([
            'available_from' => $startDate->toDateString(),
            'available_to' => $endDate->toDateString(),
        ])
        ->for($location)
        ->create();

    $this->assertThrows(
        fn () => $this->validateShiftIsAvailableAction->execute($shift, $startDate->clone()->subDay()),
        ShiftAvailabilityException::class,
        ShiftAvailabilityException::notAvailableYet()->getMessage()
    );

    $this->assertThrows(
        fn () => $this->validateShiftIsAvailableAction->execute($shift, $startDate->clone()->endOfMonth()->addDay()),
        ShiftAvailabilityException::class,
        ShiftAvailabilityException::notAvailableAnymore()->getMessage()
    );
});
