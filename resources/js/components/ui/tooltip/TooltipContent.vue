<script lang="ts" setup>
import { cn } from "@/lib/utils";
import {
    TooltipContent,
    type TooltipContentEmits,
    type TooltipContentProps,
    TooltipPortal,
    useForwardPropsEmits,
} from "radix-vue";
import { computed, type HTMLAttributes } from "vue";

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<TooltipContentProps & { class?: HTMLAttributes["class"] }>(),
    {
        side: "top",
        sideOffset: 4,
        align: "center",
        alignOffset: 0,
        avoidCollisions: true,
        collisionBoundary: () => [],
        collisionPadding: 0,
        arrowPadding: 0,
        sticky: "partial",
        hideWhenDetached: false,
    },
);

const emits = defineEmits<TooltipContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <TooltipPortal>
        <TooltipContent
            :class="
                cn(
                    'z-50 overflow-hidden rounded-md border border-border/80 bg-popover px-3 py-1.5 text-sm text-popover-foreground shadow-lg animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                    props.class,
                )
            "
            v-bind="{ ...forwarded, ...$attrs }"
        >
            <slot />
        </TooltipContent>
    </TooltipPortal>
</template>
