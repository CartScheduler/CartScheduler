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

const townSquare = /Town Square/;
const marketStreet = /Market Street/;

const toggle = (name: RegExp) => fireEvent.click(screen.getByRole("button", { name }));

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
});
