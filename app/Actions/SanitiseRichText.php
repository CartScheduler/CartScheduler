<?php

namespace App\Actions;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Reduces editor HTML to the markup the editor itself can produce.
 *
 * The location description is written through a TipTap editor and rendered with
 * `v-html`, so it is stored as raw HTML. The editor is only a UI — an admin can
 * PUT whatever they like at the endpoint — and the rendered result reaches every
 * volunteer's dashboard. Sanitising on write makes the stored value the safe
 * one, rather than relying on every future read remembering to escape.
 *
 * The allowlist mirrors the toolbar in `resources/js/Components/TextEditor.vue`:
 * StarterKit with headings limited to h3-h6, plus TextAlign on paragraphs and a
 * link extension restricted to the five schemes below. Anything outside that is
 * markup the editor could not have created.
 */
class SanitiseRichText
{
    /**
     * Tags the editor can emit, mapped to the attributes each may carry.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        // TextAlign writes `style="text-align: …"` onto block elements.
        'p' => ['style'],
        'h3' => ['style'],
        'h4' => ['style'],
        'h5' => ['style'],
        'h6' => ['style'],
        'a' => ['href', 'target', 'rel'],
        'br' => [],
        'strong' => [],
        'em' => [],
        's' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'code' => [],
        'pre' => [],
        'hr' => [],
    ];

    public function execute(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return $this->sanitizer()->sanitize($html);
    }

    private function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            // Matches the editor's `protocols` list. Everything else — notably
            // `javascript:` and `data:` — is dropped rather than rewritten.
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel', 'sms'])
            // A sanitised link can still point off-site, so deny the new tab
            // access back to the opener regardless of what was submitted.
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        foreach (self::ALLOWED as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        return new HtmlSanitizer($config);
    }
}
