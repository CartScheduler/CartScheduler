import DOMPurify from "dompurify";

/**
 * Tags the TipTap editor can emit. Mirrors `App\Actions\SanitiseRichText`,
 * which is the real boundary — this is the second line, covering values that
 * were stored before sanitising on write existed.
 */
const ALLOWED_TAGS = [
  "p",
  "h3",
  "h4",
  "h5",
  "h6",
  "a",
  "br",
  "strong",
  "em",
  "s",
  "ul",
  "ol",
  "li",
  "blockquote",
  "code",
  "pre",
  "hr",
];

/** `style` carries TextAlign's `text-align`; DOMPurify parses and filters it. */
const ALLOWED_ATTR = ["href", "target", "rel", "style"];

/** The editor's own `protocols` list. Notably excludes `javascript:` and `data:`. */
const ALLOWED_URI_REGEXP = /^(?:https?|mailto|tel|sms):/i;

/**
 * Renders editor HTML safe to hand to `v-html`.
 *
 * Returns an empty string for nullish input so a caller can bind the result
 * directly without a fallback of its own.
 */
export default function sanitiseRichText(html: string | null | undefined): string {
  if (!html) {
    return "";
  }

  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS,
    ALLOWED_ATTR,
    ALLOWED_URI_REGEXP,
  });
}
