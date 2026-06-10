<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import JetActionMessage from "@/Jetstream/ActionMessage.vue";
import JetActionSection from "@/Jetstream/ActionSection.vue";
import JetDialogModal from "@/Jetstream/DialogModal.vue";
import JetInputError from "@/Jetstream/InputError.vue";

export interface InertiaSession {
  "agent": {
    "is_desktop": boolean;
    "platform": string;
    "browser": string;
  };
  "ip_address": string;
  "is_current_device": boolean;
  "last_active": string;
}

defineProps<{
  sessions: InertiaSession[];
}>();

const confirmingLogout = ref(false);
const form = useForm({
  password: "",
});

const confirmLogout = () => {
  confirmingLogout.value = true;
};

const logoutOtherBrowserSessions = () => {
  form.delete(route("other-browser-sessions.destroy"), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onFinish: () => form.reset(),
  });
};

const closeModal = () => {
  confirmingLogout.value = false;

  form.reset();
};
</script>

<template>
  <JetActionSection>
    <template #title>
      Browser Sessions
    </template>

    <template #description>
      Manage and log out your active sessions on other browsers and devices.
    </template>

    <template #content>
      <div class="max-w-xl text-sm bg-sub-panel dark:bg-sub-panel-dark p-3 rounded">
        If necessary, you may log out of all of your other browser sessions across all of your devices. Some of
        your recent sessions are listed below; however, this list may not be exhaustive. If you feel your
        account has been compromised, you should also update your password.
      </div>

      <!-- Other Browser Sessions -->
      <div v-if="sessions.length > 0" class="mt-5 space-y-6">
        <div v-for="(session, i) in sessions" :key="i" class="flex items-center">
          <div>
            <span v-if="session.agent.is_desktop" class="iconify mdi--laptop text-2xl"></span>
            <span v-else class="iconify mdi--mobile-phone text-2xl"></span>
          </div>

          <div class="ml-3">
            <div class="text-sm">
              {{ session.agent.platform ? session.agent.platform : "Unknown" }} -
              {{ session.agent.browser ? session.agent.browser : "Unknown" }}
            </div>

            <div>
              <div class="text-xs">
                {{ session.ip_address }},

                <span v-if="session.is_current_device"
                      class="text-green-500 dark:text-green-400 font-semibold">
                  This device
                </span>
                <span v-else>Last active {{ session.last_active }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-start items-center mt-5">
        <PButton variant="outlined" severity="warn" @click="confirmLogout">
          Log Out Other Browser Sessions
        </PButton>

        <JetActionMessage :on="form.recentlySuccessful" class="ml-3">
          Done.
        </JetActionMessage>
      </div>

      <!-- Log Out Other Devices Confirmation Modal -->
      <JetDialogModal :show="confirmingLogout" @close="closeModal">
        <template #title>
          Log Out Other Browser Sessions
        </template>

        <template #content>
          Please enter your password to confirm you would like to log out of your other browser sessions
          across all of your devices.

          <div class="mt-4">
            <PPassword v-model="form.password"
                       :feedback="false"
                       class="mt-1 block w-3/4"
                       placeholder="Password"
                       @keyup.enter="logoutOtherBrowserSessions" />

            <JetInputError :message="form.errors.password" class="mt-2" />
          </div>
        </template>

        <template #footer>
          <PButton variant="outlined" @click="closeModal">
            Cancel
          </PButton>

          <PButton class="ml-3"
                   :class="{ 'opacity-25': form.processing }"
                   :disabled="form.processing"
                   @click="logoutOtherBrowserSessions">
            Log Out Other Browser Sessions
          </PButton>
        </template>
      </JetDialogModal>
    </template>
  </JetActionSection>
</template>
