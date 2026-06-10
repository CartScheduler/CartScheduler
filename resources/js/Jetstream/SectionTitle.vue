<template>
<div class="flex flex-col px-4 sm:px-0 gap-2">
  <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
    <slot name="title"/>
  </h3>

  <p class="text-sm text-neutral-600 dark:text-neutral-300">
    <slot name="description"/>
  </p>
</div>
</template>
