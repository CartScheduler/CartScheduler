<?php

namespace App\Http\Middleware;

use App\Actions\HasNewVersionAvailable;
use App\Actions\UserNeedsToUpdateAvailability;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly GeneralSettings $settings,
        private readonly HasNewVersionAvailable $hasNewVersionAvailable,
        private readonly UserNeedsToUpdateAvailability $getNeedsToUpdateAvailability,
    ) {
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function share(Request $request): array
    {
        if ($request->expectsJson()) {
            // return because we don't need this data for json requests
            return [];
        }

        /** @var User $user */
        $user   = $request->user();
        $custom = [
            'pagePermissions'   => $this->getPageAccessPermissions($user),
            'shiftAvailability' => [
                'timezone'             => config('app.timezone'),
                'duration'             => (int) config('cart-scheduler.shift_reservation_duration'),
                'period'               => config('cart-scheduler.shift_reservation_duration_period'),
                'releasedDaily'        => config('cart-scheduler.do_release_shifts_daily'),
                'weekDayRelease'       => (int) config('cart-scheduler.release_weekly_shifts_on_day'),
                'systemShiftStartHour' => $this->settings->systemShiftStartHour,
                'systemShiftEndHour'   => $this->settings->systemShiftEndHour,
            ],
        ];

        $hasUpdate = $this->getHasSoftwareUpdate($user);
        if ($hasUpdate !== null) {
            $custom['hasUpdate'] = $hasUpdate;
        }

        if ($this->settings->enableUserAvailability) {
            $custom['enableUserAvailability']    = true;
            $custom['needsToUpdateAvailability'] = $this->getNeedsToUpdateAvailability->execute($user);
        }

        if ($this->settings->enableUserLocationChoices) {
            $custom['enableUserLocationChoices'] = true;
        }

        if ($user?->is_unrestricted) {
            $custom['isUnrestricted'] = true;
        }

        return array_merge(parent::share($request), $custom);
    }

    protected function getPageAccessPermissions(?User $user): array
    {
        $permissions = [];
        if (!$user) {
            return $permissions;
        }

        if (Gate::check('admin')) {
            $permissions['canAdmin'] = true;
            if (in_array($user->id, $this->settings->allowedSettingsUsers, true)) {
                $permissions['canEditSettings'] = true;
            }
        }

        return $permissions;
    }

    protected function getHasSoftwareUpdate(?User $user): ?bool
    {
        if ($user === null) {
            return null;
        }
        if (!Gate::check('admin')) {
            return null;
        }
        if (!in_array($user->id, $this->settings->allowedSettingsUsers, true)) {
            return null;
        }
        return $this->hasNewVersionAvailable->execute();
    }
}
