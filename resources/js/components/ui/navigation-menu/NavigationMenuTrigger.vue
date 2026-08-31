<script lang="ts" setup>
import {cn} from "@/lib/utils";
import {ChevronDown} from "lucide-vue-next";
import {NavigationMenuTrigger, type NavigationMenuTriggerProps, useForwardProps,} from "radix-vue";
import {computed, type HTMLAttributes} from "vue";
import {navigationMenuTriggerStyle} from ".";

const props = defineProps<
    NavigationMenuTriggerProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
    const {class: _, ...delegated} = props;

    return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <NavigationMenuTrigger
        :class="cn(navigationMenuTriggerStyle(), 'group', props.class)"
        v-bind="forwardedProps"
    >
        <slot/>
        <ChevronDown
            aria-hidden="true"
            class="relative top-px ml-1 h-3 w-3 transition duration-200 group-data-[state=open]:rotate-180"
        />
    </NavigationMenuTrigger>
</template>
