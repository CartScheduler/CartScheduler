import resolveConfig from "tailwindcss/resolveConfig";
import { computed } from "vue";
import tailwindConfig from "@@/tailwind.config.js";

const twConfig = resolveConfig(tailwindConfig);
const twColors = twConfig.theme.colors;

export default function EChartsTheme(isDark) {

  return computed(() => {
    const colors = {
      text: isDark.value ? twColors.neutral[300] : twColors.neutral[700],
      lines: isDark.value ? twColors.neutral[800] : twColors.neutral[200],
      series1: twColors.yellow[300],
      series2: twColors.cyan[400],
      series3: twColors.orange[300],
      series4: twColors.green[600],
      series6: twColors.red[500],
      series7: twColors.slate[400],
      series8: twColors.fuchsia[300],
      series9: twColors.orange[500],
      series5: twColors.green[400],
      series10: twColors.blue[500],
      series11: twColors.amber[500],
      series12: twColors.purple[300],
    };

    return ({
      "titleColor": colors.text,
      "subtitleColor": colors.text,
      "textColor": colors.text,
      "markTextColor": colors.text,
      "legendTextColor": colors.text,

      "color": [
        colors.series1,
        colors.series2,
        colors.series3,
        colors.series4,
        colors.series6,
        colors.series7,
        colors.series8,
        colors.series9,
        colors.series5,
        colors.series10,
        colors.series11,
        colors.series12,
      ],

      "categoryAxis": {
        "axisLine": { "show": true, "lineStyle": { "color": colors.lines } },
        "axisTick": { "show": true, "lineStyle": { "color": colors.lines } },
        "axisLabel": { "show": true, "color": colors.text },
        "splitLine": { "show": false, "lineStyle": { "color": colors.lines } },
      },
      "valueAxis": {
        "axisLine": { "show": true, "lineStyle": { "color": colors.lines } },
        "axisTick": { "show": true, "lineStyle": { "color": colors.lines } },
        "axisLabel": { "show": true, "color": colors.text },
        "splitLine": { "show": false, "lineStyle": { "color": colors.lines } },
      },
      "logAxis": {
        "axisLine": { "show": true, "lineStyle": { "color": colors.lines } },
        "axisTick": { "show": true, "lineStyle": { "color": colors.lines } },
        "axisLabel": { "show": true, "color": colors.text },
        "splitLine": { "show": false, "lineStyle": { "color": colors.lines } },
      },
      "timeAxis": {
        "axisLine": { "show": true, "lineStyle": { "color": colors.lines } },
        "axisTick": { "show": true, "lineStyle": { "color": colors.lines } },
        "axisLabel": { "show": true, "color": colors.text },
        "splitLine": { "show": false, "lineStyle": { "color": colors.lines } },
      },
      "toolbox": {
        "iconStyle": { "borderColor": colors.text },
        "emphasis": { "iconStyle": { "borderColor": colors.text } },
      },
      "legend": { "textStyle": { "color": colors.text } },
      "tooltip": {
        "axisPointer": {
          "lineStyle": { "color": colors.lines, "width": "1" },
          "crossStyle": { "color": colors.text, "width": "1" },
        },
      },
    });
  });
}
