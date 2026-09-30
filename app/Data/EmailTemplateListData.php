<?php

namespace App\Data;

use App\Mail\EmailTemplateRecipientResolver;
use App\Mail\EmailTemplateTriggerResolver;
use App\Models\EmailTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EmailTemplateListData extends Data
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public string $subject,
        public ?string $description,
        public bool $is_system,
        public string $updated_at,
        public ?EmailTemplateTriggerData $trigger = null,
        public ?EmailTemplateRecipientData $recipients = null,
    ) {}

    public static function fromModel(EmailTemplate $template): self
    {
        return new self(
            id: $template->id,
            key: $template->key,
            name: $template->name,
            subject: $template->subject,
            description: $template->description,
            is_system: $template->is_system,
            updated_at: $template->updated_at->toIso8601String(),
            trigger: app(EmailTemplateTriggerResolver::class)->for($template->key),
            recipients: app(EmailTemplateRecipientResolver::class)->for($template->key),
        );
    }
}
