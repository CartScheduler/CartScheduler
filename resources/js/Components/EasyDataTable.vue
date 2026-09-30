<!--
  Not `DataTable`: PrimeVue declares a global component of that name whatever
  prefix the resolver is given, so the two collided. The auto-import resolved to
  this file and the types described PrimeVue's, which is why the slots below
  read as missing. Named for what it wraps instead.
-->
<script setup lang="ts">
import { computed } from "vue";
// The package ships types for its rows, headers and filters but never declares
// the component they belong to, so its own documented default import does not
// exist as far as the checker is concerned.
// @ts-expect-error -- no declaration for the component itself
import EasyDataTable from "vue3-easy-data-table";
import type { FilterOption, Header, Item } from "vue3-easy-data-table";

/**
 * The package types a column's `width` as a number, but the table takes the CSS
 * width its callers pass — every header in this project is a percentage string.
 */
type TableHeader = Omit<Header, "width"> & { width?: number | string };

const { showHover = true } = defineProps<{
  headers?: TableHeader[];
  items?: Item[];
  filterOptions?: FilterOption[];
  searchField?: string;
  searchValue?: string;
  showHover?: boolean;
}>();

defineEmits<{ "click-row": [row: Item] }>();

// Every slot is handed straight through to the table, so what they are named is
// the caller's business — `item-email`, `item-is_enabled` and so on, one per
// column. Naming them individually here would mean editing this file for each.
defineSlots<Record<string, (props: Item) => unknown>>();

const cursor = computed(() => showHover ? "pointer" : "default");
const rowHoverColor = computed(() => showHover ? "var(--tw-bg-200)" : "transparent");
</script>

<template>
  <EasyDataTable buttons-pagination
                 :headers="headers"
                 :items="items"
                 :search-field="searchField"
                 :search-value="searchValue"
                 :filter-options="filterOptions"
                 theme-color="rgb(55 65 81)"
                 table-class-name="data-table"
                 body-row-class-name="data-table-row"
                 @click-row="$emit('click-row', $event)">
    <template v-for="slot of Object.keys($slots)" :key="slot" v-slot:[slot]="data">
      <slot :name="slot" v-bind="data"></slot>
    </template>
  </EasyDataTable>
</template>

<style lang="postcss">
@import 'vue3-easy-data-table/dist/style.css';

.data-table {
    --tw-th-bg: rgb(221 214 254);
    --tw-bg-200: rgb(237 233 254);
    --tw-bg-900: rgb(17 24 39);

    --easy-table-border: none;
    --easy-table-header-font-size: 1rem;
    --easy-table-header-item-padding: 1rem 15px;
    --easy-table-header-background-color: var(--tw-th-bg);

    --easy-table-body-row-font-size: 1rem;
    --easy-table-row-border: none;
    --easy-table-body-item-padding: .2rem 1rem;

    --easy-table-body-row-background-color: transparent;
    --easy-table-body-row-hover-background-color: v-bind(rowHoverColor);
    --easy-table-body-row-hover-font-color: var(--tw-bg-900);

    --easy-table-footer-font-size: 1rem;

    table {
        cursor: v-bind(cursor);
        border-collapse: separate;

        thead tr {
            th:first-child {
                @apply rounded-l-lg;
            }

            th:last-child {
                @apply rounded-r-lg;
            }
        }

        tbody tr:hover {
            td:first-child {
                @apply rounded-l-lg;
            }

            td:last-child {
                @apply rounded-r-lg;
            }
        }
    }

    .vue3-easy-data-table__footer {
        .easy-data-table__rows-selector {
            .rows-input__wrapper {
                border-bottom-width: 0;
            }
        }

        ul.select-items.show {
            @apply rounded-lg;

            li {
                text-align: center;

                &:first-child {
                    @apply rounded-t-lg;
                }

                &:last-child {
                    @apply rounded-b-lg;
                }
            }
        }
    }
}

.dark .data-table {
    --tw-dark-text-col: rgb(243 244 246);
    --tw-bg-200: rgb(71 85 105);

    --easy-table-header-background-color: rgb(51 65 85);
    --easy-table-header-font-color: rgb(243 244 246);
    --easy-table-body-row-font-color: rgb(243 244 246);
    --easy-table-body-row-hover-background-color: v-bind(rowHoverColor);
    --easy-table-body-row-hover-font-color: rgb(243 244 246);
    --easy-table-footer-background-color: transparent;
    --easy-table-footer-font-color: var(--tw-dark-text-col);

    .vue3-easy-data-table__footer {
        .buttons-pagination {
            .item.button.active {
                border-color: var(--easy-table-buttons-pagination-border) !important;
            }
        }
    }
}
</style>
