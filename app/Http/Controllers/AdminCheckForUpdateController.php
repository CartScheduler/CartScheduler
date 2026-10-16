<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Pixelated\Streamline\Actions\CheckAvailableVersions;

class AdminCheckForUpdateController extends Controller
{
    public function __construct(
        private readonly CheckAvailableVersions $availableVersions,
    ) {}

    public function __invoke(Request $request)
    {
        $old = ltrim(config('streamline.installed_version'), 'v');
        $new = $this->availableVersions->execute(force: true, ignorePreReleases: ! $request->boolean('beta'));
        $new = ltrim($new, 'v');

        return version_compare($old, $new, '<');
    }
}
