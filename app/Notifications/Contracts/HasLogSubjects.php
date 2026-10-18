<?php

namespace App\Notifications\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A notification about specific records (e.g. shift assignments). The notification log links to
 * them, which is how the app knows those records have already been notified.
 */
interface HasLogSubjects
{
    /**
     * @return Collection<int, Model>
     */
    public function logSubjects(): Collection;
}
