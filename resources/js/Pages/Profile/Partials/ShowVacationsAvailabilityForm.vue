<script setup lang="ts">
import { Dropdown as VDropdown } from "floating-vue";
import SubmitButton from "@/Components/Form/Buttons/SubmitButton.vue";
import CloseCircle from "@/Components/Icons/CloseCircle.vue";
import QuestionCircle from "@/Components/Icons/QuestionCircle.vue";
import InputTextEIPField from "@/Components/InputTextEIPField.vue";
import useToast from "@/Composables/useToast";
import JetActionMessage from "@/Jetstream/ActionMessage.vue";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import VacationDateRange from "@/Pages/Profile/Partials/VacationDateRange.vue";
import precognitiveForm from "@/Utils/precognitiveForm";

const { vacations = [], userId } = defineProps<{
  vacations?: Array<App.Data.UserVacationData>;
  userId?: number;
}>();

const toast = useToast();

const form = precognitiveForm({
  routeName: "update.user.vacations",
  method: "put",
}, {
  vacations: vacations,
  deletedVacations: [] as Array<App.Data.UserVacationData>,
});

const update = () => {
  form.transform((data) => {
    if (userId) {
      return {
        ...data,
        user_id: userId,
      };
    }
    return data;
  })
    .submit({
      preserveScroll: true,
      onSuccess: () => toast.success(
        "Vacation information has been updated.",
        "Success!",
        { group: "center" },
      ),
      onError: () => toast.error(
        "Vacation information could not be updated. Please check the validation messages.",
        "Not Saved!",
        { group: "center" },
      ),
    });
};

const resetForm = () => {
  form.reset();
  form.clearErrors();
};

const addVacation = () => form.vacations = [...form.vacations, { start_date: "", end_date: "", description: "" }];

const deleteVacation = (idx: number) => form.deletedVacations = [...form.deletedVacations, form.vacations.splice(idx, 1)[0]];
</script>

<template>
  <JetFormSection @submitted="update">
    <template #title>
      Vacations & Time Off
    </template>

    <template #description>
      Indicate when {{ userId ? 'this volunteer' : 'you' }} may be on holiday or are unable to be rostered.
    </template>

    <template #form>
      <div class="col-span-6 text-gray-700 dark:text-gray-100">
        <div v-if="form.vacations?.length">
          <div v-for="(vacation, idx) in form.vacations"
               :key="vacation.id"
               class="grid grid-cols-[auto_minmax(0,_1fr)] sm:grid-cols-[auto_minmax(0,_2fr)] gap-y-px gap-x-3 rounded p-3 items-center mb-3 bg-sub-panel dark:bg-sub-panel-dark shadow">
            <PButton severity=""
                     type="button"
                     class="row-span-2 self-stretch px-1 py-1 bg-transparent dark:bg-transparent dark:border dark:border-slate-700 sm:row-span-2"
                     @click="deleteVacation(idx)">
              <CloseCircle />
            </PButton>
            <vacation-date-range v-model:start-date="vacation.start_date"
                                 v-model:end-date="vacation.end_date"
                                 :start-error="form.errors[`vacations.${idx}.start_date`]"
                                 :end-error="form.errors[`vacations.${idx}.end_date`]" />
            <div class="mt-2 sm:mt-0">
              <div class="flex items-center">
                <span class="font-bold">Comment</span>
                <v-dropdown v-if="vacation.id" class="inline-block ml-2">
                  <QuestionCircle />

                  <template #popper>
                    <div class="">
                      <p class="text-sm">Tap/click on the comment to edit it.</p>
                    </div>
                  </template>
                </v-dropdown>
              </div>
              <JetInput v-if="!vacation.id"
                        type="text"
                        class="w-full"
                        v-model="vacation.description" />
              <InputTextEIPField v-else
                                 input-class="w-full bg-text-input dark:bg-text-input-dark"
                                 v-model="vacation.description"
                                 :maxlength="250"
                                 empty-value="No comment set" />
              <div class="text-sm italic text-gray-500 dark:text-gray-400">This is for your reference only</div>
              <JetInputError :message="form.errors[`vacations.${idx}.description`]" />
            </div>
          </div>
        </div>
        <div v-else
             class="flex flex-wrap gap-y-px justify-center items-stretch p-3 rounded border-gray-200 bg-sub-panel dark:bg-sub-panel-dark dark:border-gray-900">
          <div class="w-full text-center">No vacations set</div>
        </div>
        <div class="flex justify-center">
          <PButton severity="primary"
                   variant="outlined"
                   icon="iconify mdi--add-circle-outline"
                   label="Add a New Vacation"
                   class="mt-3"
                   @click="addVacation" />
        </div>
      </div>
    </template>

    <template #actions>
      <div class="flex flex-col">
        <JetActionMessage :on="form.recentlySuccessful" class="mr-3 w-full">
          Saved.
        </JetActionMessage>
        <div v-if="form.hasErrors" class="mr-3 font-bold text-red-500">
          Oops! There seem to be some problems. Please check the validation messages.
        </div>
        <div class="flex justify-end">
          <PButton severity="secondary" type="button" class="mr-3" @click="resetForm">
            Cancel
          </PButton>
          <SubmitButton label="Save"
                        type="submit"
                        :processing="form.processing"
                        :success="form.wasSuccessful"
                        :failure="form.hasErrors"
                        :errors="form.errors"
                        @click.prevent="update" />
        </div>
      </div>
    </template>
  </JetFormSection>
</template>
