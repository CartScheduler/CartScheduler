<?php

namespace App\Http\Controllers;

use App\Data\ReportTagData;
use Spatie\Tags\Tag;

class GetReportTagsController extends Controller
{
    public function __invoke()
    {
        return ReportTagData::collect(Tag::ordered()->withType('reports')->get());
    }
}
