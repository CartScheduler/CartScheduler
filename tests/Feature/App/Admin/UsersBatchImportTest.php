<?php

namespace Tests\Feature\App\Admin;

use App\Exports\UsersExport;
use App\Mail\UserAccountCreated;
use App\Models\User;
use App\Settings\GeneralSettings;
use DB;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use League\Csv\Reader;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class UsersBatchImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_load_import_users_page(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $user  = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->get("/admin/users/import")
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson("/admin/users/import")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Users/Import')
            )
            ->assertOk();
    }

    public function test_admin_can_batch_import_three_users_and_users_receive_emails(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();

        Mail::fake();
        $this->actingAs($admin)
            ->postJson("/admin/users/import",
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
        $this->assertEquals('Fee Smee', $uploaded[0]->name);
        $this->assertEquals('Lee Smee', $uploaded[1]->name);
        $this->assertEquals('Gee Smee', $uploaded[2]->name);
        $this->assertSame($uploaded[0]->getKey(), $uploaded[1]->spouse->getKey());
        $this->assertSame($uploaded[1]->getKey(), $uploaded[0]->spouse->getKey());
        $this->assertNull($uploaded[2]->spouse);

        Mail::assertSent(UserAccountCreated::class, 3);
    }

    public function test_admin_batch_import_handles_failed_validation_properly(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $this->assertDatabaseCount('users', 1);

        Mail::fake();

        $this->travelTo('2024-06-01');
        $this->actingAs($admin)
            ->postJson("/admin/users/import",
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
    }

    public function test_admin_batch_import_handles_duplicate_users_properly(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        // The next user will be duplicated in the import and should be updated
        User::factory()->female()->state(['id' => 2, 'email' => 'lee@example.com', 'is_enabled' => true])->create();

        Mail::fake();
        $this->actingAs($admin)
            ->postJson("/admin/users/import",
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
        $this->assertSame('Existing User', $uploaded[0]->name);
        $this->assertSame('New User', $uploaded[1]->name);
        $this->assertSame($uploaded[0]->getKey(), $uploaded[1]->spouse->getKey());
        $this->assertSame($uploaded[1]->getKey(), $uploaded[0]->spouse->getKey());

        // Should only be one email sent because the duplicate user was updated
        Mail::assertSent(UserAccountCreated::class, 1);
    }

    public function test_admin_can_download_user_import_spreadsheet_template(): void
    {
        GeneralSettings::fake(['siteName' => 'Test Site']);
        Excel::fake();

        $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
        User::factory()->enabled()->count(5)->create();

        $this->actingAs($admin)
            ->get("/admin/users-import-template")
            ->assertOk();

        $filename = Str::of('Test Site')->snake()->append('-user_import_template.xlsx');
        Excel::assertDownloaded($filename);
    }

    public function test_admin_can_download_user_export_spreadsheet(): void
    {
        $carbon = Carbon::createFromTimeString('2023-01-01 12:00:00');
        Carbon::setTestNow($carbon);
        GeneralSettings::fake(['siteName' => 'Test Site']);
        Excel::fake();

        $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
        $this->actingAs($admin)
            ->get("/admin/users-as-spreadsheet")
            ->assertOk();

        $filename = Str::of('Test Site')
            ->snake()
            ->append('-user_dump_')
            ->append($carbon->format('Y-m-d_His'))
            ->append('.xlsx');

        Excel::assertDownloaded($filename);
    }

    public function test_users_export_returns_correct_data(): void
    {
        GeneralSettings::fake(['siteName' => 'Test Site']);
        User::factory()->enabled()->count(5)->create();
        $export = new UsersExport();
        $result = Excel::raw($export, \Maatwebsite\Excel\Excel::CSV);

        $csvReader = Reader::createFromString($result);

        $csvReader->setHeaderOffset(1);
        $csvReader->each(function (array $row, int $index) {
            if ($index < 1) {
                $this->checkCsvHeader($row);
                return;
            }

            $this->assertDatabaseHas('users', ['name' => $row['NAME'], 'email' => $row['EMAIL']]);
        });
    }

    private function checkCsvHeader(array $header): void
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
            $this->assertArrayHasKey($value, $header);
        }
    }
}
