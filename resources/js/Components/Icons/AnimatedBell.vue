<script setup lang="ts">
import { computed } from "vue";
import type { StyleValue } from "vue";

const {
  width = "20",
  height = "20",
  speed = "0.3",
  color = "currentColor",
  animate = false,
  active = false,
} = defineProps<{
  width?: string | number;
  height?: string | number;
  speed?: string | number;
  color?: string;
  animate?: boolean;
  active?: boolean;
  style?: Partial<StyleValue>;
}>();

const clapperSpeed = computed(() => parseFloat("" + speed)/1.7 + "s");
</script>

<template>
  <svg :width="width"
       :height="height"
       viewBox="0 0 20 20"
       :style="[
         { overflow: 'initial' },
         style,
       ]"
       v-bind="$attrs">
    <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
      <g id="bell" :fill="color">
        <g id="group-2" v-if="active">
          <animateTransform v-if="animate"
                            attributeName="transform"
                            attributeType="XML"
                            type="rotate"
                            keyTimes="0; 0.5; 1"
                            values="-4 10 2; 4 10 2; -4 10 2;"
                            :dur="`${speed}s`"
                            begin="0s"
                            repeatCount="indefinite" />
          <path d="M17.97,8.5 L19.97,8.5 C19.82,5.3 18.24,2.48 15.85,0.65 L14.43,2.08 C16.45,3.53 17.82,5.85 17.97,8.5 Z"
                id="Path">
          </path>
          <path d="M5.58,2.08 L4.15,0.65 C1.75,2.48 0.17,5.3 0.03,8.5 L2.03,8.5 C2.18,5.85 3.54,3.53 5.58,2.08 L5.58,2.08 Z"
                id="Path">
          </path>
        </g>

        <g id="group-3">
          <animateTransform v-if="animate"
                            attributeName="transform"
                            attributeType="XML"
                            type="rotate"
                            keyTimes="0; 0.5; 1"
                            values="-12 10 4; 12 10 4; -12 10 4;"
                            :dur="`${speed}s`"
                            begin="0s"
                            repeatCount="indefinite" />

          <path d="M10,20 C10.14,20 10.27,19.99 10.4,19.96 C11.05,19.82 11.58,19.38 11.84,18.78 C11.94,18.54 11.99,18.28 11.99,18 L7.99,18 C8,19.1 8.89,20 10,20 Z"
                id="Path">
            <animateTransform v-if="animate"
                              attributeName="transform"
                              attributeType="XML"
                              type="translate"
                              keyTimes="0; 0.5; 1"
                              values="-5 0; 5 0; -5 0;"
                              :dur="clapperSpeed"
                              begin=".1s"
                              repeatCount="indefinite" />

            <!--            :dur="`.${clapperSpeed}s`" -->
          </path>
          <path d="M16,9 C16,5.93 14.36,3.36 11.5,2.68 L11.5,2 C11.5,1.17 10.83,0.5 10,0.5 C9.17,0.5 8.5,1.17 8.5,2 L8.5,2.68 C5.63,3.36 4,5.92 4,9 L4,14 L2,16 L2,17 L18,17 L18,16 L16,14 L16,9 Z"
                id="Shape"
                fill-rule="nonzero"></path>
        </g>
      </g>
    </g>
  </svg>
</template>
