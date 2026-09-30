<?php

namespace App\Data;

use App\Mail\EmailPlaceholderRegistry;
use App\Mail\EmailTemplateRecipientResolver;
use App\Mail\EmailTemplateTriggerResolver;
use App\Models\EmailTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EmailTemplateData extends Data
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public string $subject,
        public string $body,
        public ?string $description,
        public bool $is_system,
        public string $updated_at,
        /** @var list<EmailPlaceholderData> */
        public array $placeholders = [],
        public ?EmailTemplateTriggerData $trigger = null,
        public ?EmailTemplateRecipientData $recipients = null,
    ) {}

    public static function fromModel(EmailTemplate $template, ?EmailPlaceholderRegistry $registry = null): self
    {
        $registry ??= app(EmailPlaceholderRegistry::class);

        return new self(
            id: $template->id,
            key: $template->key,
            name: $template->name,
            subject: $template->subject,
            body: $template->body,
            description: $template->description,
            is_system: $template->is_system,
            updated_at: $template->updated_at->toIso8601String(),
            placeholders: array_map(
                static fn (array $placeholder) => EmailPlaceholderData::from($placeholder),
                $registry->placeholdersFor($template->key),
            ),
            trigger: app(EmailTemplateTriggerResolver::class)->for($template->key),
            recipients: app(EmailTemplateRecipientResolver::class)->for($template->key),
        );
    }
}
