<script lang="ts" setup>
import {ChevronRight} from "lucide-vue-next";
import {onBeforeUnmount, onMounted, ref} from "vue";

defineOptions({name: "PublicNavigationItem"});
withDefaults(defineProps<{ item: any; lodgeSlug: string; depth?: number }>(), {
    depth: 0,
});
const isOpen = ref(false);
const isHoverSuppressed = ref(false);
const touchPointer = ref(false);
const itemElement = ref<HTMLElement | null>(null);
const markPointerType = (event: PointerEvent) => {
    touchPointer.value = event.pointerType === "touch";
};
const handleNavigation = (event: MouseEvent, item: any) => {
    if (touchPointer.value && item.children?.length && !isOpen.value) {
        event.preventDefault();
        isOpen.value = true;
    }

    touchPointer.value = false;
};
const toggleContainer = (event: MouseEvent) => {
    const isClosing = touchPointer.value
        ? isOpen.value
        : !isHoverSuppressed.value;

    if (touchPointer.value) {
        isHoverSuppressed.value = isOpen.value;
        isOpen.value = !isOpen.value;
    } else {
        isOpen.value = false;
        isHoverSuppressed.value = !isHoverSuppressed.value;
    }

    touchPointer.value = false;

    if (isClosing) {
        (event.currentTarget as HTMLButtonElement).blur();
    }
};
const closeWhenOutside = (event: PointerEvent) => {
    if (!itemElement.value?.contains(event.target as Node)) {
        isOpen.value = false;
        touchPointer.value = false;
    }
};

onMounted(() => document.addEventListener("pointerdown", closeWhenOutside));
onBeforeUnmount(() =>
    document.removeEventListener("pointerdown", closeWhenOutside),
);
</script>

<template>
    <li
        ref="itemElement"
        :class="{
            'is-open': isOpen,
            'is-hover-suppressed': isHoverSuppressed,
        }"
        class="nav-item relative"
        @mouseleave="isHoverSuppressed = false"
    >
        <button
            v-if="item.is_navigation_container"
            :aria-expanded="isOpen"
            :aria-label="`${isOpen ? 'Close' : 'Open'} ${item.title} submenu`"
            class="flex items-center gap-1 rounded px-3 py-2 font-medium hover:bg-black/10 focus-visible:outline-2 focus-visible:outline-offset-2"
            type="button"
            @click="toggleContainer($event)"
            @pointerdown="markPointerType"
        >
            {{ item.title }}
            <ChevronRight
                v-if="item.children?.length"
                aria-hidden="true"
                class="nav-caret size-4 transition-transform"
            />
        </button>
        <a
            v-else
            :href="
                item.is_home
                    ? `/l/${lodgeSlug}`
                    : `/l/${lodgeSlug}/${item.slug}`
            "
            class="flex items-center gap-1 rounded px-3 py-2 font-medium hover:bg-black/10 focus-visible:outline-2 focus-visible:outline-offset-2"
            @click="handleNavigation($event, item)"
            @pointerdown="markPointerType"
        >
            {{ item.title }}
            <ChevronRight
                v-if="item.children?.length"
                aria-hidden="true"
                class="nav-caret size-4 transition-transform"
            />
        </a>
        <ul
            v-if="item.children?.length"
            :class="depth === 0 ? 'left-0 top-full' : 'left-full top-0'"
            class="submenu absolute z-30 hidden min-w-48 rounded-md border bg-background p-1 shadow-lg"
        >
            <PublicNavigationItem
                v-for="child in item.children"
                :key="child.slug"
                :depth="depth + 1"
                :item="child"
                :lodge-slug="lodgeSlug"
            />
        </ul>
    </li>
</template>

<style scoped>
.nav-item:not(.is-hover-suppressed):hover > .submenu,
.nav-item:not(.is-hover-suppressed):focus-within > .submenu {
    display: block;
}

.nav-item.is-open > .submenu {
    display: block;
}

.nav-item:not(.is-hover-suppressed):hover > :is(a, button) .nav-caret,
.nav-item:not(.is-hover-suppressed):focus-within > :is(a, button) .nav-caret,
.nav-item.is-open > :is(a, button) .nav-caret {
    transform: rotate(90deg);
}
</style>
