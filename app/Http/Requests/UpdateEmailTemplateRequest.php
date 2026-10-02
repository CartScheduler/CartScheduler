<?php

namespace App\Http\Requests;

use App\Models\EmailTemplate;
use App\Rules\ValidEmailTemplatePlaceholders;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var EmailTemplate $emailTemplate */
        $emailTemplate = $this->route('emailTemplate');

        return [
            'subject' => ['required', 'string', 'max:255', new ValidEmailTemplatePlaceholders($emailTemplate->key)],
            'body' => ['required', 'string', 'max:4000000000', new ValidEmailTemplatePlaceholders($emailTemplate->key)],
        ];
    }
}
