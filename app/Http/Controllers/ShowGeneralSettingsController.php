<?php

namespace App\Http\Controllers;

use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowGeneralSettingsController extends Controller
{
    public function __construct(private readonly GeneralSettings $settings) {}

    /**
     * @throws HttpException
     * @throws NotFoundHttpException
     */
    public function __invoke(Request $request)
    {
        if (! in_array($request->user()->getKey(), $this->settings->allowedSettingsUsers, true)) {
            abort(404);
        }

        return Inertia::render('Admin/Settings/Show', [
            'settings' => $this->settings->toArray(),
            'version' => config('cart-scheduler.version'),
        ]);
    }
}
