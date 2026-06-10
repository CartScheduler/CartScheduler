<script setup lang="ts">
import { BarChart } from "echarts/charts";
import { GridComponent, LegendComponent, TitleComponent, TooltipComponent } from "echarts/components";
import { use } from "echarts/core";
import { SVGRenderer } from "echarts/renderers";
import { computed, provide } from "vue";
import VChart, { THEME_KEY } from "vue-echarts";
import { useDarkMode } from "@/Composables/useDarkMode.js";
import EChartsTheme from "@/Utils/eChartsTheme.js";

const props = defineProps<{
  shiftData: App.Data.FilledShiftData[];
}>();

use([SVGRenderer, TitleComponent, TooltipComponent, LegendComponent, BarChart, GridComponent]);

const { isDarkMode } = useDarkMode();

const theme = EChartsTheme(isDarkMode);

provide(THEME_KEY, theme);

const shiftsData = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  tooltip: {
    trigger: "item",
    formatter: "{a} <br/>{b}: {c}",
  },
  xAxis: {
    data: props.shiftData.map((item) => item.date),
    axisLabel: {
      interval: 0,
      rotate: 45,
    },
  },
  yAxis: {},
  series: [
    {
      data: props.shiftData.map((item) => item.shifts_filled),
      type: "bar",
      stack: "x",
      name: "Filled Shifts",
    },
    {
      data: props.shiftData.map((item) => item.shifts_available - item.shifts_filled),
      type: "bar",
      stack: "x",
      name: "Shifts Available",
    },
  ],
}));
</script>

<template>
  <VChart class="min-h-72" :option="shiftsData" />
</template>
