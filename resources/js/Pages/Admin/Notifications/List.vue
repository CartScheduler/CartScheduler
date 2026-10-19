<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import { watchDebounced } from "@vueuse/core";
import { format, parseISO } from "date-fns";
import PSelect from "primevue/select";
import { reactive, ref } from "vue";
import FailedBadge from "@/Components/FailedBadge.vue";
import JetLabel from "@/Jetstream/Label.vue";
import notificationStatusTone, { statusToneTextClass } from "@/Utils/notificationStatusTone";

type Option = { value: string; label: string };
type Filters = {
  status: string | null;
  type: string | null;
  channel: string | null;
  search: string | null;
  period: string | null;
  from: string | null;
  to: string | null;
  resolution: string | null;
};
type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  from: number | null;
  to: number | null;
  prev_page_url: string | null;
  next_page_url: string | null;
};

const props = defineProps<{
  logs: Paginated<App.Data.NotificationLogData>;
  filters: Filters;
  stats: App.Data.NotificationStatsData;
  options: {
    statuses: Option[];
    types: Option[];
    channels: Option[];
    periods: Option[];
  };
}>();

const filters = reactive<Filters>({ ...props.filters });
const expanded = ref<number | null>(null);

const applyFilters = () => {
  if (filters.status !== "failed") {
    filters.resolution = null;
  }
  const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== null && value !== ""));
  router.get(route("admin.notifications.index"), query, { preserveState: true, preserveScroll: true, replace: true });
};

const setPeriod = (period: string | null) => {
  filters.period = filters.period === period ? null : period;
  filters.from = null;
  filters.to = null;
  applyFilters();
};

const clearFilters = () => {
  Object.assign(filters, { status: null, type: null, channel: null, search: null, period: null, from: null, to: null, resolution: null });
  applyFilters();
};

const hasFilters = () => Object.values(filters).some((value) => value !== null && value !== "");

const showUnresolvedFailures = () => {
  filters.status = "failed";
  filters.resolution = "unresolved";
  setPeriod("7d");
};

const resolutions = [
  { value: null, label: "All failures" },
  { value: "unresolved", label: "Still not sent" },
  { value: "resolved", label: "Resolved (sent later)" },
] as const;

const setResolution = (resolution: string | null) => {
  filters.resolution = resolution;
  applyFilters();
};

watchDebounced(() => filters.search, applyFilters, { debounce: 400 });

const formatDate = (value: string | null | undefined) => (value ? format(parseISO(value), "d MMM yyyy, h:mm a") : "—");

const plural = (count: number, word: string) => `${count} ${word}${count === 1 ? "" : "s"}`;

const resending = ref<number | null>(null);

const resend = (log: App.Data.NotificationLogData) => {
  resending.value = log.id;
  router.post(route("admin.notifications.resend", log.id), {}, {
    preserveScroll: true,
    onFinish: () => (resending.value = null),
  });
};

</script>

