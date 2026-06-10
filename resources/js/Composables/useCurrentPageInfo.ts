import { computed, reactive } from "vue";

const currentRoute = reactive<{
  name: string | undefined;
}>({
  name: undefined,
});

export default function() {
  const prepareRouteData = () => {
    currentRoute.name = route().current();
  };

  const isCurrent = (routeName?: string) => computed(() => {
    return currentRoute.name === routeName;
  });

  return {
    routeName: computed(() => currentRoute.name),
    isCurrent,
    prepareRouteData,
  };
}
