<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    lodge: { id: number; name: string };
    categories: Array<{ id: number; key: string; name: string; description: string | null; is_active: boolean; enabled: boolean }>;
}>();

const selected = ref<number[]>(props.categories.filter((category) => category.enabled).map((category) => category.id));
const activeCategories = computed(() => props.categories.filter((category) => category.is_active));

const save = () => router.put(`/lodges/${props.lodge.id}/event-categories`, { category_ids: selected.value });
</script>

<template>
    <main class="mx-auto max-w-3xl space-y-6 p-6">
        <div><h1 class="text-2xl font-semibold">Event categories</h1><p class="text-sm text-muted-foreground">Choose the categories {{ lodge.name }} can use for new events.</p></div>
        <form class="space-y-3" @submit.prevent="save">
            <label v-for="category in activeCategories" :key="category.id" class="flex cursor-pointer items-start gap-3 rounded-lg border p-4">
                <input v-model="selected" :value="category.id" type="checkbox" class="mt-1" />
                <span><span class="block font-medium">{{ category.name }}</span><span v-if="category.description" class="text-sm text-muted-foreground">{{ category.description }}</span></span>
            </label>
            <button type="submit" class="cursor-pointer rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">Save categories</button>
        </form>
    </main>
</template>
