<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class EmailTemplateRenderer
{
    public function __construct(
        private readonly EmailPlaceholderRegistry $registry,
        private readonly EmailTemplateBlockRenderer $blockRenderer,
    ) {}

    /**
     * @return array{subject: string, html: string}
     */
    public function preview(string $templateKey, string $subject, string $body): array
    {
        $context = $this->buildRenderContext($this->samplePreviewContext($templateKey));

        return [
            'subject' => $this->replacePlaceholders($subject, $templateKey, $context),
            'html' => (string) app(Markdown::class)->render('emails.dynamic-template', [
                'body' => $this->replacePlaceholders($body, $templateKey, $context),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{subject: string, body: string, view: string|null}
     */
    public function renderForMailable(string $key, array $context): array
    {
        $template = EmailTemplate::findByKey($key);

        if ($template === null) {
            return [
                'subject' => $this->defaultSubject($key),
                'body' => '',
                'view' => $this->fallbackView($key),
            ];
        }

        $renderContext = $this->buildRenderContext($context);

        return [
            'subject' => $this->replacePlaceholders($template->subject, $key, $renderContext),
            'body' => $this->replacePlaceholders($template->body, $key, $renderContext),
            'view' => 'emails.dynamic-template',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function replacePlaceholders(string $content, string $templateKey, array $context): string
    {
        $content = $this->blockRenderer->render(
            $content,
            $context,
            $this->registry->loopItemAliases($templateKey),
        );

        foreach ($this->registry->componentKeys($templateKey) as $componentKey) {
            $placeholder = '{{ '.$componentKey.' }}';
            if (! str_contains($content, $placeholder)) {
                continue;
            }

            $markdown = app(Markdown::class);
            $rendered = View::replaceNamespace('mail', $markdown->htmlComponentPaths())
                ->make('emails.components.'.str_replace('_', '-', $componentKey), $context)
                ->render();
            $content = str_replace($placeholder, trim($rendered), $content);
        }

        $variableContext = $context;

        return preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            static function (array $matches) use ($variableContext) {
                $count = EmailTemplateBlockRenderer::countFor($variableContext, $matches[1]);
                if ($count !== null) {
                    return (string) $count;
                }

                $value = data_get($variableContext, $matches[1]);

                return is_scalar($value) || $value === null ? (string) ($value ?? '') : '';
            },
            $content,
        ) ?? $content;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildRenderContext(array $context): array
    {
        $user = $this->recipientVariables($context['user'] ?? null);
        $app = $this->registry->appVariables();
        $token = (string) ($context['token'] ?? '');

        return array_merge($context, [
            'user' => $user,
            'app' => $app,
            'mail' => $this->registry->mailVariables(),
            'period' => [
                'start' => (string) data_get($context, 'period.start', ''),
                'end' => (string) data_get($context, 'period.end', ''),
                'label' => (string) data_get($context, 'period.label', ''),
            ],
            'confirm_account_url' => $this->confirmAccountUrl($user->id, $token, $app['url']),
        ]);
    }

    private function confirmAccountUrl(mixed $userId, string $token, string $appUrl): string
    {
        if ($userId === null || $userId === '' || $token === '') {
            return '';
        }

        return rtrim($appUrl, '/').'/set-password/'.$userId.'/'.$token;
    }

    /**
     * @return object{
     *     id: mixed,
     *     name: string,
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     mobile_phone: string,
     *     title: string,
     *     prefixed_name: string,
     *     gender: string,
     *     appointment: string,
     *     serving_as: string,
     *     spouse_name: string
     * }
     */
    private function recipientVariables(mixed $user): object
    {
        $name = trim((string) data_get($user, 'name', ''));
        $gender = (string) data_get($user, 'gender', '');
        $nameParts = preg_split('/\s+/', $name, 2) ?: [];
        $title = match ($gender) {
            'male' => 'Bro',
            'female' => 'Sis',
            default => '',
        };

        $spouseName = data_get($user, 'spouse.name') ?? data_get($user, 'spouse_name');

        return (object) [
            'id' => data_get($user, 'id'),
            'name' => $name,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'email' => (string) data_get($user, 'email', ''),
            'mobile_phone' => (string) (data_get($user, 'mobile_phone') ?? ''),
            'title' => $title,
            'prefixed_name' => trim($title.' '.$name),
            'gender' => $gender !== '' ? Str::title($gender) : '',
            'appointment' => $this->formatLabel(data_get($user, 'appointment')),
            'serving_as' => $this->formatLabel(data_get($user, 'serving_as')),
            'spouse_name' => (string) ($spouseName ?? ''),
        ];
    }

    private function formatLabel(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        return Str::title($value);
    }

    private function fallbackView(string $key): string
    {
        return match ($key) {
            'shift-assignments-released' => 'emails.shift-assignments-released',
            'user-account-created' => 'emails.user-account-created',
            default => 'emails.dynamic-template',
        };
    }

    private function defaultSubject(string $key): string
    {
        $siteName = $this->registry->appVariables()['site_name'];

        return match ($key) {
            'shift-assignments-released' => $siteName.' Shift Assignments',
            'user-account-created' => $siteName.' Account Activation',
            default => $siteName,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function samplePreviewContext(string $templateKey): array
    {
        $user = (object) [
            'id' => 1,
            'name' => 'Jane Volunteer',
            'email' => 'jane@example.com',
            'gender' => 'female',
            'mobile_phone' => '0400 000 000',
            'appointment' => null,
            'serving_as' => 'publisher',
            'spouse_name' => 'John Volunteer',
        ];

        $context = ['user' => $user];

        if ($templateKey === 'shift-assignments-released') {
            $context['period'] = $this->registry->periodVariables(
                Carbon::parse('2026-02-01'),
                Carbon::parse('2026-02-28'),
            );
            $context['shifts'] = Collection::make([
                [
                    'date' => 'Monday, 14 February 2026',
                    'start_time' => '9:00 AM',
                    'end_time' => '11:00 AM',
                    'location' => 'Town Square',
                    'location_description' => 'Meet at the north entrance near the cart.',
                    'location_map_url' => 'https://www.google.com/maps?q=-37.8136,144.9631',
                    'other_volunteers' => [
                        ['name' => 'John Smith', 'mobile_phone' => '0400 000 000'],
                        ['name' => 'Mary Jones', 'mobile_phone' => '0400 000 001'],
                        ['name' => 'Jane Doe', 'mobile_phone' => '0400 000 002'],
                    ],
                ],
                [
                    'date' => 'Wednesday, 16 February 2026',
                    'start_time' => '2:00 PM',
                    'end_time' => '4:00 PM',
                    'location' => 'City Centre',
                    'location_description' => '',
                    'location_map_url' => null,
                    'other_volunteers' => [
                        ['name' => 'John Smith', 'mobile_phone' => '0400 000 000'],
                        ['name' => 'Mary Jones', 'mobile_phone' => '0400 000 001'],
                    ],
                ],
            ]);
        }

        if ($templateKey === 'user-account-created') {
            $context['token'] = 'sample-preview-token';
        }

        return $context;
    }
}
