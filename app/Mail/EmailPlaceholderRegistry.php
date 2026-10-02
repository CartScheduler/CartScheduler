<?php

namespace App\Mail;

use App\Settings\GeneralSettings;
use Illuminate\Support\Carbon;

class EmailPlaceholderRegistry
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     type: 'variable'|'component'|'loop',
     *     category: 'recipient'|'app'|'email',
     *     value?: string|null,
     *     parent?: string|null,
     *     collection?: string|null,
     *     item?: string|null
     * }>
     */
    public function placeholdersFor(?string $templateKey = null): array
    {
        return array_merge($this->globalPlaceholders(), $this->templatePlaceholdersFor($templateKey));
    }

    /**
     * Keys that may appear as {{ key }} interpolations, including loop-scoped variables.
     *
     * @return list<string>
     */
    public function allowedKeys(?string $templateKey = null): array
    {
        return array_values(array_map(
            static fn (array $placeholder) => $placeholder['key'],
            array_filter(
                $this->placeholdersFor($templateKey),
                static fn (array $placeholder) => in_array($placeholder['type'], ['variable', 'component'], true),
            ),
        ));
    }

    /**
     * @return array<string, string> collection path => loop item alias
     */
    public function loopItemAliases(?string $templateKey = null): array
    {
        $aliases = [];

        foreach ($this->placeholdersFor($templateKey) as $placeholder) {
            if ($placeholder['type'] !== 'loop' || ! isset($placeholder['collection'], $placeholder['item'])) {
                continue;
            }

            $aliases[$placeholder['collection']] = $placeholder['item'];
        }

        return $aliases;
    }

    /**
     * @return list<string>
     */
    public function allowedLoopCollections(?string $templateKey = null): array
    {
        return array_keys($this->loopItemAliases($templateKey));
    }

    /**
     * Loop collection a placeholder or nested loop may only be used inside, if any.
     */
    public function requiredLoopCollection(?string $templateKey, string $key): ?string
    {
        $placeholders = $this->placeholdersFor($templateKey);
        $placeholder = $this->findPlaceholder($placeholders, $key);

        $parentKey = is_array($placeholder) ? ($placeholder['parent'] ?? null) : null;
        if (! is_string($parentKey) || $parentKey === '') {
            return null;
        }

        foreach ($placeholders as $candidate) {
            if ($candidate['key'] === $parentKey) {
                return $candidate['collection'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $placeholders
     * @return array<string, mixed>|null
     */
    private function findPlaceholder(array $placeholders, string $key): ?array
    {
        foreach ($placeholders as $placeholder) {
            if ($placeholder['key'] === $key) {
                return $placeholder;
            }
        }

        foreach ($placeholders as $placeholder) {
            if (($placeholder['type'] ?? null) === 'loop' && ($placeholder['collection'] ?? null) === $key) {
                return $placeholder;
            }
        }

        return null;
    }

    /**
     * @return array{name: string, url: string, site_name: string}
     */
    public function appVariables(): array
    {
        $siteName = app(GeneralSettings::class)->siteName;

        return [
            'name' => (string) config('app.name'),
            'url' => (string) config('app.url'),
            'site_name' => $siteName !== '' ? $siteName : (string) config('app.name'),
        ];
    }

    /**
     * @return array{support: array{address: string, name: string}, from: array{address: string, name: string}}
     */
    public function mailVariables(): array
    {
        return [
            'support' => [
                'address' => (string) config('mail.support.address'),
                'name' => (string) config('mail.support.name'),
            ],
            'from' => [
                'address' => (string) config('mail.from.address'),
                'name' => (string) config('mail.from.name'),
            ],
        ];
    }

    /**
     * @return array{start: string, end: string, label: string}
     */
    public function periodVariables(Carbon $start, Carbon $end): array
    {
        $startLabel = $start->copy()->startOfDay()->format('j F Y');
        $endLabel = $end->copy()->startOfDay()->format('j F Y');

        return [
            'start' => $startLabel,
            'end' => $endLabel,
            'label' => $startLabel === $endLabel ? $startLabel : $startLabel.' – '.$endLabel,
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, type: 'variable'|'component'|'loop', category: 'recipient'|'app'|'email', value?: string|null}>
     */
    private function globalPlaceholders(): array
    {
        $app = $this->appVariables();
        $mail = $this->mailVariables();

        return [
            [
                'key' => 'user.name',
                'label' => 'Full Name',
                'description' => 'The recipient\'s full name.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.first_name',
                'label' => 'First Name',
                'description' => 'The recipient\'s first name.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.last_name',
                'label' => 'Last Name',
                'description' => 'The recipient\'s last name.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.email',
                'label' => 'Email',
                'description' => 'The recipient\'s email address.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.mobile_phone',
                'label' => 'Mobile Phone',
                'description' => 'The recipient\'s mobile phone number.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.title',
                'label' => 'Title',
                'description' => 'Bro or Sis, based on the recipient\'s gender.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.prefixed_name',
                'label' => 'Prefixed Name',
                'description' => 'The recipient\'s full name with a Bro or Sis prefix.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.gender',
                'label' => 'Gender',
                'description' => 'The recipient\'s gender.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.appointment',
                'label' => 'Appointment',
                'description' => 'The recipient\'s congregation appointment, if any.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.serving_as',
                'label' => 'Serving As',
                'description' => 'How the recipient is serving, such as publisher or pioneer.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'user.spouse_name',
                'label' => 'Spouse Name',
                'description' => 'The recipient\'s spouse\'s name, if recorded.',
                'type' => 'variable',
                'category' => 'recipient',
            ],
            [
                'key' => 'app.name',
                'label' => 'App Name',
                'description' => 'The application name from configuration.',
                'type' => 'variable',
                'category' => 'app',
                'value' => $app['name'],
            ],
            [
                'key' => 'app.site_name',
                'label' => 'Site Name',
                'description' => 'The congregation-facing site name from Settings.',
                'type' => 'variable',
                'category' => 'app',
                'value' => $app['site_name'],
            ],
            [
                'key' => 'app.url',
                'label' => 'App URL',
                'description' => 'The application base URL.',
                'type' => 'variable',
                'category' => 'app',
                'value' => $app['url'],
            ],
            [
                'key' => 'mail.support.address',
                'label' => 'Support Email',
                'description' => 'The support contact email address.',
                'type' => 'variable',
                'category' => 'app',
                'value' => $mail['support']['address'],
            ],
            [
                'key' => 'mail.support.name',
                'label' => 'Support Name',
                'description' => 'The support contact name, useful in a signature.',
                'type' => 'variable',
                'category' => 'app',
                'value' => $mail['support']['name'],
            ],
            [
                'key' => 'mail.from.address',
                'label' => 'From Email',
                'description' => 'The address emails are sent from.',
                'type' => 'variable',
                'category' => 'app',
                'value' => $mail['from']['address'],
            ],
            [
                'key' => 'mail.from.name',
                'label' => 'From Name',
                'description' => 'The name emails are sent from.',
                'type' => 'variable',
                'category' => 'app',
                'value' => $mail['from']['name'],
            ],
        ];
    }

    /**
     * @return array<string, list<array{key: string, label: string, description: string, type: 'variable'|'component'|'loop', category: 'recipient'|'app'|'email'}>>
     */
    public function componentsGroupedByTemplateKey(): array
    {
        $grouped = [];

        foreach ($this->templatePlaceholdersGroupedByTemplateKey() as $templateKey => $placeholders) {
            $grouped[$templateKey] = array_values(array_filter(
                $placeholders,
                static fn (array $placeholder) => $placeholder['type'] === 'component',
            ));
        }

        return $grouped;
    }

    /**
     * @return array<string, list<array{key: string, label: string, description: string, type: 'variable'|'component'|'loop', category: 'recipient'|'app'|'email', parent?: string, collection?: string, item?: string}>>
     */
    private function templatePlaceholdersGroupedByTemplateKey(): array
    {
        return [
            'shift-assignments-released' => [
                [
                    'key' => 'period.start',
                    'label' => 'Period Start',
                    'description' => 'The first date of the newly released reservation window.',
                    'type' => 'variable',
                    'category' => 'email',
                ],
                [
                    'key' => 'period.end',
                    'label' => 'Period End',
                    'description' => 'The last date of the newly released reservation window.',
                    'type' => 'variable',
                    'category' => 'email',
                ],
                [
                    'key' => 'period.label',
                    'label' => 'Reservation Period',
                    'description' => 'The released reservation window as a single label, such as 1 February 2026 – 28 February 2026.',
                    'type' => 'variable',
                    'category' => 'email',
                ],
                [
                    'key' => 'shifts.count',
                    'label' => 'Shift Count',
                    'description' => 'How many shifts the recipient is assigned in this email.',
                    'type' => 'variable',
                    'category' => 'email',
                ],
                [
                    'key' => 'shifts',
                    'label' => 'Shifts',
                    'description' => 'Inserts a formatted list of the recipient\'s assigned shifts. Each shift shows the date, location, map link, location notes, start and finish times, and the other volunteers on that shift with their phone numbers.',
                    'type' => 'component',
                    'category' => 'email',
                ],
                [
                    'key' => 'each.shifts',
                    'label' => 'Each Shift',
                    'description' => 'Repeats the wrapped content once for each assigned shift. Put shift variables inside the loop. Use {{#if shift.location_map_url}}…{{/if}} to hide empty values, and {{#empty}} before {{/each}} when the list has no items.',
                    'type' => 'loop',
                    'category' => 'email',
                    'collection' => 'shifts',
                    'item' => 'shift',
                ],
                [
                    'key' => 'shift.date',
                    'label' => 'Shift Date',
                    'description' => 'The assigned shift date, such as Monday, 14 February 2026.',
                    'type' => 'variable',
                    'category' => 'email',
                    'parent' => 'each.shifts',
                ],
                [
                    'key' => 'shift.location',
                    'label' => 'Shift Location',
                    'description' => 'The location name for the assigned shift.',
                    'type' => 'variable',
                    'category' => 'email',
                    'parent' => 'each.shifts',
                ],
                [
                    'key' => 'shift.location_map_url',
                    'label' => 'Shift Map URL',
                    'description' => 'A Google Maps link for the shift location, when coordinates are recorded. Wrap with {{#if shift.location_map_url}} to hide it when empty.',
                    'type' => 'variable',
                    'category' => 'email',
                    'parent' => 'each.shifts',
                ],
                [
                    'key' => 'shift.location_description',
                    'label' => 'Shift Location Notes',
                    'description' => 'Optional notes for finding the location. Wrap with {{#if shift.location_description}} to hide them when empty.',
                    'type' => 'variable',
                    'category' => 'email',
                    'parent' => 'each.shifts',
                ],
                [
                    'key' => 'shift.start_time',
                    'label' => 'Shift Start Time',
                    'description' => 'The shift start time, such as 9:00 AM.',
                    'type' => 'variable',
                    'category' => 'email',
                    'parent' => 'each.shifts',
                ],
                [
                    'key' => 'shift.end_time',
                    'label' => 'Shift Finish Time',
                    'description' => 'The shift finish time, such as 11:00 AM.',
                    'type' => 'variable',
                    'category' => 'email',
                    'parent' => 'each.shifts',
                ],
                [
                    'key' => 'each.shift.other_volunteers',
                    'label' => 'Each Other Volunteer',
                    'description' => 'Repeats the wrapped content once for each other volunteer on the current shift. Place this inside {{#each shifts}}. Use {{#empty}} for when nobody else is assigned.',
                    'type' => 'loop',
                    'category' => 'email',
                    'parent' => 'each.shifts',
                    'collection' => 'shift.other_volunteers',
                    'item' => 'volunteer',
                ],
                [
                    'key' => 'volunteer.name',
                    'label' => 'Volunteer Name',
                    'description' => 'The other volunteer\'s name. Use inside {{#each shift.other_volunteers}}.',
                    'type' => 'variable',
                    'category' => 'email',
                    'parent' => 'each.shift.other_volunteers',
                ],
                [
                    'key' => 'volunteer.mobile_phone',
                    'label' => 'Volunteer Mobile',
                    'description' => 'The other volunteer\'s mobile number, if recorded. Wrap with {{#if volunteer.mobile_phone}} to hide it when empty.',
                    'type' => 'variable',
                    'category' => 'email',
                    'parent' => 'each.shift.other_volunteers',
                ],
            ],
            'user-account-created' => [
                [
                    'key' => 'confirm_account_url',
                    'label' => 'Confirm Account URL',
                    'description' => 'The set-password link, for a plain URL instead of the button.',
                    'type' => 'variable',
                    'category' => 'email',
                ],
                [
                    'key' => 'confirm_account_button',
                    'label' => 'Confirm Account Button',
                    'description' => 'Renders the set-password confirmation button.',
                    'type' => 'component',
                    'category' => 'email',
                ],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, type: 'variable'|'component'|'loop', category: 'recipient'|'app'|'email'}>
     */
    private function templatePlaceholdersFor(?string $templateKey): array
    {
        if ($templateKey === null) {
            return [];
        }

        return $this->templatePlaceholdersGroupedByTemplateKey()[$templateKey] ?? [];
    }

    /**
     * @return list<string>
     */
    public function componentKeys(?string $templateKey = null): array
    {
        return array_values(array_map(
            static fn (array $placeholder) => $placeholder['key'],
            array_filter(
                $this->placeholdersFor($templateKey),
                static fn (array $placeholder) => $placeholder['type'] === 'component',
            ),
        ));
    }
}
