<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import InputError from "@/components/InputError.vue";
import { router, useForm } from "@inertiajs/vue3";

defineOptions({ layout: AppLayout });
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
const createForm = useForm({ name: "", key: "", description: "" });
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
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">Event categories</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Maintain the platform-wide catalog. Lodges choose which active
                categories they enable.
            </p>
        </div>
        <form
            class="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_1fr_2fr_auto]"
            @submit.prevent="create"
        >
            <input
                v-model="createForm.name"
                placeholder="Category name"
                class="rounded-md border bg-background p-2"
                required
            /><input
                v-model="createForm.key"
                placeholder="Stable key (optional)"
                class="rounded-md border bg-background p-2"
            /><input
                v-model="createForm.description"
                placeholder="Description (optional)"
                class="rounded-md border bg-background p-2"
            /><button
                class="cursor-pointer rounded-md bg-primary px-4 py-2 text-primary-foreground"
                :disabled="createForm.processing"
            >
                Add category</button
            ><InputError
                class="md:col-span-4"
                :message="createForm.errors.name || createForm.errors.key"
            />
        </form>
        <div class="space-y-3">
            <form
                v-for="category in props.categories"
                :key="category.id"
                class="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_1fr_2fr_6rem_auto_auto]"
                @submit.prevent="save(category)"
            >
                <input
                    v-model="category.name"
                    class="rounded-md border bg-background p-2"
                    required
                /><input
                    v-model="category.key"
                    class="rounded-md border bg-background p-2"
                    required
                /><input
                    v-model="category.description"
                    placeholder="Description"
                    class="rounded-md border bg-background p-2"
                /><input
                    v-model.number="category.sort_order"
                    type="number"
                    min="0"
                    aria-label="Sort order"
                    class="rounded-md border bg-background p-2"
                /><label class="flex items-center gap-2 text-sm"
                    ><input v-model="category.is_active" type="checkbox" />
                    Active</label
                ><button
                    type="submit"
                    class="cursor-pointer rounded-md border px-3 py-2 text-sm"
                >
                    Save
                </button>
            </form>
        </div>
    </main>
</template>
