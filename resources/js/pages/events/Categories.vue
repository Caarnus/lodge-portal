<script lang="ts" setup>
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import {router} from "@inertiajs/vue3";
import {computed, ref} from "vue";

defineOptions({layout: AppLayout});

const props = defineProps<{
    lodge: { id: number; name: string };
    categories: Array<{
        id: number;
        key: string;
        name: string;
        description: string | null;
        is_active: boolean;
        enabled: boolean;
    }>;
}>();

const selected = ref<number[]>(
    props.categories
        .filter((category) => category.enabled)
        .map((category) => category.id),
);
const activeCategories = computed(() =>
    props.categories.filter((category) => category.is_active),
);

const save = () =>
    router.put(`/lodges/${props.lodge.id}/event-categories`, {
        category_ids: selected.value,
    });
</script>

<template>
    <main class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
        <PageHeader
            :description="`Choose the categories ${lodge.name} can use for new events.`"
            title="Event categories"
        />
        <WorkspaceTabs
            :lodge="lodge"
            active="event-categories"
            workspace="settings"
        />
        <form
            class="space-y-3 rounded-lg border border-border/80 bg-card p-4"
            @submit.prevent="save"
        >
            <label
                v-for="category in activeCategories"
                :key="category.id"
                class="flex cursor-pointer items-start gap-3 rounded-lg border border-border/80 p-4"
            >
                <input
                    v-model="selected"
                    :value="category.id"
                    class="mt-1"
                    type="checkbox"
                />
                <span
                ><span class="block font-medium">{{ category.name }}</span
                ><span
                    v-if="category.description"
                    class="text-sm text-muted-foreground"
                >{{ category.description }}</span
                ></span
                >
            </label>
            <button class="primary-button" type="submit">
                Save categories
            </button>
        </form>
    </main>
</template>
