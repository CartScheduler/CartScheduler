# Frontend Testing Guide

This guide explains how to write and run tests for the CartApp frontend components and utilities.

## Test Setup

The project uses the following testing tools:

- **Vitest**: Test runner compatible with Vue 3 and TypeScript
- **Vue Test Utils**: Official testing library for Vue components
- **JSDOM**: Browser environment simulation
- **Testing Library**: Additional utilities for component testing

## Running Tests

### Run all tests
```bash
npm run test
```

### Run tests in watch mode (for development)
```bash
npm run test:watch
```

### Run tests with coverage report
```bash
npm run test:coverage
```

## Test Structure

Tests are organized in the `resources/js/__tests__` directory with the following structure:

- `components/`: Tests for Vue components
- `composables/`: Tests for Vue composables
- `utils/`: Test utilities and helpers

## Writing Tests

### Component Tests

Component tests should verify:

1. Component renders correctly with props
2. User interactions work as expected
3. Events are emitted correctly
4. Component state updates properly

Example:

```typescript
import { describe, it, expect } from "vitest";
import { mountWithPlugins } from "../utils/test-utils";
import MyComponent from "@/Components/MyComponent.vue";

describe("MyComponent.vue", () => {
  it("renders with props", () => {
    const wrapper = mountWithPlugins(MyComponent, {
      props: {
        title: "Test Title",
      },
    });
    
    expect(wrapper.text()).toContain("Test Title");
  });
  
  it("emits an event when button is clicked", async () => {
    const wrapper = mountWithPlugins(MyComponent);
    
    await wrapper.find("button").trigger("click");
    expect(wrapper.emitted("click")).toBeTruthy();
  });
});
```

### Composable Tests

Composable tests should verify:

1. Initial state is set correctly
2. Methods modify state as expected
3. Reactive state updates properly
4. External dependencies are called correctly

Example:

```typescript
import { describe, it, expect } from "vitest";
import { testComposable } from "../utils/composable-testing-utils";
import { useMyComposable } from "@/Composables/useMyComposable";

describe("useMyComposable", () => {
  it("initializes with default state", () => {
    const { composable } = testComposable(useMyComposable);
    
    expect(composable.count.value).toBe(0);
  });
  
  it("increments count when increment is called", async () => {
    const { composable } = testComposable(useMyComposable);
    
    await composable.increment();
    expect(composable.count.value).toBe(1);
  });
});
```
## Best Practices

1. **Isolate tests**: Each test should be independent of others
2. **Mock external dependencies**: Use mocks for API calls, Inertia, etc.
3. **Test user interactions**: Simulate user events like clicks and form submissions
4. **Verify component output**: Check that components render correctly
5. **Test edge cases**: Consider error states, empty data, etc.
6. **Keep tests simple**: Test one thing at a time
7. **Use data-testid attributes**: Add `data-testid` to elements for reliable selection
8. **Aim for coverage**: Try to cover all critical components and logic
9. **Globals**: Use vi.stubGlobal() for creating global mocks
10. **Quotes**: Use double quotes for strings unless interpolation is required

## Continuous Integration

Tests are automatically run on GitHub Actions when code is pushed to the repository. The workflow is defined in `.github/workflows/frontend-tests.yml`.
