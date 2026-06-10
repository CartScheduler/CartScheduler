<template>
  <div id="alert-3" class="flex items-center gap-3 p-4 mb-4 rounded-lg bg-success-light dark:bg-success" role="alert">
    <span class="iconify mdi--information-variant-circle-outline"></span>
    <span class="sr-only">Info</span>
    <div class="text-sm font-medium">
      <slot></slot>
    </div>
  </div>
</template>
