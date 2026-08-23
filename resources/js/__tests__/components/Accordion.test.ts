import { fireEvent, render, screen } from "@testing-library/vue";
import { describe, expect, it } from "vitest";
import { defineComponent, nextTick, ref } from "vue";
import Accordion from "@/Components/Accordion.vue";
import AccordionPanel from "@/Components/AccordionPanel.vue";

/**
 * `hasInitialised` is deliberately not passed: left undefined, the accordion
 * renders its panels on mount rather than waiting for the parent's signal.
 */
const renderAccordion = async (openPanel?: number) => {
  const utils = render(defineComponent({
    components: { Accordion, AccordionPanel },
    setup: () => ({ expanded: ref(openPanel) }),
    template: `
      <Accordion v-model="expanded">
        <AccordionPanel :unique-id="1">
          <template #title>Town Square</template>
          <p data-testid="body-1">Town Square shifts</p>
        </AccordionPanel>
        <AccordionPanel :unique-id="2">
          <template #title>Market Street</template>
          <p data-testid="body-2">Market Street shifts</p>
        </AccordionPanel>
      </Accordion>
    `,
  }));

  // The panels appear once the accordion flags itself initialised on mount.
  await nextTick();
  return utils;
};

/** The same two panels, but in the mode that lets several stand open at once. */
const renderMultiAccordion = async (openPanels: number[] = []) => {
  const utils = render(defineComponent({
    components: { Accordion, AccordionPanel },
    setup: () => ({ expanded: ref(openPanels) }),
    template: `
      <Accordion v-model="expanded" multiple>
        <AccordionPanel :unique-id="1">
          <template #title>Town Square</template>
          <p data-testid="body-1">Town Square shifts</p>
        </AccordionPanel>
        <AccordionPanel :unique-id="2">
          <template #title>Market Street</template>
          <p data-testid="body-2">Market Street shifts</p>
        </AccordionPanel>
      </Accordion>
    `,
  }));

  await nextTick();
  return utils;
};

/** The same two panels, laid out as fixed panels rather than disclosures. */
const renderStaticAccordion = async (description?: string) => {
  const utils = render(defineComponent({
    components: { Accordion, AccordionPanel },
    setup: () => ({ expanded: ref(undefined), description }),
    template: `
      <Accordion v-model="expanded" static-panels>
        <AccordionPanel :unique-id="1" :description="description">
          <template #title>Town Square</template>
          <p data-testid="body-1">Town Square shifts</p>
        </AccordionPanel>
        <AccordionPanel :unique-id="2">
          <template #title>Market Street</template>
          <p data-testid="body-2">Market Street shifts</p>
        </AccordionPanel>
      </Accordion>
    `,
  }));

  await nextTick();
  return utils;
};

const townSquare = /Town Square/;
const marketStreet = /Market Street/;

const toggle = (name: RegExp) => fireEvent.click(screen.getByRole("button", { name }));

const isExpanded = (name: RegExp) => screen.getByRole("button", { name }).getAttribute("aria-expanded");

