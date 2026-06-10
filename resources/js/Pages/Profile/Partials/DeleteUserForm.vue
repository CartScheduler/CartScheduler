<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import JetActionSection from "@/Jetstream/ActionSection.vue";
import JetDangerButton from "@/Jetstream/DangerButton.vue";
import JetDialogModal from "@/Jetstream/DialogModal.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetSecondaryButton from "@/Jetstream/SecondaryButton.vue";

const confirmingUserDeletion = ref(false);

const form = useForm({
  password: "",
});

const confirmUserDeletion = () => {
  confirmingUserDeletion.value = true;
};

const deleteUser = () => {
  form.delete(route("current-user.destroy"), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onFinish: () => form.reset(),
  });
};

const closeModal = () => {
  confirmingUserDeletion.value = false;

  form.reset();
};
</script>

<template>
  <JetActionSection>
    <template #title>
      Delete Account
    </template>

    <template #description>
      Permanently delete your account.
    </template>

    <template #content>
      <div class="max-w-xl text-sm text-gray-600 dark:text-gray-100">
        Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting
        your account, please download any data or information that you wish to retain.
      </div>

      <div class="mt-5">
        <JetDangerButton @click="confirmUserDeletion">
          Delete Account
        </JetDangerButton>
      </div>

      <!-- Delete Account Confirmation Modal -->
      <JetDialogModal :show="confirmingUserDeletion" @close="closeModal">
        <template #title>
          Delete Account
        </template>

        <template #content>
          Are you sure you want to delete your account? Once your account is deleted, all of its resources and
          data will be permanently deleted. Please enter your password to confirm you would like to
          permanently delete your account.

          <div class="mt-4">
            <PPassword v-model="form.password"
                       :feedback="false"
                       class="mt-1 block w-3/4"
                       placeholder="Password"
                       @keyup.enter="deleteUser" />

            <JetInputError :message="form.errors.password" class="mt-2" />
          </div>
        </template>

        <template #footer>
          <JetSecondaryButton @click="closeModal">
            Cancel
          </JetSecondaryButton>

          <JetDangerButton class="ml-3"
                           :class="{ 'opacity-25': form.processing }"
                           :disabled="form.processing"
                           @click="deleteUser">
            Delete Account
          </JetDangerButton>
        </template>
      </JetDialogModal>
    </template>
  </JetActionSection>
</template>
