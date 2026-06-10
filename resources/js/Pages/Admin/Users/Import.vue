<script setup lang="ts">
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed, watch } from "vue";
import FileUpload from "@/Components/Form/FileUpload.vue";
import useSessionFlash from "@/Composables/useSessionFlash";
import useToast from "@/Composables/useToast";

const form = useForm<{ file: File | null }>({
  file: null,
});

const toast = useToast();
const page = usePage();
const flash = useSessionFlash();

watch(() => form.wasSuccessful, (value, oldValue) => {
  if (oldValue && !value) {
    form.reset();
  }
});

const uploadFile = () => {
  form.post(route("admin.users.import.import"), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
    onError: () => {
      form.reset("file");
      toast.error(
        "File could not be imported. Please check the validation messages",
        "Not Saved!",
        { group: "center" },
      );
    },
  });
};

watch(() => page.props.jetstream, (value) => {

  flash(
    page.props.jetstream,
    page.props.jetstream.flash.bannerTitle || "Success!",
    page.props.jetstream.flash.bannerStyle || undefined,
  );
}, { deep: true });

const listRouteAction = () => {
  router.visit(route("admin.users.index"));
};

const validationErrors = computed(() => {
  if (form.errors && Object.keys(form.errors).length === 0 && Object.getPrototypeOf(form.errors) === Object.prototype) {
    return [];
  }

  const errors = [];
  for (const errorsKey in form.errors) {
    const error = form.errors[errorsKey as keyof typeof form.errors];
    errors.push(error);
  }

  return errors;
});

const templateFile = route("admin.user-import-template");

const isReadyForUpload = computed(() => {
  if (form.recentlySuccessful) return true;
  // noinspection RedundantIfStatementJS
  if (form.file) return true;
  return false;
});

const label = computed(() => {
  if (form.hasErrors) {
    return "Please fix the above errors and try again";
  }
  return isReadyForUpload.value ? "Upload and Import" : "To upload: 'Choose' a file using the field above";
});
</script>

<template>
  <PageHeader title="Import Users">
    <div class="flex justify-between">
      <h2>Import Users</h2>
      <BackButton class="mx-3" outline @click.prevent="listRouteAction"/>
    </div>
  </PageHeader>
  <div class="pt-10 mx-auto max-w-7xl sm:px-6">
    <div v-if="validationErrors.length"
         class="overflow-auto px-5 py-3 mb-6 text-white rounded bg-warning dark:bg-warning-light min-h-10 max-h-96 overflow-y-scroll">
      <h4>
        Oops! it seems you have
        {{ validationErrors.length === 1 ? "a problem" : "some problems" }}
        with the spreadsheet you uploaded:
      </h4>
      <ul class="formatted-list">
        <li v-for="error in validationErrors" :key="error">
          {{ error }}
        </li>
      </ul>
    </div>

    <div class="flex flex-col px-4 py-3 rounded bg-sub-panel dark:bg-sub-panel-dark std-border">
      <form class="flex flex-col w-full" @submit.prevent="uploadFile">
        <div class="flex flex-col gap-3 w-full">
          <div class="flex flex-col gap-3">
            <h3>User Import</h3>
            <div class="flex flex-col gap-4 px-3 py-6 rounded border std-border">
              <ol class="formatted-list">
                <li>
                  Download and open the
                  <a :href="templateFile" class="align-middle inline-flex items-center gap-1">
                    Template Excel file
                    <span class="iconify mdi--cloud-download-outline text-sm"></span>
                  </a>
                </li>
                <li>
                  <strong>Closely</strong> follow the instructions that appear at the top of the Excel file.
                  <ul>
                    <li>
                      You can copy and past data from another source into the excel file but please make sure the data
                      conforms to the instructions.
                    </li>
                    <li>
                      If the importer finds an email address that already belongs to a user in the system, it will
                      update the data for that user. Otherwise, it will create a new user.
                    </li>
                    <li>
                      Note, if you wish to update a users' email address, you'll need to do that manually by editing
                      that user on this site.
                    </li>
                  </ul>
                </li>
                <li>
                  Upload the file. If there are any issues, you will be notified on what the issues are and what needs
                  fixing.
                  <ul>
                    <li>If there are issues, follow the instructions, fix them and re-upload.</li>
                    <li>Note, you may need to reload this page in order for the upload to work again.</li>
                  </ul>
                </li>
              </ol>

              <PMessage severity="warn" variant="outlined" size="small" class="mt-4">
                <strong>Please note:</strong>
                Any <em>new users</em>
                in the uploaded Excel file will be sent an 'account activation' email.
              </PMessage>
            </div>
          </div>

          <div class="flex flex-col gap-2 mt-4">
            <FileUpload v-model="form.file"
                        label="Upload your spreadsheet here:"
                        accept="application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv">
              <template #footer>
                <div class="text-xs italic text-gray-500 dark:text-gray-300" id="file-input-help">
                  XLSX, XLS or CSV files only
                </div>
                <div class="text-sm text-blue-500 dark:text-gray-300" id="file-input-download">
                  <a :href="templateFile" class="inline-flex gap-1 items-center">
                    <span class="iconify mdi--cloud-download-outline"></span>
                    Download the Template Excel file
                  </a>
                </div>
              </template>
            </FileUpload>
          </div>
        </div>
        <div class="flex justify-end mt-3 w-full">
          <SubmitButton type="submit"
                        :label
                        :icon="isReadyForUpload ? undefined : 'iconify mdi--arrow-up fade-out-up'"
                        :disabled="!form.file"
                        :processing="form.processing"
                        :success="form.recentlySuccessful"
                        :failure="form.hasErrors"
                        :errors="form.errors" />
        </div>
        <div class="w-full">
          <progress v-if="form.progress" :value="form.progress.percentage" max="100">
            {{ form.progress.percentage }}%
          </progress>
        </div>
      </form>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import 'vue3-easy-data-table/dist/style.css';
</style>
