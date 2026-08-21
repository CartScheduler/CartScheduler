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
});
