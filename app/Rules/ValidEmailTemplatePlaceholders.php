<?php

namespace App\Rules;

use App\Mail\EmailPlaceholderRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmailTemplatePlaceholders implements ValidationRule
{
    public function __construct(private readonly ?string $templateKey = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $registry = app(EmailPlaceholderRegistry::class);
        $allowedKeys = $registry->allowedKeys($this->templateKey);
        $allowedCollections = $registry->allowedLoopCollections($this->templateKey);
        $blockStack = [];
        $collectionStack = [];

        preg_match_all(
            '/\{\{\s*(#each|#if|#empty|\/each|\/if|[\w.]+)\s*([\w.]*)\s*\}\}/',
            $value,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $tag = $match[1];
            $path = $match[2] !== '' ? $match[2] : null;

            if ($tag === '#each') {
                if ($path === null || ! in_array($path, $allowedCollections, true)) {
                    $fail("The loop {{#each {$path} }} is not allowed for this template.");

                    return;
                }

                $required = $registry->requiredLoopCollection($this->templateKey, $path);
                if ($required !== null && ! in_array($required, $collectionStack, true)) {
                    $fail("The loop {{#each {$path} }} can only be used inside {{#each {$required} }}.");

                    return;
                }

                $blockStack[] = 'each';
                $collectionStack[] = $path;

                continue;
            }

            if ($tag === '/each') {
                if (array_pop($blockStack) !== 'each') {
                    $fail('The {{#each}} and {{/each}} tags are not balanced.');

                    return;
                }
                array_pop($collectionStack);

                continue;
            }

            if ($tag === '#if') {
                if ($path === null || ! $this->isAllowedConditionPath($path, $allowedKeys, $allowedCollections)) {
                    $fail("The placeholder {{#if {$path} }} is not allowed for this template.");

                    return;
                }

                $required = $registry->requiredLoopCollection($this->templateKey, $path);
                if ($required !== null && ! in_array($required, $collectionStack, true)) {
                    $fail("The placeholder {{#if {$path} }} can only be used inside {{#each {$required} }}.");

                    return;
                }

                $blockStack[] = 'if';

                continue;
            }

            if ($tag === '/if') {
                if (array_pop($blockStack) !== 'if') {
                    $fail('The {{#if}} and {{/if}} tags are not balanced.');

                    return;
                }

                continue;
            }

            if ($tag === '#empty') {
                if (end($blockStack) !== 'each') {
                    $fail('The {{#empty}} tag can only be used inside a {{#each}} loop.');

                    return;
                }

                continue;
            }

            if (! in_array($tag, $allowedKeys, true)) {
                $fail("The placeholder {{ {$tag} }} is not allowed for this template.");

                return;
            }

            $required = $registry->requiredLoopCollection($this->templateKey, $tag);
            if ($required !== null && ! in_array($required, $collectionStack, true)) {
                $fail("The placeholder {{ {$tag} }} can only be used inside {{#each {$required} }}.");

                return;
            }
        }

        if ($blockStack !== []) {
            $unclosed = $blockStack[0] === 'if' ? '{{#if}} and {{/if}}' : '{{#each}} and {{/each}}';
            $fail("The {$unclosed} tags are not balanced.");
        }
    }

    /**
     * @param  list<string>  $allowedKeys
     * @param  list<string>  $allowedCollections
     */
    private function isAllowedConditionPath(string $path, array $allowedKeys, array $allowedCollections): bool
    {
        return in_array($path, $allowedKeys, true) || in_array($path, $allowedCollections, true);
    }
}
