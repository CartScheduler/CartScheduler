<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { moveArrayElement, useSortable } from "@vueuse/integrations/useSortable";
import { nextTick, onMounted, onUnmounted, ref, watch, useTemplateRef, toRef } from "vue";
import DragDrop from "@/Components/Icons/DragDrop.vue";
import useToast from "@/Composables/useToast";
import type { SortableEvent } from "sortablejs";

const props = defineProps<{
  locations: App.Data.LocationAdminData[];
}>();

const locations = toRef(props.locations);

const toast = useToast();

const onNewLocation = () => {
  if (isSortingMode.value) {
    toast.warning("You cannot create a new location while sorting is enabled");
    return;
  }
  router.visit(route("admin.locations.create"));
};

const locationClicked = (location: App.Data.LocationAdminData) => {
  if (isSortingMode.value) {
    return;
  }
  router.visit(route("admin.locations.edit", { id: location.id }));
};

const locationsRef = useTemplateRef("locationsRef");

const storeSortOrder = async (locations: App.Data.LocationAdminData[]) => {
  try {
    await axios.put(route("admin.locations.sort-order"), {
      locations: locations.map((location) => location.id),
    });
  } catch {
    return false;
  }

  return true;
};

const doSort = async (event: SortableEvent) => {
  if (event.oldIndex === undefined || event.newIndex === undefined || event.oldIndex === event.newIndex) {
    return;
  }

  moveArrayElement(locations.value, event.oldIndex, event.newIndex, event);
  await nextTick();

  const success = await storeSortOrder(locations.value);
  if (!success) {
    toast.error("There was an error while saving the sort order. The order has been reverted. Please try again.");
    return;
  }
  toast.success("The sort order has been saved successfully");
};

const { start, stop, option } = useSortable(locationsRef, locations, {
  animation: 250,
  disabled: true,
  onUpdate: doSort,
});

const pause = () => {
  option("disabled", true);
};
const resume = () => {
  option("disabled", false);
};

onMounted(() => start());
onUnmounted(() => stop());

const isSortingMode = ref(false);
watch(isSortingMode, (value) => {
  if (value) {
    resume();
  } else {
    pause();
  }
});

const transitionDelayStyle = (index: number) => `animation-delay: -${(index * 0.1173)}s`;
</script>

<template>
  <PageHeader title="Locations">
    <div class="flex justify-between">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Locations</h2>
      <div class="flex">
        <PButton outline
                 :severity="isSortingMode ? 'danger' : 'secondary'"
                 class="mx-3"
                 @click="isSortingMode = !isSortingMode">
          <drag-drop color="currentColor" box="16" />
          <span class="ml-3">{{ isSortingMode ? "Stop sorting" : "Sort locations" }}</span>
        </PButton>
        <PButton class="mx-3" severity="primary" @click="onNewLocation">
          New Location
        </PButton>
      </div>
    </div>
  </PageHeader>
  <div ref="locationsRef"
       class="max-w-7xl mx-auto py-10 sm:px-6 grid grid-cols-1 content-start sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"
       :class="{ '!gap-4': isSortingMode }">
    <div v-for="(location, idx) in locations"
         :key="location.id"
         class="shadow-sm card subtle-zoom action cursor-pointer duration-500 scale-[.98] transition-all"
         :class="{ 'is-sorting-mode !scale-100': isSortingMode }"
         :style="transitionDelayStyle(idx)"
         @click="locationClicked(location)">
      <div class="flex items-start gap-8 h-full dark:text-gray-100">
        <div>
          <h4 class="font-semibold">{{ location.name }}</h4>
          <div class="line-clamp-3">{{ location.clean_description }}</div>
        </div>
        <div>
          <h5 class="font-semibold text-center">Shifts</h5>
          <div class="text-center text-6xl">{{ location.shifts?.length }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="postcss">
@import 'vue3-easy-data-table/dist/style.css';
@tailwind;

/*noinspection CssUnusedSymbol*/
.sortable-ghost {
  @apply opacity-40 border-dashed border-2 border-gray-700 dark:border-slate-300;
}

.is-sorting-mode {
  @apply select-none shadow-md hover:bg-white dark:hover:bg-slate-700 cursor-grab;
  @apply ease-[linear(0,_0.245_3.5%,_0.466_7.2%,_0.653_10.9%,_0.816_14.8%,_0.887_16.8%,_0.951_18.8%,_1.009_20.9%,_1.06_23%,_1.103_25.1%,_1.142_27.3%,_1.175_29.6%,_1.201_31.9%,_1.216_33.6%,_1.229_35.4%,_1.239_37.2%,_1.245_39.1%,_1.249_41.1%,_1.25_43.1%,_1.248_45.2%,_1.242_47.4%,_1.228_51.1%,_1.205_55.3%,_1.178_59.6%,_1.085_72.9%,_1.059_77.2%,_1.038_81.2%,_1.02_85.7%,_1.008_90.2%,_1.002_94.8%,_1)];
  animation-name: shake-left-right;
  animation-duration: 1500ms;
  animation-timing-function: cubic-bezier(0.86, 0, 0.14, 1);
  animation-iteration-count: infinite;
}

@keyframes shake-left-right {
  0%, 20%, 40%, 60%, 80%, 100% {
    rotate: -.25deg;
  }
  10%, 30%, 50%, 70%, 90% {
    rotate: .25deg;
  }
}
</style>
