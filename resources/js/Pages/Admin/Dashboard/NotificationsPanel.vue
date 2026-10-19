<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import FailedBadge from "@/Components/FailedBadge.vue";

const { stats } = defineProps<{
  stats: App.Data.NotificationStatsData;
}>();

const counts = [
  { key: "sent_last_10_minutes", label: "Last 10 mins", period: "10m" },
  { key: "sent_last_hour", label: "Last hour", period: "1h" },
  { key: "sent_last_24_hours", label: "Last 24 hours", period: "24h" },
] as const;
</script>

<template>
  <div class="card grid gap-4" data-testid="notifications-panel">
    <div class="flex items-start justify-between gap-3">
      <div>
        <Link :href="route('admin.notifications.index')" class="font-black hover:underline">Notifications</Link>
        <div class="text-sm text-gray-600 dark:text-gray-300">Emails sent by the system.</div>
      </div>
      <Link v-if="stats.failed_last_7_days > 0"
            :href="route('admin.notifications.index', { status: 'failed', period: '7d', resolution: 'unresolved' })"
            class="hover:underline decoration-red-500"
            :aria-label="`${stats.failed_last_7_days} failed in the last 7 days and still not sent. Show them`"
            data-testid="notifications-failed">
        <FailedBadge :label="`${stats.failed_last_7_days} failed`" />
      </Link>
    </div>

    <div class="grid grid-cols-3 gap-2">
      <Link v-for="count in counts"
            :key="count.key"
            :href="route('admin.notifications.index', { status: 'sent', period: count.period })"
            class="rounded-md bg-sub-panel dark:bg-sub-panel-dark p-2 text-center hover:bg-neutral-200 dark:hover:bg-slate-700 transition-colors">
        <div class="text-2xl font-semibold tabular-nums">{{ stats[count.key] }}</div>
        <div class="text-xs text-gray-600 dark:text-gray-300">{{ count.label }}</div>
      </Link>
    </div>

    <div v-if="stats.queued > 0" class="text-sm text-gray-600 dark:text-gray-300">
      {{ stats.queued }} waiting to send.
    </div>
  </div>
</template>
