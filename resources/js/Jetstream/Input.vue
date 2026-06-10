<script setup>
import { onMounted, useTemplateRef } from "vue";

const props = defineProps({
    autofocus: {
        type: Boolean,
        required: false,
        default: false,
    },
    autocomplete: {
        type: String,
        required: false,
        default: "off",
    },
});

const model = defineModel({ type: [String, Number] });

defineEmits(["update:model"]);

const input = useTemplateRef("input");

onMounted(() => {
    if (props.autofocus) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });

// TODO, this file is redundant. We need to remove it
</script>

<template>
<PInputText ref="input" v-model="model" :autocomplete/>
</template>