describe("Accordion", () => {
  it("renders every panel header without building its body", async () => {
    await renderAccordion();

    expect(screen.getByRole("button", { name: townSquare })).toBeTruthy();
    expect(screen.getByRole("button", { name: marketStreet })).toBeTruthy();
    // The expensive part — the panel bodies — is not constructed up front.
    expect(screen.queryByTestId("body-1")).toBeNull();
    expect(screen.queryByTestId("body-2")).toBeNull();
  });

  it("builds a body on first expand, and only that one", async () => {
    await renderAccordion();

    await toggle(townSquare);

    expect(screen.getByTestId("body-1")).toBeTruthy();
    expect(screen.queryByTestId("body-2")).toBeNull();
  });

  it("keeps a body mounted once collapsed, so re-opening is instant", async () => {
    await renderAccordion();

    await toggle(townSquare);
    await toggle(townSquare);

    // Hidden by v-show rather than torn down and rebuilt.
    expect(screen.getByTestId("body-1")).toBeTruthy();
  });

  it("builds the body immediately for a panel that starts open", async () => {
    await renderAccordion(2);

    expect(screen.getByTestId("body-2")).toBeTruthy();
    expect(screen.queryByTestId("body-1")).toBeNull();
  });

  it("closes the open panel when another is opened", async () => {
    await renderAccordion(1);

    await toggle(marketStreet);

    // Single mode is a true accordion: room for one section at a time.
    expect(isExpanded(townSquare)).toBe("false");
    expect(isExpanded(marketStreet)).toBe("true");
  });

  describe("multiple", () => {
    it("holds every listed panel open at once", async () => {
      await renderMultiAccordion([1, 2]);

      expect(isExpanded(townSquare)).toBe("true");
      expect(isExpanded(marketStreet)).toBe("true");
      expect(screen.getByTestId("body-1")).toBeTruthy();
      expect(screen.getByTestId("body-2")).toBeTruthy();
    });

    it("closes only the panel that was clicked", async () => {
      await renderMultiAccordion([1, 2]);

      await toggle(townSquare);

      expect(isExpanded(townSquare)).toBe("false");
      expect(isExpanded(marketStreet)).toBe("true");
    });

    it("adds to the open set rather than replacing it", async () => {
      await renderMultiAccordion([1]);

      await toggle(marketStreet);

      expect(isExpanded(townSquare)).toBe("true");
      expect(isExpanded(marketStreet)).toBe("true");
    });
  });

  describe("staticPanels", () => {
    it("shows every body with nothing to press", async () => {
      await renderStaticAccordion();

      // Not "open": a panel layout has no state to communicate, so there is no
      // button, no aria-expanded and no chevron to rotate.
      expect(screen.queryByRole("button", { name: townSquare })).toBeNull();
      expect(screen.getByRole("heading", { name: townSquare })).toBeTruthy();
      expect(screen.getByTestId("body-1")).toBeTruthy();
      expect(screen.getByTestId("body-2")).toBeTruthy();
    });

    it("still labels each body with its heading", async () => {
      const { container } = await renderStaticAccordion();

      const region = container.querySelector("#\\31 -panel") as HTMLElement;
      expect(region.getAttribute("role")).toBe("region");
      expect(region.getAttribute("aria-labelledby")).toBe("1-header");
    });

    it("leaves the body unpinned, so it cannot go stale", async () => {
      const { container } = await renderStaticAccordion();

      // The measured pixel height exists only to give the open/close
      // transition something to animate between. Nothing animates here.
      const region = container.querySelector("#\\31 -panel") as HTMLElement;
      expect(region.className).not.toContain("var(--panel-height)");
    });
  });

  describe("description", () => {
    it("sits under the heading in a static panel", async () => {
      await renderStaticAccordion("Where the cart goes on a Saturday.");

      expect(screen.getByText("Where the cart goes on a Saturday.")).toBeTruthy();
    });

    it("sits inside the header button when the panel is a disclosure", async () => {
      render(defineComponent({
        components: { Accordion, AccordionPanel },
        setup: () => ({ expanded: ref(undefined) }),
        template: `
          <Accordion v-model="expanded">
            <AccordionPanel :unique-id="1" description="Where the cart goes on a Saturday.">
              <template #title>Town Square</template>
              <p data-testid="body-1">Town Square shifts</p>
            </AccordionPanel>
          </Accordion>
        `,
      }));
      await nextTick();

      const header = screen.getByRole("button", { name: townSquare });
      expect(header.textContent).toContain("Where the cart goes on a Saturday.");
    });
  });

  describe("borders", () => {
    /**
     * Reached through the headings rather than as children of `.accordion`,
     * because the test setup stubs the transition group the panels sit in.
     */
    const panelsIn = (container: Element) =>
      Array.from(container.querySelectorAll("[role='heading']"), (heading) => heading.parentElement as HTMLElement);

    it("shares edges down a stack, so no line is drawn twice", async () => {
      const { container } = await renderAccordion();

      // Every panel gives up its bottom border and leans on the next panel's
      // top border as the divider. Drawing both would put 2px between each
      // pair and 2px around a stack that sits inside another bordered box.
      const [first, second] = panelsIn(container);
      expect(panelsIn(container)).toHaveLength(2);
      expect(first.className).toContain("border-b-0");
      expect(first.className).toContain("first:rounded-t");
      // The last panel closes the stack off again.
      expect(second.className).toContain("last:border-b");
      expect(second.className).toContain("last:rounded-b");
      // Not at any breakpoint: a wider screen does not put gaps in this stack,
      // so handing every panel its own closed box only doubles the dividers.
      expect(second.className).not.toContain("sm:border-b");
      expect(second.className).not.toContain("sm:rounded");
    });

    it("gives a static panel a box of its own", async () => {
      const { container } = await renderStaticAccordion();

      // A static layout spaces the panels apart, so each closes its own outline
      // rather than borrowing the next one's.
      expect(panelsIn(container)).toHaveLength(2);
      for (const panel of panelsIn(container)) {
        expect(panel.className).toContain("rounded");
        expect(panel.className).not.toContain("border-b-0");
        expect(panel.className).not.toContain("first:rounded-t");
      }
    });
  });
});
