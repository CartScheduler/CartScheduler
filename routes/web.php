<?php

use App\Http\Controllers\Admin\Reports\UserAvailabilityReportController;
use App\Http\Controllers\AdminAvailableShiftsController;
use App\Http\Controllers\AdminCheckForUpdateController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminRunSoftwareUpdateController;
use App\Http\Controllers\AdminUpdateLocationSortOrderController;
use App\Http\Controllers\AvailableShiftsController;
use App\Http\Controllers\DeleteShiftsController;
use App\Http\Controllers\DownloadUserImportSpreadsheetController;
use App\Http\Controllers\DownloadUsersAsSpreadsheetController;
use App\Http\Controllers\ExportReportsController;
use App\Http\Controllers\ExportShiftAssignmentsController;
use App\Http\Controllers\ExportUserAvailabilitiesController;
use App\Http\Controllers\ExportUserShiftCountsController;
use App\Http\Controllers\GetAdminUsersController;
use App\Http\Controllers\GetAvailableUsersForShiftController;
use App\Http\Controllers\GetReportTagsController;
use App\Http\Controllers\GetUserLocationChoicesController;
use App\Http\Controllers\LocationsController;
use App\Http\Controllers\MissingReportsForUserController;
use App\Http\Controllers\MoveUserToNewShiftController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReportTagsController;
use App\Http\Controllers\ReportTagsSortOrderController;
use App\Http\Controllers\ResendWelcomeEmailController;
use App\Http\Controllers\SaveShiftReportController;
use App\Http\Controllers\SetUserPasswordController;
use App\Http\Controllers\ShowExportsController;
use App\Http\Controllers\ShowGeneralSettingsController;
use App\Http\Controllers\ShowUserAvailabilityController;
use App\Http\Controllers\ToggleShiftReservationController;
use App\Http\Controllers\ToggleUserOntoShiftReservationController;
use App\Http\Controllers\UpdateAllowedSettingsUsersController;
use App\Http\Controllers\UpdateGeneralSettingsController;
use App\Http\Controllers\UpdateUserLocationsChoicesController;
use App\Http\Controllers\UpdateUserRegularAvailabilityController;
use App\Http\Controllers\UpdateUserVacationsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\UsersImportController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/*Route::get('/', static function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});*/

Route::get('/', static fn () => Inertia::render('Auth/Login'));

Route::get('/set-password/{user}/{token}', [SetUserPasswordController::class, 'show'])->name('set.password.show');
Route::post('/set-password', [SetUserPasswordController::class, 'update'])->name('set.password.update');

