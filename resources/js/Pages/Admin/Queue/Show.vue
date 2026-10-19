<script setup lang="ts">
import { Link, router, usePoll } from "@inertiajs/vue3";
import { useClipboard } from "@vueuse/core";
import { format, formatDistanceToNow, parseISO } from "date-fns";
import { computed, ref } from "vue";
import FailedBadge from "@/Components/FailedBadge.vue";

type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
};

const { status, failedJobs } = defineProps<{
  status: App.Data.QueueStatusData;
  failedJobs: Paginated<App.Data.FailedJobData>;
  pendingJobs: App.Data.PendingJobData[];
  pendingLimit: number;
}>();

const busy = ref<string | null>(null);
const confirmingDelete = ref<string | null>(null);

// Waiting jobs move quickly, so keep this page current.
usePoll(30_000, { only: ["status", "pendingJobs", "failedJobs"] });

const retry = (uuid: string) => {
  busy.value = uuid;
  router.post(route("admin.queue.failed.retry", uuid), {}, {
    preserveScroll: true,
    onFinish: () => (busy.value = null),
  });
};

const remove = (uuid: string) => {
  busy.value = uuid;
  router.delete(route("admin.queue.failed.destroy", uuid), {
    preserveScroll: true,
    onFinish: () => {
      busy.value = null;
      confirmingDelete.value = null;
    },
  });
};

const formatDate = (value: string) => format(parseISO(value), "d MMM yyyy, h:mm a");
const ago = (value: string | null | undefined) => (value ? `${formatDistanceToNow(parseISO(value))} ago` : "never");

const stateLabel = { waiting: "Waiting", processing: "In progress", delayed: "Retrying later" } as const;
const stateClass = {
  waiting: "text-amber-700 dark:text-amber-300",
  processing: "text-sky-700 dark:text-sky-300",
  delayed: "text-gray-500 dark:text-gray-400",
} as const;

const showDetails = ref(false);
const { copy, copied } = useClipboard({ legacy: true });

/** Technical details an administrator needs, as plain text so they can be pasted into a message. */
const details = computed(() => [
  `Queue status: ${status.healthy ? "working" : "not working"}`,
  ...status.problems.map((problem) => `- ${problem}`),
  `Connection: ${status.connection}`,
  `Scheduler last ran: ${status.scheduler_last_ran_at ?? "never"}`,
  `Worker last ran: ${status.worker_last_ran_at ?? "never"}`,
  `Jobs: ${status.waiting} waiting, ${status.processing} in progress, ${status.delayed} retrying later, ${status.failed} failed`,
  `Site: ${window.location.origin}`,
  `Checked: ${new Date().toISOString()}`,
].join("\n"));

const backToDashboard = () => router.visit(route("admin.dashboard"));
</script>

