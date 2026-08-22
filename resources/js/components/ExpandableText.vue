<script setup lang="ts">
import Tooltip from "primevue/tooltip";
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";

const vTooltip = Tooltip;

const props = withDefaults(
    defineProps<{
        text?: string | null;
        label?: string;
    }>(),
    {
        text: "",
        label: "text",
    },
);

const content = ref<HTMLElement | null>(null);
const expanded = ref(false);
const canExpand = ref(false);
let resizeObserver: ResizeObserver | undefined;

const displayText = computed(() => props.text?.trim() || "—");

const measure = async () => {
    if (expanded.value) return;

    await nextTick();
    const element = content.value;
    canExpand.value = element
        ? element.scrollWidth > element.clientWidth + 1
        : false;
};

const toggle = () => {
    if (canExpand.value) expanded.value = !expanded.value;
};

watch(
    () => props.text,
    () => {
        expanded.value = false;
        void measure();
    },
);

watch(expanded, (isExpanded) => {
    if (!isExpanded) void measure();
});

onMounted(() => {
    void measure();
    resizeObserver = new ResizeObserver(() => void measure());
    if (content.value) resizeObserver.observe(content.value);
});

onBeforeUnmount(() => resizeObserver?.disconnect());
</script>

<template>
    <button
        type="button"
        class="block min-w-0 max-w-full text-left"
        :class="canExpand ? 'cursor-pointer' : 'cursor-default'"
        :aria-expanded="canExpand ? expanded : undefined"
        :aria-label="
            canExpand
                ? `${expanded ? 'Collapse' : 'Expand'} ${label}`
                : undefined
        "
        v-tooltip.bottom="
            canExpand
                ? {
                      value: `${expanded ? 'Collapse' : 'Expand'} ${label}`,
                      showDelay: 2000,
                  }
                : undefined
        "
        @click="toggle"
    >
        <span
            ref="content"
            class="block max-w-full"
            :class="expanded ? 'whitespace-normal break-words' : 'truncate'"
            :title="canExpand ? undefined : displayText"
        >
            {{ displayText }}
        </span>
    </button>
</template>