// Route::get('/mail', static fn() => new App\Mail\UserAccountCreated(App\Models\User::find(1)));

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'user-enabled'])->group(function () {
    Route::get('/', static fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/shifts/{shiftDate}', AvailableShiftsController::class)->where(['shiftDate' => '\d\d\d\d-\d\d-\d\d'])->name('shifts');
    Route::get('/outstanding-reports', MissingReportsForUserController::class)->name('outstanding-reports');
    Route::post('/reserve-shift', ToggleShiftReservationController::class)->name('reserve.shift');
    Route::post('/save-report', SaveShiftReportController::class)->name('save.report');
    Route::get('/get-report-tags', GetReportTagsController::class)->name('get.report-tags');
    Route::post('/set-viewed-availability', static function (Request $request) {
        $user = $request->user();
        if (! $user->availability) {
            $user->load('availability');
        }
        $user->availability->touch();
    })->name('set.viewed-availability');

    // Device-level display settings, kept apart from the account-level profile
    // page because nothing here is saved to the account.
    Route::get('/user/preferences', static fn () => Inertia::render('Profile/Preferences'))->name('user.preferences');

    Route::get('/user/availability', ShowUserAvailabilityController::class)->name('user.availability');
    Route::put('/user/availability', UpdateUserRegularAvailabilityController::class)->name('update.user.availability');
    Route::put('/user/vacations', UpdateUserVacationsController::class)->name('update.user.vacations');

    Route::get('/user/available-locations', GetUserLocationChoicesController::class)->name('user.location-choices');
    Route::put(
        '/user/available-locations',
        UpdateUserLocationsChoicesController::class
    )->name('update.user.location-choices');

    Route::prefix('admin')
        ->middleware('is-admin')
        ->group(static function () {
            Route::get('/', AdminDashboardController::class)->name('admin.dashboard');

            Route::get('/users/import', [UsersImportController::class, 'show'])->name('admin.users.import.show');
            Route::post('/users/import', [UsersImportController::class, 'import'])->name('admin.users.import.import');

            Route::group(['middleware' => HandlePrecognitiveRequests::class], static function () {
                Route::resource('/users', UsersController::class)->names([
                    'index' => 'admin.users.index',
                    'create' => 'admin.users.create',
                    'store' => 'admin.users.store',
                    'show' => 'admin.users.show',
                    'edit' => 'admin.users.edit',
                    'update' => 'admin.users.update',
                    'destroy' => 'admin.users.destroy',
                ]);
            });

            Route::put('/locations/sort-order', AdminUpdateLocationSortOrderController::class)
                ->name('admin.locations.sort-order');

            Route::resource('/locations', LocationsController::class)->names([
                'index' => 'admin.locations.index',
                'create' => 'admin.locations.create',
                'store' => 'admin.locations.store',
                'show' => 'admin.locations.show',
                'edit' => 'admin.locations.edit',
                'update' => 'admin.locations.update',
                'destroy' => 'admin.locations.destroy',
            ]);

            Route::resource('/reports', ReportsController::class)->names([
                'index' => 'admin.reports.index',
            ])->only(['index']);

            // New user availability report endpoint
            Route::get('/reporting/users-availability',
                UserAvailabilityReportController::class)->name('admin.reports.users-availability');

            Route::resource('/report-tags', ReportTagsController::class)->parameter('report-tags', 'tag')->names([
                'index' => 'admin.report-tags.index',
                'store' => 'admin.report-tags.store',
                'update' => 'admin.report-tags.update',
                'destroy' => 'admin.report-tags.destroy',
            ])->except(['show', 'edit', 'create']);
            Route::put('/report-tag-sort-order', ReportTagsSortOrderController::class)->name('admin.report-tag.sort-order');
            Route::post(
                '/resend-welcome-email',
                ResendWelcomeEmailController::class
            )->name('admin.resend-welcome-email');

            Route::get(
                '/assigned-shifts/{shiftDate}',
                AdminAvailableShiftsController::class
            )->where(['shiftDate' => '\d\d\d\d-\d\d-\d\d'])
                ->name('admin.assigned-shifts');

            Route::delete('/shifts/{shift}', DeleteShiftsController::class)->name('admin.shifts.destroy');

            Route::put('/move-volunteer-to-shift', MoveUserToNewShiftController::class)->name('admin.move-volunteer-to-shift');

            Route::get('/available-users-for-shift/{shift}', GetAvailableUsersForShiftController::class)->name('admin.available-users-for-shift');
            Route::match(['put', 'delete'], '/toggle-shift-for-user', ToggleUserOntoShiftReservationController::class);

            Route::get('/settings', ShowGeneralSettingsController::class)->name('admin.settings');

            Route::put(
                '/general-settings',
                UpdateGeneralSettingsController::class
            )->name('admin.general-settings.update');
            Route::put(
                '/allowed-settings-users',
                UpdateAllowedSettingsUsersController::class
            )->name('admin.allowed-settings-users.update');
            Route::get('/admin-users', GetAdminUsersController::class)->name('admin.admin-users.get');
            Route::get('/check-update', AdminCheckForUpdateController::class)->name('admin.check-update');
            Route::post('/do-update', AdminRunSoftwareUpdateController::class)->name('admin.do-update');
            Route::get(
                '/users-as-spreadsheet',
                DownloadUsersAsSpreadsheetController::class
            )->name('admin.users-as-spreadsheet');
            Route::get(
                '/users-import-template',
                DownloadUserImportSpreadsheetController::class
            )->name('admin.user-import-template');

            Route::get('/exports', ShowExportsController::class)->name('admin.exports');
            Route::get('/exports/reports', ExportReportsController::class)->name('admin.exports.reports');
            Route::get('/exports/shift-assignments', ExportShiftAssignmentsController::class)->name('admin.exports.shift-assignments');
            Route::get('/exports/shift-counts', ExportUserShiftCountsController::class)->name('admin.exports.shift-counts');
            Route::get('/exports/user-availabilities', ExportUserAvailabilitiesController::class)->name('admin.exports.user-availabilities');

            // Route::get('/', static fn() => Inertia::render('Admin/Dashboard'))->name('admin.dashboard');

            // Route::resource('locations', 'Admin\LocationsController');
            // Route::resource('users', 'Admin\UsersController');
            // Route::resource('roles', 'Admin\RolesController');
            // Route::resource('permissions', 'Admin\PermissionsController');
            // Route::resource('reports', 'Admin\ReportsController');
            // Route::resource('reservations', 'Admin\ReservationsController');
        });
});
