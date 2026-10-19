<?php

use App\Mail\EmailTemplateRecipientResolver;

test('shift assignments explains who receives the email', function () {
    $recipients = app(EmailTemplateRecipientResolver::class)->for('shift-assignments-released');

    expect($recipients)->not->toBeNull();
    expect($recipients->summary)->toContain('pre-assigned');
    expect($recipients->summary)->toContain('reservation window');
});

test('user account created explains who receives the email', function () {
    $recipients = app(EmailTemplateRecipientResolver::class)->for('user-account-created');

    expect($recipients)->not->toBeNull();
    expect($recipients->summary)->toContain('account was created');
    expect($recipients->summary)->toContain('welcome email is being resent');
});

test('unknown template has no recipients', function () {
    expect(app(EmailTemplateRecipientResolver::class)->for('unknown-template'))->toBeNull();
});
