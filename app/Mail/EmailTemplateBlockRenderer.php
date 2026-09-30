<?php

namespace App\Mail;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmailTemplateBlockRenderer
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $loopItemAliases
     */
    public function render(string $content, array $context, array $loopItemAliases = []): string
    {
        return $this->renderNodes($this->parse($content), $context, $loopItemAliases);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $content): array
    {
        return $this->parseNodes($this->tokenize($content), 0, null)[0];
    }

    /**
     * @return list<array{type: string, value?: string, path?: string}>
     */
    private function tokenize(string $content): array
    {
        $tokens = [];
        $offset = 0;

        if (preg_match_all(
            '/\{\{\s*(#each|#if|#empty|\/each|\/if)(?:\s+([\w.]+))?\s*\}\}/',
            $content,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        ) === false) {
            return [['type' => 'text', 'value' => $content]];
        }

        foreach ($matches as $match) {
            $start = $match[0][1];
            $length = strlen($match[0][0]);

            if ($start > $offset) {
                $tokens[] = ['type' => 'text', 'value' => substr($content, $offset, $start - $offset)];
            }

            $tag = $match[1][0];
            $path = $match[2][0] ?? '';

            $tokens[] = match ($tag) {
                '#each' => ['type' => 'each_open', 'path' => $path],
                '#if' => ['type' => 'if_open', 'path' => $path],
                '#empty' => ['type' => 'empty'],
                '/each' => ['type' => 'each_close'],
                '/if' => ['type' => 'if_close'],
                default => ['type' => 'text', 'value' => $match[0][0]],
            };

            $offset = $start + $length;
        }

        if ($offset < strlen($content)) {
            $tokens[] = ['type' => 'text', 'value' => substr($content, $offset)];
        }

        return $tokens;
    }

    /**
     * @param  list<array{type: string, value?: string, path?: string}>  $tokens
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function parseNodes(array $tokens, int $index, ?string $until): array
    {
        $nodes = [];

        while ($index < count($tokens)) {
            $token = $tokens[$index];

            if ($until !== null && $token['type'] === $until) {
                break;
            }

            if ($until === 'each_close' && $token['type'] === 'empty') {
                break;
            }

            if ($token['type'] === 'each_open') {
                [$node, $index] = $this->parseEach($tokens, $index);
                $nodes[] = $node;

                continue;
            }

            if ($token['type'] === 'if_open') {
                [$node, $index] = $this->parseIf($tokens, $index);
                $nodes[] = $node;

                continue;
            }

            if (in_array($token['type'], ['each_close', 'if_close', 'empty'], true)) {
                $nodes[] = ['type' => 'text', 'value' => $this->tokenAsText($token)];
                $index++;

                continue;
            }

            $nodes[] = ['type' => 'text', 'value' => $token['value'] ?? ''];
            $index++;
        }

        return [$nodes, $index];
    }

    /**
     * @param  list<array{type: string, value?: string, path?: string}>  $tokens
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function parseEach(array $tokens, int $index): array
    {
        $path = $tokens[$index]['path'] ?? '';
        $index++;

        [$body, $index] = $this->parseNodes($tokens, $index, 'each_close');
        $empty = [];

        if (($tokens[$index]['type'] ?? null) === 'empty') {
            $index++;
            [$empty, $index] = $this->parseNodes($tokens, $index, 'each_close');
        }

        if (($tokens[$index]['type'] ?? null) === 'each_close') {
            $index++;
        }

        return [
            ['type' => 'each', 'path' => $path, 'body' => $body, 'empty' => $empty],
            $index,
        ];
    }

    /**
     * @param  list<array{type: string, value?: string, path?: string}>  $tokens
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function parseIf(array $tokens, int $index): array
    {
        $path = $tokens[$index]['path'] ?? '';
        $index++;

        [$body, $index] = $this->parseNodes($tokens, $index, 'if_close');

        if (($tokens[$index]['type'] ?? null) === 'if_close') {
            $index++;
        }

        return [
            ['type' => 'if', 'path' => $path, 'body' => $body],
            $index,
        ];
    }

    /**
     * @param  array{type: string, value?: string, path?: string}  $token
     */
    private function tokenAsText(array $token): string
    {
        return match ($token['type']) {
            'each_close' => '{{/each}}',
            'if_close' => '{{/if}}',
            'empty' => '{{#empty}}',
            default => $token['value'] ?? '',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $loopItemAliases
     */
    private function renderNodes(array $nodes, array $context, array $loopItemAliases): string
    {
        $output = '';

        foreach ($nodes as $node) {
            $output .= match ($node['type']) {
                'each' => $this->renderEach($node, $context, $loopItemAliases),
                'if' => $this->renderIf($node, $context, $loopItemAliases),
                default => (string) ($node['value'] ?? ''),
            };
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $loopItemAliases
     */
    private function renderEach(array $node, array $context, array $loopItemAliases): string
    {
        $path = (string) ($node['path'] ?? '');
        $items = $this->itemsFor(data_get($context, $path));
        $alias = $loopItemAliases[$path] ?? Str::singular(Str::afterLast($path, '.'));

        if ($items === []) {
            return $this->interpolate(
                $this->renderNodes($node['empty'] ?? [], $context, $loopItemAliases),
                $context,
            );
        }

        $output = '';

        foreach ($items as $item) {
            $iterationContext = array_merge($context, [$alias => $this->itemAsArray($item)]);
            $output .= $this->interpolate(
                $this->renderNodes($node['body'] ?? [], $iterationContext, $loopItemAliases),
                $iterationContext,
            );
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $loopItemAliases
     */
    private function renderIf(array $node, array $context, array $loopItemAliases): string
    {
        if (! $this->isTruthy(data_get($context, (string) ($node['path'] ?? '')))) {
            return '';
        }

        return $this->renderNodes($node['body'] ?? [], $context, $loopItemAliases);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function interpolate(string $content, array $context): string
    {
        return preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            static function (array $matches) use ($context) {
                $key = $matches[1];
                $count = EmailTemplateBlockRenderer::countFor($context, $key);
                if ($count !== null) {
                    return (string) $count;
                }

                $value = data_get($context, $key);

                return is_scalar($value) || $value === null ? (string) ($value ?? '') : '';
            },
            $content,
        ) ?? $content;
    }

    /**
     * @return list<mixed>
     */
    private function itemsFor(mixed $value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value) || $value === []) {
            return [];
        }

        return array_is_list($value) ? array_values($value) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemAsArray(mixed $item): array
    {
        if ($item instanceof Collection) {
            return $item->toArray();
        }

        if (is_object($item)) {
            return get_object_vars($item);
        }

        return is_array($item) ? $item : [];
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value instanceof Collection) {
            return $value->isNotEmpty();
        }

        if (is_array($value)) {
            return $value !== [];
        }

        if (is_string($value)) {
            return $value !== '';
        }

        return (bool) $value;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function countFor(array $context, string $key): ?int
    {
        if (! str_ends_with($key, '.count')) {
            return null;
        }

        $collection = substr($key, 0, -strlen('.count'));
        $value = data_get($context, $collection);

        if (is_string($value) || ! is_countable($value)) {
            return null;
        }

        return count($value);
    }
}