<template>
  <PageHeader title="Notifications">
    <div class="flex justify-between">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Notifications</h2>
      <BackButton class="mx-3" :href="route('admin.dashboard')" />
    </div>
  </PageHeader>

  <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 grid gap-4">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <button type="button"
              class="card text-left transition-colors"
              :class="filters.status === 'sent' && filters.period === '10m' ? 'ring-2 ring-indigo-500' : ''"
              @click="filters.status = 'sent'; setPeriod('10m')">
        <div class="text-3xl font-semibold tabular-nums">{{ stats.sent_last_10_minutes }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">Sent in the last 10 mins</div>
      </button>
      <button type="button"
              class="card text-left transition-colors"
              :class="filters.status === 'sent' && filters.period === '1h' ? 'ring-2 ring-indigo-500' : ''"
              @click="filters.status = 'sent'; setPeriod('1h')">
        <div class="text-3xl font-semibold tabular-nums">{{ stats.sent_last_hour }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">Sent in the last hour</div>
      </button>
      <button type="button"
              class="card text-left transition-colors"
              :class="filters.status === 'sent' && filters.period === '24h' ? 'ring-2 ring-indigo-500' : ''"
              @click="filters.status = 'sent'; setPeriod('24h')">
        <div class="text-3xl font-semibold tabular-nums">{{ stats.sent_last_24_hours }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">Sent in the last 24 hours</div>
      </button>
      <button type="button"
              class="card text-left transition-colors"
              :class="filters.status === 'failed' && filters.resolution === 'unresolved' && filters.period === '7d' ? 'ring-2 ring-red-500' : ''"
              @click="showUnresolvedFailures">
        <div class="text-3xl font-semibold tabular-nums"
             :class="stats.failed_last_7_days ? 'text-red-600 dark:text-red-400' : ''">
          {{ stats.failed_last_7_days }}
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-300">Failed and still not sent (last 7 days)</div>
      </button>
    </div>

    <div class="bg-panel dark:bg-panel-dark border std-border sm:rounded-lg p-4 sm:p-6 grid gap-4">
      <div class="flex flex-wrap gap-2" role="group" aria-label="Time period">
        <button v-for="period in options.periods"
                :key="period.value"
                type="button"
                class="rounded-full border px-3 py-1 text-sm transition-colors"
                :class="filters.period === period.value
                  ? 'border-indigo-500 bg-indigo-500 text-white'
                  : 'std-border hover:bg-neutral-100 dark:hover:bg-slate-800'"
                :aria-pressed="filters.period === period.value"
                @click="setPeriod(period.value)">
          {{ period.label }}
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
        <div class="lg:col-span-2">
          <JetLabel for="notification-search" value="Search" />
          <PInputText id="notification-search"
                      v-model="filters.search"
                      type="search"
                      placeholder="Name, email or subject"
                      class="mt-1 w-full" />
        </div>
        <div>
          <JetLabel for="notification-status" value="Status" />
          <PSelect v-model="filters.status"
                   label-id="notification-status"
                   :options="options.statuses"
                   option-label="label"
                   option-value="value"
                   placeholder="Any status"
                   show-clear
                   class="mt-1 w-full"
                   @change="applyFilters" />
        </div>
        <div>
          <JetLabel for="notification-type" value="Type" />
          <PSelect v-model="filters.type"
                   label-id="notification-type"
                   :options="options.types"
                   option-label="label"
                   option-value="value"
                   placeholder="Any type"
                   show-clear
                   class="mt-1 w-full"
                   @change="applyFilters" />
        </div>
        <div>
          <JetLabel for="notification-from" value="From" />
          <input id="notification-from"
                 v-model="filters.from"
                 type="date"
                 class="mt-1 block w-full rounded border std-border bg-text-input dark:bg-text-input-dark p-2 text-sm"
                 @change="filters.period = null; applyFilters()">
        </div>
        <div>
          <JetLabel for="notification-to" value="To" />
          <input id="notification-to"
                 v-model="filters.to"
                 type="date"
                 class="mt-1 block w-full rounded border std-border bg-text-input dark:bg-text-input-dark p-2 text-sm"
                 @change="filters.period = null; applyFilters()">
        </div>
      </div>

      <div v-if="filters.status === 'failed'" class="flex flex-wrap gap-2" role="group" aria-label="Failures">
        <button v-for="resolution in resolutions"
                :key="resolution.label"
                type="button"
                class="rounded-full border px-3 py-1 text-sm transition-colors"
                :class="filters.resolution === resolution.value
                  ? 'border-red-500 bg-red-500 text-white'
                  : 'std-border hover:bg-neutral-100 dark:hover:bg-slate-800'"
                :aria-pressed="filters.resolution === resolution.value"
                @click="setResolution(resolution.value)">
          {{ resolution.label }}
        </button>
      </div>

      <div v-if="options.channels.length > 1" class="max-w-xs">
        <JetLabel for="notification-channel" value="Channel" />
        <PSelect v-model="filters.channel"
                 label-id="notification-channel"
                 :options="options.channels"
                 option-label="label"
                 option-value="value"
                 placeholder="Any channel"
                 show-clear
                 class="mt-1 w-full"
                 @change="applyFilters" />
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600 dark:text-gray-300">
        <span v-if="logs.total">Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}</span>
        <span v-else>No notifications match these filters.</span>
        <button v-if="hasFilters()" type="button" class="font-medium hover:underline" @click="clearFilters">
          Clear filters
        </button>
      </div>

      <ul class="divide-y std-border border-y std-border" data-testid="notification-rows">
        <li v-for="log in logs.data" :key="log.id" class="py-3">
          <button type="button"
                  class="grid w-full grid-cols-[auto_1fr] sm:grid-cols-[6.5rem_1fr_12rem] gap-x-4 gap-y-1 text-left items-center"
                  :aria-expanded="expanded === log.id"
                  @click="expanded = expanded === log.id ? null : log.id">
            <span class="row-span-2 sm:row-span-1">
              <FailedBadge v-if="log.status === 'failed'" :label="log.status_label" />
              <span v-else
                    class="text-sm font-medium"
                    :class="statusToneTextClass[notificationStatusTone(log.status)]"
                    :data-status="log.status">
                {{ log.status_label }}
              </span>
            </span>
            <span class="min-w-0">
              <span class="block font-medium truncate">{{ log.subject || log.type_label }}</span>
              <span class="block text-sm text-gray-600 dark:text-gray-300 truncate">
                {{ log.recipient_name ? `${log.recipient_name} · ` : "" }}{{ log.recipient || "Unknown recipient" }}
              </span>
            </span>
            <span class="text-sm text-gray-600 dark:text-gray-300 sm:text-right col-start-2 sm:col-start-3">
              {{ formatDate(log.sent_at || log.failed_at || log.created_at) }}
            </span>
          </button>

          <p v-if="log.error_summary" class="mt-2 sm:ml-[7.5rem] text-sm text-red-700 dark:text-red-300">
            {{ log.error_summary }}
          </p>

          <div v-if="log.status === 'failed'"
               class="mt-1 sm:ml-[7.5rem] flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-600 dark:text-gray-300"
               data-testid="failed-follow-up">
            <span>Tried {{ plural(Math.max(log.attempts, 1), "time") }}.</span>
            <span v-if="log.later_sent_at" class="text-green-700 dark:text-green-400">
              Sent successfully on {{ formatDate(log.later_sent_at) }}.
            </span>
            <span v-else-if="log.later_in_progress">Another attempt is being sent.</span>
            <span v-else-if="log.later_attempts">
              {{ log.later_attempts === 1 ? "A later attempt also failed." : `${log.later_attempts} later attempts also failed.` }}
            </span>
            <PButton v-if="log.can_resend"
                     label="Send again"
                     icon="iconify mdi--email-fast-outline"
                     size="small"
                     severity="secondary"
                     variant="outlined"
                     :loading="resending === log.id"
                     @click="resend(log)" />
            <span v-else-if="!log.later_sent_at && !log.later_in_progress" class="text-gray-500 dark:text-gray-400">
              This email can't be sent again from here.
            </span>
          </div>

          <dl v-if="expanded === log.id"
              class="mt-3 sm:ml-[7.5rem] grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-md bg-sub-panel dark:bg-sub-panel-dark p-3 text-sm">
            <dt class="text-gray-500 dark:text-gray-400">Type</dt>
            <dd>{{ log.type_label }}</dd>
            <dt class="text-gray-500 dark:text-gray-400">Channel</dt>
            <dd>{{ log.channel === "mail" ? "Email" : log.channel }}</dd>
            <dt class="text-gray-500 dark:text-gray-400">Attempts</dt>
            <dd>{{ log.attempts }}</dd>
            <dt class="text-gray-500 dark:text-gray-400">Queued</dt>
            <dd>{{ formatDate(log.queued_at) }}</dd>
            <dt class="text-gray-500 dark:text-gray-400">Sent</dt>
            <dd>{{ formatDate(log.sent_at) }}</dd>

            <template v-if="log.failed_at">
              <dt class="text-gray-500 dark:text-gray-400">Failed</dt>
              <dd>{{ formatDate(log.failed_at) }}</dd>
            </template>

            <template v-if="log.error">
              <dt class="text-gray-500 dark:text-gray-400">Error</dt>
              <dd class="font-mono text-xs break-words">{{ log.error }}</dd>
            </template>

            <template v-if="log.user_id">
              <dt class="text-gray-500 dark:text-gray-400">User</dt>
              <dd><Link :href="route('admin.users.edit', log.user_id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">Open user</Link></dd>
            </template>
          </dl>
        </li>
      </ul>

      <nav v-if="logs.last_page > 1" class="flex items-center justify-between gap-2" aria-label="Pagination">
        <Link v-if="logs.prev_page_url"
              :href="logs.prev_page_url"
              preserve-scroll
              class="rounded border std-border px-3 py-1 text-sm hover:bg-neutral-100 dark:hover:bg-slate-800">
          Previous
        </Link>
        <span v-else />
        <span class="text-sm text-gray-600 dark:text-gray-300">Page {{ logs.current_page }} of {{ logs.last_page }}</span>
        <Link v-if="logs.next_page_url"
              :href="logs.next_page_url"
              preserve-scroll
              class="rounded border std-border px-3 py-1 text-sm hover:bg-neutral-100 dark:hover:bg-slate-800">
          Next
        </Link>
        <span v-else />
      </nav>
    </div>
  </div>
</template>
