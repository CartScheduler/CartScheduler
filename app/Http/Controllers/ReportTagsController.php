<?php

namespace App\Http\Controllers;

use App\Data\ReportTagData;
use App\Http\Requests\CreateUpdateReportTagRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Spatie\Tags\Tag;

class ReportTagsController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Tag::class, 'tag');
    }

    public function index(): Collection
    {
        return ReportTagData::collect(Tag::ordered()->get());
    }

    public function store(CreateUpdateReportTagRequest $request): Response
    {
        $data = $request->validated();

        Tag::findOrCreate($data['name'], 'reports');

        return response('', 204);
    }

    public function update(CreateUpdateReportTagRequest $request, Tag $tag)
    {
        $tag->name = $request->get('name');
        $tag->save();

        return response('', 204);
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return response('', 204);
    }
}