<template>
  <PageHeader title="Queue">
    <div class="flex justify-between">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Background Queue</h2>
      <BackButton class="mx-3" @click.prevent="backToDashboard" />
    </div>
  </PageHeader>

  <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 grid gap-4">
    <section class="rounded-lg border-2 p-4 sm:p-6 grid gap-3"
             :class="status.healthy
               ? 'border-green-500/60 bg-green-50 dark:bg-green-950/30'
               : 'border-red-500/60 bg-red-50 dark:bg-red-950/30'"
             data-testid="queue-health">
      <div class="flex items-center gap-3">
        <span class="h-4 w-4 shrink-0 rounded-full" :class="status.healthy ? 'bg-green-500' : 'bg-red-500 animate-pulse'" />
        <h3 class="text-lg font-semibold">
          {{ status.healthy ? "The queue is working." : "The queue is not working." }}
        </h3>
      </div>
      <p v-if="!status.healthy && status.summary" class="text-red-800 dark:text-red-200">{{ status.summary }}</p>
      <div>
        <PButton :label="showDetails ? 'Hide details' : 'More details'"
                 :icon="showDetails ? 'iconify mdi--chevron-up' : 'iconify mdi--chevron-down'"
                 size="small"
                 severity="secondary"
                 variant="text"
                 :aria-expanded="showDetails"
                 @click="showDetails = !showDetails" />
      </div>
      <div v-if="showDetails"
           class="grid gap-2 rounded-md bg-panel dark:bg-panel-dark border std-border p-3 text-sm"
           data-testid="queue-details">
        <p v-if="!status.healthy">Copy these details and send them to your administrator.</p>
        <pre class="whitespace-pre-wrap break-words font-mono text-xs text-gray-700 dark:text-gray-300">{{ details }}</pre>
        <div>
          <PButton :label="copied ? 'Copied' : 'Copy details'"
                   :icon="copied ? 'iconify mdi--check' : 'iconify mdi--content-copy'"
                   size="small"
                   severity="secondary"
                   variant="outlined"
                   @click="copy(details)" />
        </div>
      </div>
    </section>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="card">
        <div class="text-3xl font-semibold tabular-nums">{{ status.waiting }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">Waiting</div>
        <div v-if="status.oldest_waiting_since" class="text-xs text-gray-500 dark:text-gray-400">
          Oldest {{ ago(status.oldest_waiting_since) }}
        </div>
      </div>
      <div class="card">
        <div class="text-3xl font-semibold tabular-nums">{{ status.processing }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">In progress</div>
      </div>
      <div class="card">
        <div class="text-3xl font-semibold tabular-nums">{{ status.delayed }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">Retrying later</div>
      </div>
      <div class="card">
        <div class="text-3xl font-semibold tabular-nums" :class="status.failed ? 'text-red-600 dark:text-red-400' : ''">
          {{ status.failed }}
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-300">Failed</div>
      </div>
    </div>

    <section class="bg-panel dark:bg-panel-dark border std-border sm:rounded-lg p-4 sm:p-6 grid gap-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-lg font-semibold">Failed jobs</h3>
        <PButton v-if="failedJobs.total > 1"
                 label="Retry all"
                 icon="iconify mdi--refresh"
                 size="small"
                 :loading="busy === 'all'"
                 @click="retry('all')" />
      </div>
      <p v-if="!failedJobs.data.length" class="text-sm text-gray-600 dark:text-gray-300">Nothing has failed.</p>
      <ul v-else class="divide-y std-border border-y std-border" data-testid="failed-jobs">
        <li v-for="job in failedJobs.data" :key="job.uuid" class="py-3 grid gap-2">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <FailedBadge />
                <span class="font-medium">{{ job.name }}</span>
              </div>
              <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                <span v-if="job.recipient">To {{ job.recipient }} · </span>Failed {{ formatDate(job.failed_at) }}
              </div>
            </div>
            <div class="flex shrink-0 gap-2">
              <PButton label="Retry"
                       icon="iconify mdi--refresh"
                       size="small"
                       severity="secondary"
                       variant="outlined"
                       :loading="busy === job.uuid"
                       @click="retry(job.uuid)" />
              <PButton v-if="confirmingDelete !== job.uuid"
                       label="Delete"
                       icon="iconify mdi--delete-outline"
                       size="small"
                       severity="danger"
                       variant="text"
                       @click="confirmingDelete = job.uuid" />
              <PButton v-else
                       label="Confirm delete"
                       size="small"
                       severity="danger"
                       :loading="busy === job.uuid"
                       @click="remove(job.uuid)" />
            </div>
          </div>
          <p class="text-sm text-red-700 dark:text-red-300">{{ job.error_summary }}</p>
          <details class="text-xs">
            <summary class="cursor-pointer text-gray-500 dark:text-gray-400">Technical details</summary>
            <p class="mt-1 font-mono break-words">{{ job.error }}</p>
            <Link v-if="job.notification_log_id"
                  :href="route('admin.notifications.index', { status: 'failed', search: job.recipient })"
                  class="mt-1 inline-block text-indigo-600 dark:text-indigo-400 hover:underline">
              View in notifications
            </Link>
          </details>
        </li>
      </ul>
      <nav v-if="failedJobs.last_page > 1" class="flex items-center justify-between gap-2" aria-label="Failed jobs pages">
        <Link v-if="failedJobs.prev_page_url" :href="failedJobs.prev_page_url" preserve-scroll class="text-sm hover:underline">Previous</Link>
        <span v-else />
        <span class="text-sm text-gray-600 dark:text-gray-300">Page {{ failedJobs.current_page }} of {{ failedJobs.last_page }}</span>
        <Link v-if="failedJobs.next_page_url" :href="failedJobs.next_page_url" preserve-scroll class="text-sm hover:underline">Next</Link>
        <span v-else />
      </nav>
    </section>

    <section class="bg-panel dark:bg-panel-dark border std-border sm:rounded-lg p-4 sm:p-6 grid gap-3">
      <h3 class="text-lg font-semibold">Waiting jobs</h3>
      <p v-if="!pendingJobs.length" class="text-sm text-gray-600 dark:text-gray-300">
        {{ status.connection === "database" ? "The queue is empty." : "Waiting jobs can only be listed for the database queue." }}
      </p>
      <ul v-else class="divide-y std-border border-y std-border" data-testid="pending-jobs">
        <li v-for="job in pendingJobs" :key="job.id" class="py-2 flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2 min-w-0">
            <span class="text-sm font-medium" :class="stateClass[job.state]">{{ stateLabel[job.state] }}</span>
            <span class="font-medium truncate">{{ job.name }}</span>
            <span v-if="job.recipient" class="text-sm text-gray-600 dark:text-gray-300 truncate">to {{ job.recipient }}</span>
          </div>
          <div class="text-sm text-gray-600 dark:text-gray-300">
            Queued {{ ago(job.queued_at) }}<span v-if="job.attempts"> · {{ job.attempts }} attempt{{ job.attempts === 1 ? "" : "s" }}</span>
          </div>
        </li>
      </ul>
      <p v-if="pendingJobs.length >= pendingLimit" class="text-xs text-gray-500 dark:text-gray-400">
        Showing the first {{ pendingLimit }} waiting jobs.
      </p>
    </section>
  </div>
</template>
