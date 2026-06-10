<script setup lang="ts">
import { router, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { useConfirm } from "primevue";
import { computed, onMounted, ref } from "vue";
import OozeLoader from "@/Components/Loaders/OozeLoader.vue";
import useToast from "@/Composables/useToast";
import vAutoScroll from "@/Directives/v-autoscroll.js";
import JetFormSection from "@/Jetstream/FormSection.vue";

const { settings } = defineProps<{
  settings: App.Settings.GeneralSettings;
}>();

const processing = ref(false);
const betaCheck = ref(false);
const toast = useToast();
const confirm = useConfirm();

const updateCheck = async () => {
  processing.value = true;
  try {
    const response = await axios.get(route("admin.check-update"), { params: { beta: !!betaCheck.value } });
    if (!response.data) {
      toast.warning("No update available");
      return;
    }

    toast.success("Update available");
  } finally {
    processing.value = false;
  }
  router.visit(route("admin.settings"), { preserveState: false, preserveScroll: true });
};

const updateLog = ref("");
const updateCompleted = ref(false);
const hadUpdateError = ref(false);

const confirmUpdate = (event: Event) => {
  confirm.require({
    target: event.currentTarget as HTMLElement,
    message: "Are you sure you want to update?",
    header: "Confirm Update",
    icon: "iconify mdi--alert-circle-outline text-xl",
    acceptProps: {
      label: "Yes",
      severity: "danger",
      variant: "outlined",
    },
    rejectProps: {
      label: "No",
      severity: "primary",
    },
    accept: () => doSoftwareUpdate(),
  });
};

const doSoftwareUpdate = async () => {
  processing.value = true;
  updateLog.value = "";
  try {
    const response = await axios.post(route("admin.do-update"), {}, {
      responseType: "stream",
      adapter: "fetch",
    });
    const reader = response.data.getReader();
    const decoder = new TextDecoder();

    while (true) {
      const { done, value } = await reader.read();
      if (done) {
        break;
      }
      updateLog.value += decoder.decode(value);
    }

    if (response.status === 200) {
      toast.success("Update process completed. Please check the log for details.");
    } else {
      toast.error("Update process failed. Please check the log for details.");
    }
    updateCompleted.value = true;
  } catch (error) {
    console.error("Error during update:", error);
    toast.error("An error occurred during the update. Please try again.");
    hadUpdateError.value = true;
  } finally {
    processing.value = false;
  }
};

const hasUpdate = computed(() => {
  return !!usePage().props.hasUpdate && !updateLog.value;
});

const downloadLog = () => {
  if (!updateLog.value) return;

  // Create a blob with the log content
  const blob = new Blob([updateLog.value], { type: "text/plain" });
  const url = URL.createObjectURL(blob);
  const siteName = settings.siteName ? settings.siteName.toLowerCase().replace(/[^a-z\s]/g, "").replace(/\s+/g, "_") : "cart_scheduler";

  // Create a temporary link element to trigger the download
  const link = document.createElement("a");
  link.href = url;
  link.download = `${siteName}_update_${new Date().toISOString().slice(0, 19).replace(/[T:]/g, "-")}.txt`;
  document.body.appendChild(link);

  // Trigger the download
  link.click();

  // Clean up
  setTimeout(() => {
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }, 100);
};

const reloadSite = () => window.location.reload();

const adminUsers = ref();
onMounted(async () => {
  const response = await axios.get(route("admin.admin-users.get"));
  adminUsers.value = response.data.data;
});
</script>

<template>
  <JetFormSection>
    <template #title>
      Software Update
    </template>

    <template #description>
      <div v-if="hasUpdate" class="flex items-center content-center gap-2 text-help dark:text-help-light">
        <span class="iconify mdi--bell-circle text-md"></span>
        <div class="font-bold">Update Available!</div>
      </div>
      <div v-else>You are running the latest version of the scheduling software.</div>
    </template>

    <template #form>
      <template v-if="hasUpdate">
        <p class="col-span-12 bg-help dark:bg-help-light text-white dark:text-black p-3 rounded">
          The latest version is: <span class="font-mono font-bold">{{ settings.availableVersion }}</span>
          You are running version: <span class="font-mono">{{ settings.currentVersion }}</span>.
        </p>
        <p class="col-span-12 text-gray-600 dark:text-gray-300">
          <a :href="`https://github.com/pixelated-au/CartScheduler/releases/tag/${settings.availableVersion}`"
             target="_blank">
            Latest version release notes
          </a>.
        </p>
        <p class="col-span-12 text-gray-600 dark:text-gray-300 font-bold">
          Note: Updates should be safe to perform. However, sometimes they don't work as expected. It's
          generally wise to perform the update out of peak usage hours so potential issues can be addressed
          with minimum impact.
        </p>
      </template>

      <div v-else-if="updateLog" class="col-span-12 text-gray-600 dark:text-gray-300">
        <div class="font-bold flex items-center justify-between w-full">
          Update Log:
          <OozeLoader v-if="processing" />
        </div>
        <pre v-auto-scroll
             class="mt-2 rounded-md p-2 font-mono text-sm scroll-smooth max-w-full h-96 max-h-96 overflow-x-scroll bg-gray-200 dark:bg-gray-800"
             :class="{ 'bg-red-200': hadUpdateError, 'dark:bg-red-900/80': hadUpdateError }">{{ updateLog }}</pre>
      </div>

      <template v-else>
        <p class="col-span-12 text-gray-600 dark:text-gray-300">
          You are running the latest version of the
          scheduling software (version {{ settings.currentVersion }}).
        </p>
        <p class="col-span-12 text-gray-600 dark:text-gray-300">
          You can reinstall the current update. Note that
          this usually isn't required. Use only if directed to by your IT support.
        </p>
        <PMessage :severity="betaCheck ? 'error' : 'secondary'"
                  class="col-span-12 text-gray-600 dark:text-gray-300 font-bold"
                  :class="{ 'animate-pulse': betaCheck }">
          It's not recommended to use the <em>Beta update</em> option unless instructed by your IT support.
        </PMessage>
      </template>
    </template>

    <template #actions>
      <PConfirmPopup/>
      <PButton v-if="hasUpdate"
               label="Update Now"
               icon="iconify mdi--progress-download"
               :class="{ 'opacity-25': processing }"
               :disabled="processing"
               @click="confirmUpdate"/>

      <template v-else-if="!updateLog">
        <label class="mr-3 flex items-center text-gray-400 dark:text-gray-600">
          <PCheckbox binary v-model="betaCheck" />
          <span class="ml-1.5">Check for Beta updates</span>
        </label>
        <PButton label="Check for updates"
                 variant="outlined"
                 severity="danger"
                 :class="{ 'opacity-25': processing }"
                 class="mr-3"
                 :disabled="processing"
                 @click="updateCheck" />
        <PButton label="Reinstall current version"
                 severity="warning"
                 :class="{ 'opacity-25': processing }"
                 :disabled="processing" />
      </template>

      <template v-else-if="updateCompleted">
        <PButton label="Download update log"
                 severity="secondary"
                 class="mr-3"
                 :disabled="processing"
                 @click="downloadLog" />
        <PButton label="Reload the page to see changes"
                 severity="primary"
                 class="mr-3"
                 :disabled="processing"
                 @click="reloadSite" />
      </template>
    </template>
  </JetFormSection>
</template>
