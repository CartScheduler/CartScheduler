<script setup lang="ts">
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import axios from "axios";
import { computed, nextTick, ref, useTemplateRef, watch } from "vue";

const { gender, userId } = defineProps<{
  /** The gender of the user being edited; candidates are the opposite gender. */
  gender: "male" | "female" | undefined;
  /** Absent when adding a user. */
  userId?: number | undefined;
}>();

const visible = defineModel<boolean>("visible", { required: true });

const emit = defineEmits<{
  select: [spouse: App.Data.SpouseAdminData];
}>();

/** A bottom sheet suits a phone; on anything wider the dialog floats in the centre. */
const isMobile = useBreakpoints(breakpointsTailwind).smaller("sm");

const candidates = ref<App.Data.SpouseAdminData[]>([]);
const search = ref("");
const isLoading = ref(false);
const hasLoadError = ref(false);
/** The candidate the arrow keys have moved to; -1 while none has been reached. */
const activeIndex = ref(-1);

const searchField = useTemplateRef<{ $el: HTMLInputElement }>("searchField");
const candidateList = useTemplateRef<HTMLUListElement>("candidateList");

const filteredCandidates = computed(() => {
  const term = search.value.trim().toLowerCase();
  return term
    ? candidates.value.filter((candidate) => candidate.name.toLowerCase().includes(term))
    : candidates.value;
});

const loadCandidates = async () => {
  if (!gender) {
    candidates.value = [];
    return;
  }

  isLoading.value = true;
  hasLoadError.value = false;
  try {
    const response = await axios.get<App.Data.SpouseAdminData[]>(
      route("admin.spouse-candidates.get", { gender, user_id: userId }),
    );
    candidates.value = response.data;
  } catch {
    candidates.value = [];
    hasLoadError.value = true;
  } finally {
    isLoading.value = false;
  }
};

watch(visible, (isVisible) => {
  if (!isVisible) {
    return;
  }

  search.value = "";
  activeIndex.value = -1;
  void loadCandidates();

  // Typing is the point of this dialog on a desktop; on a phone it would only
  // throw up the on-screen keyboard over the list. The wait is for `showModal()`,
  // which opens the dialog after this and puts the focus on its first control.
  if (!isMobile.value) {
    setTimeout(() => searchField.value?.$el.focus());
  }
}, { immediate: true });

/** A shorter search can leave the highlight past the end of the list. */
watch(filteredCandidates, () => {
  activeIndex.value = -1;
});

const moveActive = (step: number) => {
  const lastIndex = filteredCandidates.value.length - 1;
  if (lastIndex < 0) {
    return;
  }

  activeIndex.value = activeIndex.value === -1
    ? (step > 0 ? 0 : lastIndex)
    : Math.min(Math.max(activeIndex.value + step, 0), lastIndex);

  void nextTick(() => {
    candidateList.value
      ?.querySelectorAll("li")
      ?.[activeIndex.value]
      ?.scrollIntoView({ block: "nearest" });
  });
};

/** Empty while the arrow keys have not reached a candidate. */
const activeDescendantId = computed(() => {
  const candidate = filteredCandidates.value[activeIndex.value];
  return candidate ? `spouse-candidate-${candidate.id}` : "";
});

const selectActive = () => {
  const candidate = filteredCandidates.value[activeIndex.value];
  if (candidate) {
    selectSpouse(candidate);
  }
};

function selectSpouse(spouse: App.Data.SpouseAdminData) {
  emit("select", spouse);
  visible.value = false;
}
</script>

<template>
  <Dialog v-model:visible="visible"
          dismissable-mask
          class="sm:w-full sm:max-w-md"
          :position="isMobile ? 'bottom' : 'center'">
    <template #header>
      <h3 class="text-xl font-semibold">Select Spouse</h3>
    </template>

    <div class="flex flex-col gap-3">
      <p v-if="!gender" class="text-neutral-500 dark:text-neutral-300">
        Please set the user's gender before selecting a spouse.
      </p>

      <template v-else>
        <PInputText ref="searchField"
                    v-model="search"
                    type="search"
                    placeholder="Search by name"
                    aria-label="Search by name"
                    autocomplete="off"
                    role="combobox"
                    aria-controls="spouse-candidates"
                    :aria-activedescendant="activeDescendantId"
                    fluid
                    @keydown.down.prevent="moveActive(1)"
                    @keydown.up.prevent="moveActive(-1)"
                    @keydown.enter.prevent="selectActive" />

        <p v-if="isLoading" role="status" class="text-neutral-500 dark:text-neutral-300">
          Loading...
        </p>
        <p v-else-if="hasLoadError" class="text-red-600 dark:text-red-400">
          The list of users could not be loaded. Please try again.
        </p>
        <p v-else-if="!filteredCandidates.length" class="text-neutral-500 dark:text-neutral-300">
          No matching users found.
        </p>
        <ul v-else
            id="spouse-candidates"
            ref="candidateList"
            class="flex flex-col divide-y divide-neutral-200 dark:divide-neutral-700">
          <li v-for="(candidate, index) in filteredCandidates" :key="candidate.id">
            <button :id="`spouse-candidate-${candidate.id}`"
                    type="button"
                    role="option"
                    :aria-selected="index === activeIndex"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 rounded px-2 py-3 text-left
                           hover:bg-neutral-100 dark:hover:bg-neutral-800"
                    :class="{ 'bg-neutral-100 dark:bg-neutral-800': index === activeIndex }"
                    @click="selectSpouse(candidate)">
              <span>{{ candidate.name }}</span>
            </button>
          </li>
        </ul>
      </template>
    </div>

    <template #footer>
      <PButton label="Cancel"
               icon="iconify mdi--close-circle-outline"
               severity="secondary"
               variant="text"
               @click="visible = false" />
    </template>
  </Dialog>
</template>
