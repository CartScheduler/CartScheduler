<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { inject, onMounted, ref } from "vue";
import SubmitButton from "@/Components/Form/Buttons/SubmitButton.vue";
import useToast from "@/Composables/useToast";
import JetActionMessage from "@/Jetstream/ActionMessage.vue";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";

const { selectedLocations, userId = null } = defineProps<{
  selectedLocations?: { [key: number]: number };
  userId?: number | null;
}>();

const form = useForm({
  selectedLocations: selectedLocations || [],
});

const toast = useToast();

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
    .put(route("update.user.location-choices"), {
      preserveScroll: true,
      onSuccess: () => toast.success(
        "Your location preferences have been updated.",
        "Success!",
        { group: "center" },
      ),
      onError: () => toast.error(
        "Your location preferences could not be updated due to invalid data",
        "Not Saved!",
        { group: "center" },
      ),
    });
};

const resetForm = () => {
  form.reset();
  form.clearErrors();
};

const locations = ref<Array<App.Data.LocationChoiceData>>([]);

onMounted(async () => {
  const response = await axios.get<Array<App.Data.LocationChoiceData>>(route("user.location-choices"));
  locations.value = response.data;
});
</script>

<template>
  <JetFormSection @submitted="update">
    <template #title>
      Locations
    </template>

    <template #description>
      Indicate which locations are convenient for {{ userId ? 'this volunteer' : 'you' }} to volunteer at.
    </template>

    <template #form>
      <div class="grid grid-cols-6 col-span-6 gap-3">
        <div v-for="location in locations" :key="location.id" class="flex flex-wrap col-span-2 items-center">
          <PCheckbox :input-id="`do_enable_location_choice_${location.id}`"
                     :value="location.id"
                     class="mr-3"
                     v-model="form.selectedLocations" />
          <JetLabel :for="`do_enable_location_choice_${location.id}`"
                    :value="location.name"
                    class="select-none" />
        </div>
      </div>
      <JetInputError :message="form.errors.featureDisabled" class="col-span-6 mt-2" />
    </template>

    <template #actions>
      <div class="flex flex-col">
        <JetActionMessage :on="form.recentlySuccessful" class="mr-3 w-full">
          Saved.
        </JetActionMessage>
        <div v-if="form.hasErrors" class="mr-3 font-bold text-red-500">
          Hmmm... There is a problem with your submission, please contact your overseer.
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
