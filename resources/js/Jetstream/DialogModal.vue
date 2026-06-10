<script setup lang="ts">
import Modal, { Widths } from "@/Jetstream/Modal.vue";

const emit = defineEmits(["close"]);

const { show, maxWidth="2xl", closeable=true } = defineProps<{
  show: boolean;
  maxWidth?: Widths;
  closeable?: boolean;
}>();

const close = () => {
  emit("close");
};
</script>

<template>
  <Modal fill-screen :show="show" :max-width="maxWidth" :closeable="closeable" @close="close">
    <div class="px-6 py-4 overflow-y-auto flex-1 w-full">
      <div class="text-lg ">
        <slot name="title"/>
      </div>

      <div class="mt-4 ">
        <slot name="content"/>
      </div>
    </div>

    <div
        class="flex flex-row justify-end w-full px-6 py-4 bg-gray-100  text-right">
      <slot name="footer"/>
    </div>
  </Modal>
</template>
