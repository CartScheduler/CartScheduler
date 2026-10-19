---
paths:
  - 'resources/views/emails/**'
---

# Emails

## Markdown injection in the welcome email, flagged for review
FOR REVIEW, raised 2026-09-12, not yet fixed. `user-account-created.blade.php` interpolates `{{ $user->name }}` into a Markdown mailable. Blade escapes it for HTML, but nothing escapes it for Markdown, so CommonMark then parses whatever survives:

- `Bob [click](http://evil.test)` renders a real `<a href>`
- `Bob ![x](http://evil.test/x.png)` renders a real `<img src>`, i.e. a tracking pixel
- `Tom *Bob* Smith` renders `<em>`

`Features::updateProfileInformation()` is enabled and validates `name` as only `required|string|max:255`, so a user can set this themselves; an admin resending the welcome email then sends it. The recipient is that same user, and self-registration is off, which caps the practical severity — but it is a real injection.

HTML escaping itself is fine and covered: `&` becomes `&amp;`, `<`/`>` become entities, and a `<script>` reaches the email only as text. Quotes stay literal because CommonMark decodes Blade's entities and re-escapes only `&`, `<` and `>` — see the escaping tests in `tests/Feature/App/Admin/UsersTest.php` before changing any of this.

Likely fixes: escape Markdown metacharacters before the view, or move the greeting out of Markdown.
