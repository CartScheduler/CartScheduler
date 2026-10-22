<?php

namespace App\Http\Controllers;

use App\Data\EmailTemplateData;
use App\Data\EmailTemplateListData;
use App\Http\Controllers\Concerns\EnsuresAllowedSettingsUser;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Mail\EmailPlaceholderRegistry;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplatesController extends Controller
{
    use EnsuresAllowedSettingsUser;

    public function __construct(private readonly EmailPlaceholderRegistry $registry) {}

    public function index(Request $request): Response
    {
        $this->ensureAllowedSettingsUser($request);

        return Inertia::render('Admin/Emails/List', [
            'templates' => EmailTemplateListData::collect(
                EmailTemplate::query()
                    ->where('is_system', true)
                    ->orderBy('name')
                    ->get()
                    ->map(static fn (EmailTemplate $template) => EmailTemplateListData::fromModel($template)),
            ),
        ]);
    }

    public function edit(Request $request, EmailTemplate $emailTemplate): Response
    {
        $this->ensureAllowedSettingsUser($request);
        abort_unless($emailTemplate->is_system, 404);

        return Inertia::render('Admin/Emails/Edit', [
            'template' => EmailTemplateData::fromModel($emailTemplate, $this->registry),
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->ensureAllowedSettingsUser($request);
        abort_unless($emailTemplate->is_system, 404);

        $emailTemplate->update($request->validated());

        session()->flash('flash.banner', "Email template {$emailTemplate->name} successfully updated.");
        session()->flash('flash.bannerStyle', 'success');

        return Redirect::route('admin.emails.edit', $emailTemplate);
    }
}
