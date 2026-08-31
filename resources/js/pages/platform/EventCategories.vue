<script lang="ts" setup>
import AppLayout from "@/layouts/AppLayout.vue";
import InputError from "@/components/InputError.vue";
import PageHeader from "@/components/PageHeader.vue";
import {router, useForm} from "@inertiajs/vue3";

defineOptions({layout: AppLayout});
const props = defineProps<{
    categories: Array<{
        id: number;
        key: string;
        name: string;
        description: string | null;
        sort_order: number;
        is_active: boolean;
    }>;
}>();
const createForm = useForm({name: "", key: "", description: ""});
const create = () =>
    createForm.post("/platform/event-categories", {
        onSuccess: () => createForm.reset(),
    });
const save = (category: {
    id: number;
    key: string;
    name: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
}) => router.put(`/platform/event-categories/${category.id}`, category);
</script>

<template>
    <main class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6 lg:p-8">
        <PageHeader
            description="Maintain the platform-wide catalog. Lodges choose which active categories they enable."
            title="Event categories"
        />
        <form
            class="grid gap-3 rounded-lg border border-border/80 bg-card p-4 md:grid-cols-[1fr_1fr_2fr_auto]"
            @submit.prevent="create"
        >
            <input
                v-model="createForm.name"
                class="field-input"
                placeholder="Category name"
                required
            /><input
            v-model="createForm.key"
            class="field-input"
            placeholder="Stable key (optional)"
        /><input
            v-model="createForm.description"
            class="field-input"
            placeholder="Description (optional)"
        />
            <button :disabled="createForm.processing" class="primary-button">
                Add category
            </button
            >
            <InputError
                :message="createForm.errors.name || createForm.errors.key"
                class="md:col-span-4"
            />
        </form>
        <div class="space-y-3">
            <form
                v-for="category in props.categories"
                :key="category.id"
                class="grid gap-3 rounded-lg border border-border/80 bg-card p-4 md:grid-cols-[1fr_1fr_2fr_6rem_auto_auto]"
                @submit.prevent="save(category)"
            >
                <input
                    v-model="category.name"
                    class="field-input"
                    required
                /><input
                v-model="category.key"
                class="field-input"
                required
            /><input
                v-model="category.description"
                class="field-input"
                placeholder="Description"
            /><input
                v-model.number="category.sort_order"
                aria-label="Sort order"
                class="field-input"
                min="0"
                type="number"
            /><label class="flex items-center gap-2 text-sm"
            ><input v-model="category.is_active" type="checkbox"/>
                Active</label
            >
                <button class="secondary-button" type="submit">Save</button>
            </form>
        </div>
    </main>
</template>
