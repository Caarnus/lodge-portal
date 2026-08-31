<script lang="ts" setup>
import {cn} from "@/lib/utils";
import {Primitive, type PrimitiveProps} from "radix-vue";
import type {HTMLAttributes} from "vue";
import {sidebarMenuButtonVariants, type SidebarMenuButtonVariants} from ".";

export interface SidebarMenuButtonProps extends PrimitiveProps {
    variant?: SidebarMenuButtonVariants["variant"];
    size?: SidebarMenuButtonVariants["size"];
    isActive?: boolean;
    class?: HTMLAttributes["class"];
}

const props = withDefaults(defineProps<SidebarMenuButtonProps>(), {
    as: "button",
    variant: "default",
    size: "default",
});
</script>

<template>
    <Primitive
        :as="as"
        :as-child="asChild"
        :class="cn(sidebarMenuButtonVariants({ variant, size }), props.class)"
        :data-active="isActive"
        :data-size="size"
        data-sidebar="menu-button"
        v-bind="$attrs"
    >
        <slot/>
    </Primitive>
</template>
