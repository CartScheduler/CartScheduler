<script setup lang="ts">
import { useForm, usePage } from "@inertiajs/vue3";
import { computed, nextTick, reactive, watch } from "vue";
import SubmitButton from "@/Components/Form/Buttons/SubmitButton.vue";
import useAvailabilityActions, { days } from "@/Composables/useAvailabilityActions";
import useToast from "@/Composables/useToast";
import JetActionMessage from "@/Jetstream/ActionMessage.vue";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";
import DayOfWeekConfiguration from "@/Pages/Profile/Partials/DayOfWeekConfiguration.vue";
import type { Day, DayOfMonthCount } from "@/Composables/useAvailabilityActions";

const { availability, userId = undefined } = defineProps<{
  availability: App.Data.AvailabilityData;
  userId?: number | undefined;
}>();

const page = usePage();
const toast = useToast();

const ranges = computed<{ start: App.Enums.AvailabilityHours; end: App.Enums.AvailabilityHours }>(() => ({
  start: page.props.shiftAvailability.systemShiftStartHour,
  end: page.props.shiftAvailability.systemShiftEndHour,
}));

const form = useForm({
  day_monday: availability.day_monday || [ranges.value.start, ranges.value.end],
  day_tuesday: availability.day_tuesday || [ranges.value.start, ranges.value.end],
  day_wednesday: availability.day_wednesday || [ranges.value.start, ranges.value.end],
  day_thursday: availability.day_thursday || [ranges.value.start, ranges.value.end],
  day_friday: availability.day_friday || [ranges.value.start, ranges.value.end],
  day_saturday: availability.day_saturday || [ranges.value.start, ranges.value.end],
  day_sunday: availability.day_sunday || [ranges.value.start, ranges.value.end],
  num_mondays: availability.num_mondays || 0 as DayOfMonthCount,
  num_tuesdays: availability.num_tuesdays || 0 as DayOfMonthCount,
  num_wednesdays: availability.num_wednesdays || 0 as DayOfMonthCount,
  num_thursdays: availability.num_thursdays || 0 as DayOfMonthCount,
  num_fridays: availability.num_fridays || 0 as DayOfMonthCount,
  num_saturdays: availability.num_saturdays || 0 as DayOfMonthCount,
  num_sundays: availability.num_sundays || 0 as DayOfMonthCount,
  comments: availability.comments || "",
});

const { computedRange, dayToggle, tooltipFormat } = useAvailabilityActions(form, ranges);

const rosters = reactive(
  Object.entries(days).map(([name, label]) => ({
    label,
    value: dayToggle(name as Day),
  })),
);

const hoursEachDay = reactive({
  monday: computedRange("monday"),
  tuesday: computedRange("tuesday"),
  wednesday: computedRange("wednesday"),
  thursday: computedRange("thursday"),
  friday: computedRange("friday"),
  saturday: computedRange("saturday"),
  sunday: computedRange("sunday"),
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
    .put(route("update.user.availability"), {
      preserveScroll: true,
      onSuccess: () => {
        // form.reset();
        toast.success(
          "Your availability preferences have been updated.",
          "Success!",
          { group: "center" },
        );
      },
      onError: () => {
        toast.error(
          "Your availability preferences could not be updated due to invalid data",
          "Not Saved!",
          { group: "center" },
        );
      },
    });
};

const resetForm = () => {
  form.reset();
  form.clearErrors();
};

const showConfigurations = computed(() => {
  return form.num_mondays > 0 || form.num_tuesdays > 0 || form.num_wednesdays > 0 || form.num_thursdays > 0 || form.num_fridays > 0 || form.num_saturdays > 0 || form.num_sundays > 0;
});

const maxCommentChars = 500;
const commentsRemainingCharacters = computed(() => {
  if (form.comments) {
    return maxCommentChars - form.comments.length;
  }
  return maxCommentChars;
});

watch(() => form.comments, (value, oldValue) => {
  if (value.length > maxCommentChars) {
    void nextTick(() => {
      form.comments = oldValue;
    });
  }
});
</script>

<template>
  <JetFormSection @submitted="update">
    <template #title>
      Regular Availability
    </template>

    <template #description>
      Please indicate {{ userId ? "this volunteers" : "your" }} availability
    </template>

    <template #form>
      <div
          class="grid grid-cols-4 col-span-6 gap-y-px items-stretch p-3 text-gray-700 rounded border border-gray-200 lg:grid-cols-7 dark:text-gray-100 dark:border-gray-900 bg-sub-panel dark:bg-sub-panel-dark">
        <div class="col-span-4 font-bold lg:col-span-7">
          {{ userId ? "Volunteer is" : "I am" }} available
          to be rostered:
        </div>
        <div v-for="roster in rosters"
             :key="roster.label"
             class="flex flex-col justify-center items-center text-center col">
          <div class="text-sm">
            {{ roster.label }}
          </div>
          <PToggleSwitch :id="`check-${roster.label}`" v-model="roster.value" label="Monday" />
        </div>
      </div>
      <div v-show="showConfigurations"
           class="grid grid-cols-12 col-span-6 gap-x-2 gap-y-8 items-center p-3 text-gray-700 rounded border border-gray-200 lg:gap-x-10 dark:text-gray-100 dark:border-gray-900 bg-sub-panel dark:bg-sub-panel-dark">
        <DayOfWeekConfiguration v-for="(label, name) in days"
                                :key="name"
                                v-model:hours-each-day="hoursEachDay[name as keyof typeof hoursEachDay]"
                                v-model:number-of-days-per-month="form[`num_${name}s` as keyof typeof form] as number"
                                :start="ranges.start"
                                :end="ranges.end"
                                :label="label"
                                :tooltip-format="tooltipFormat" />
      </div>

      <div class="col-span-6 items-stretch p-3 rounded border std-border bg-sub-panel dark:bg-sub-panel-dark">
        <JetLabel for="availability-comments" value="Comments (optional)" />
        <textarea id="availability-comments"
                  class="p-3 w-full h-40 rounded border std-border bg-text-input dark:bg-text-input-dark"
                  v-model="form.comments" />
        <div class="text-sm">{{ commentsRemainingCharacters }} characters remaining</div>
        <JetInputError :message="form.errors.comments" class="mt-2" />
        <div class="italic, text-gray-500 text-sm">
          If relevant, use this to provide any additional information about your availability that could
          assist in scheduling.
        </div>
      </div>
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

<style lang="scss" scoped>
.v-enter-active,
.v-leave-active {
  transition: opacity 0.5s ease;
}

.v-enter-from,
.v-leave-to {
  opacity: 0;
}
</style>
