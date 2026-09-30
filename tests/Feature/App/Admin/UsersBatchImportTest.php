<?php

use App\Exports\UsersExport;
use App\Mail\UserAccountCreated;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use League\Csv\Reader;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

test('only admin can load import users page', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();
    $user = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->get('/admin/users/import')
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson('/admin/users/import')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Users/Import')
        )
        ->assertOk();
});

test('admin can batch import three users and users receive emails', function () {
    $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();

    Mail::fake();
    $this->actingAs($admin)
        ->postJson('/admin/users/import',
            ['file' => new UploadedFile(
                path: base_path('tests/assets/test_admin_can_batch_import_three_users_and_users_receive_emails.xlsx'),
                originalName: 'test_admin_can_batch_import_three_users_and_users_receive_emails.xlsx',
                test: true,
            )],
            ['Content-Type' => 'multipart/form-data'],
        )
        ->assertRedirect();

    $this->assertDatabaseCount('users', 4);
    $uploaded = User::with('spouse')->where('id', '!=', $admin->getKey())->get();
    expect($uploaded[0]->name)->toEqual('Fee Smee');
    expect($uploaded[1]->name)->toEqual('Lee Smee');
    expect($uploaded[2]->name)->toEqual('Gee Smee');
    expect($uploaded[1]->spouse->getKey())->toBe($uploaded[0]->getKey());
    expect($uploaded[0]->spouse->getKey())->toBe($uploaded[1]->getKey());
    expect($uploaded[2]->spouse)->toBeNull();

    Mail::assertSent(UserAccountCreated::class, 3);
});

test('admin batch import handles failed validation properly', function () {
    $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
    $this->assertDatabaseCount('users', 1);

    Mail::fake();

    $this->travelTo('2024-06-01');
    $this->actingAs($admin)
        ->postJson('/admin/users/import',
            ['file' => new UploadedFile(
                path: base_path('tests/assets/test_admin_batch_import_fails_properly.xlsx'),
                originalName: 'test_admin_batch_import_fails_properly.xlsx',
                test: true,
            )],
            ['Content-Type' => 'multipart/form-data'],
        )
        ->assertUnprocessable()
        ->assertJsonFragment(['errors' => [
            ['Row 3 - The year_of_birth field must be at least 1924.'],
            ['Row 3 - The selected serving_as is invalid.'],
            ['Row 3 - The selected marital_status is invalid.'],
            ['Row 3 - The responsible_brother field must be true or false.'],
            ['Row 3 - The is_unrestricted field must be true or false.'],
            ['Row 4 - The selected serving_as is invalid.'],
            ['Row 4 - The year_of_birth field must be at least 1924.'],
        ]]);

    $this->assertDatabaseCount('users', 1);

    Mail::assertNothingOutgoing();
});

test('admin batch import handles duplicate users properly', function () {
    $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();

    // The next user will be duplicated in the import and should be updated
    User::factory()->female()->state(['id' => 2, 'email' => 'lee@example.com', 'is_enabled' => true])->create();

    Mail::fake();
    $this->actingAs($admin)
        ->postJson('/admin/users/import',
            ['file' => new UploadedFile(
                path: base_path('tests/assets/test_admin_batch_import_handles_duplicate_users_properly.xlsx'),
                originalName: 'test_admin_batch_import_handles_duplicate_users_properly.xlsx',
                test: true,
            )],
            ['Content-Type' => 'multipart/form-data'],
        )
        ->assertRedirect();

    $uploaded = User::with('spouse')->where('id', '!=', $admin->getKey())->get();
    $this->assertDatabaseCount('users', 3);
    expect($uploaded[0]->name)->toBe('Existing User');
    expect($uploaded[1]->name)->toBe('New User');
    expect($uploaded[1]->spouse->getKey())->toBe($uploaded[0]->getKey());
    expect($uploaded[0]->spouse->getKey())->toBe($uploaded[1]->getKey());

    // Should only be one email sent because the duplicate user was updated
    Mail::assertSent(UserAccountCreated::class, 1);
});

test('admin can download user import spreadsheet template', function () {
    GeneralSettings::fake(['siteName' => 'Test Site']);
    Excel::fake();

    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
    User::factory()->enabled()->count(5)->create();

    $this->actingAs($admin)
        ->get('/admin/users-import-template')
        ->assertOk();

    $filename = Str::of('Test Site')->snake()->append('-user_import_template.xlsx');
    Excel::assertDownloaded($filename);
});

test('admin can download user export spreadsheet', function () {
    $carbon = Carbon::createFromTimeString('2023-01-01 12:00:00');
    Carbon::setTestNow($carbon);
    GeneralSettings::fake(['siteName' => 'Test Site']);
    Excel::fake();

    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
    $this->actingAs($admin)
        ->get('/admin/users-as-spreadsheet')
        ->assertOk();

    $filename = Str::of('Test Site')
        ->snake()
        ->append('-user_dump_')
        ->append($carbon->format('Y-m-d_His'))
        ->append('.xlsx');

    Excel::assertDownloaded($filename);
});

test('users export returns correct data', function () {
    GeneralSettings::fake(['siteName' => 'Test Site']);
    User::factory()->enabled()->count(5)->create();
    $export = new UsersExport;
    $result = Excel::raw($export, Maatwebsite\Excel\Excel::CSV);

    $csvReader = Reader::createFromString($result);

    $csvReader->setHeaderOffset(1);
    $csvReader->each(function (array $row, int $index) {
        if ($index < 1) {
            checkCsvHeader($row);

            return;
        }

        $this->assertDatabaseHas('users', ['name' => $row['NAME'], 'email' => $row['EMAIL']]);
    });
});

function checkCsvHeader(array $header): void
{
    $values = [
        'NAME',
        'EMAIL',
        'MOBILE PHONE',
        'GENDER',
        'YEAR OF BIRTH',
        'APPOINTMENT',
        'SERVING AS',
        'MARITAL STATUS',
        'SPOUSE EMAIL',
        'SPOUSE ID',
        'RESPONSIBLE BROTHER',
        'IS UNRESTRICTED',
    ];

    foreach ($values as $value) {
        expect($header)->toHaveKey($value);
    }
}
