<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresAllowedSettingsUser;
use App\Http\Requests\PreviewEmailTemplateRequest;
use App\Mail\EmailTemplateRenderer;
use Illuminate\Http\JsonResponse;

class PreviewEmailTemplateController extends Controller
{
    use EnsuresAllowedSettingsUser;

    public function __invoke(PreviewEmailTemplateRequest $request, EmailTemplateRenderer $renderer): JsonResponse
    {
        $this->ensureAllowedSettingsUser($request);

        $preview = $renderer->preview(
            $request->validated('key'),
            $request->validated('subject'),
            $request->validated('body'),
        );

        return response()->json($preview);
    }
}
