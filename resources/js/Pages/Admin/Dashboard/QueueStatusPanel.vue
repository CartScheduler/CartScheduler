<script setup lang="ts">
import { Link } from "@inertiajs/vue3";

const { status } = defineProps<{
  status: App.Data.QueueStatusData;
}>();
</script>

<template>
  <Link :href="route('admin.queue.index')"
        class="card grid gap-3 cursor-pointer subtle-zoom transition-[transform,background-color,color] duration-300"
        data-testid="queue-panel">
    <div class="flex items-center gap-2">
      <span class="h-3 w-3 shrink-0 rounded-full"
            :class="status.healthy ? 'bg-green-500' : 'bg-red-500 animate-pulse'"
            :data-healthy="status.healthy" />
      <span class="font-black">Background Queue</span>
    </div>

    <p v-if="!status.healthy" class="text-sm text-red-700 dark:text-red-300">
      The queue is not working. {{ status.summary }}
    </p>
    <p v-else class="text-sm text-gray-600 dark:text-gray-300">Emails are being sent in the background.</p>

    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
      <span><span class="font-semibold tabular-nums">{{ status.waiting + status.delayed }}</span> waiting</span>
      <span v-if="status.processing"><span class="font-semibold tabular-nums">{{ status.processing }}</span> in progress</span>
      <span :class="status.failed ? 'text-red-600 dark:text-red-400 font-semibold' : ''">
        <span class="tabular-nums">{{ status.failed }}</span> failed
      </span>
    </div>
  </Link>
</template>
