<script lang="ts" setup>
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import {router, useForm} from "@inertiajs/vue3";

defineOptions({layout: AppLayout});
defineProps<{ categories: any[]; levels: any[]; degrees: any[] }>();
const categoryForm = useForm({
    key: "",
    name: "",
    description: "",
    masonic_degree_id: "",
});
const partForm = useForm({
    key: "",
    ritual_category_id: "",
    name: "",
    description: "",
    counts_toward_program: false,
    point_value: "",
});
const levelForm = useForm({key: "", name: "", point_threshold: ""});
const createCategory = () =>
    categoryForm.post("/platform/ritual-categories", {
        onSuccess: () => categoryForm.reset(),
    });
const createPart = () =>
    partForm.post("/platform/ritual-parts", {
        onSuccess: () => partForm.reset(),
    });
const createLevel = () =>
    levelForm.post("/platform/ritual-levels", {
        onSuccess: () => levelForm.reset(),
    });
const saveCategory = (category: any) =>
    router.put(`/platform/ritual-categories/${category.id}`, category);
const savePart = (part: any) =>
    router.put(`/platform/ritual-parts/${part.id}`, part);
const saveLevel = (level: any) =>
    router.put(`/platform/ritual-levels/${level.id}`, level);
const warning = (item: any) =>
    `Changes that affect points, thresholds, or active state can affect ${item.affected_person_count} credited member record${item.affected_person_count === 1 ? "" : "s"}.`;
</script>

<template>
    <main class="mx-auto w-full max-w-6xl space-y-8 p-4 sm:p-6 lg:p-8">
        <PageHeader
            description="Platform-wide catalog. Stable keys cannot change. Deactivate records instead of deleting them."
            title="Ritual Reference"
        />
        <section class="space-y-4 rounded-lg border border-border/80 bg-card p-4 sm:p-5">
            <div><h2 class="font-semibold">Categories</h2>
                <p class="mt-1 text-sm text-muted-foreground">Maintain the catalog used for ritual crediting.</p></div>
            <form
                class="grid gap-3 rounded-lg border border-border/80 bg-muted/30 p-3 md:grid-cols-4"
                @submit.prevent="createCategory"
            >
                <input
                    v-model="categoryForm.name"
                    class="field-input"
                    placeholder="Category name"
                    required
                /><input
                v-model="categoryForm.key"
                class="field-input"
                placeholder="Stable key (optional)"
            /><select
                v-model="categoryForm.masonic_degree_id"
                class="field-input"
            >
                <option value="">No degree</option>
                <option
                    v-for="degree in degrees"
                    :key="degree.id"
                    :value="degree.id"
                >
                    {{ degree.name }}
                </option>
            </select
            >
                <button
                    class="primary-button"
                >
                    Add category
                </button>
            </form>
            <form
                v-for="category in categories"
                :key="category.id"
                class="rounded-lg border border-border/80 p-3"
                @submit.prevent="saveCategory(category)"
            >
                <div class="grid gap-2 md:grid-cols-5">
                    <input
                        v-model="category.name"
                        class="field-input"
                        required
                    /><input
                    :value="category.key"
                    aria-label="Stable category key"
                    class="field-input bg-muted"
                    disabled
                /><select
                    v-model="category.masonic_degree_id"
                    class="field-input"
                >
                    <option :value="null">No degree</option>
                    <option
                        v-for="degree in degrees"
                        :key="degree.id"
                        :value="degree.id"
                    >
                        {{ degree.name }}
                    </option>
                </select
                ><input
                    v-model.number="category.sort_order"
                    class="field-input"
                    min="0"
                    type="number"
                /><label class="flex items-center gap-2"
                ><input v-model="category.is_active" type="checkbox"/>
                    Active</label
                >
                </div>
                <textarea
                    v-model="category.description"
                    class="field-input mt-3 min-h-20 w-full"
                    placeholder="Description"
                />
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ warning(category) }}
                </p>
                <label class="mt-2 flex items-center gap-2 text-sm"
                ><input v-model="category.confirm_impact" type="checkbox"/>
                    Confirm any impact change</label
                >
                <button class="secondary-button mt-3">
                    Save category
                </button>
            </form>
        </section>
        <section class="space-y-4 rounded-lg border border-border/80 bg-card p-4 sm:p-5">
            <div><h2 class="font-semibold">Parts</h2>
                <p class="mt-1 text-sm text-muted-foreground">Organize the individual ritual components within each
                    category.</p></div>
            <form
                class="grid gap-3 rounded-lg border border-border/80 bg-muted/30 p-3 md:grid-cols-3"
                @submit.prevent="createPart"
            >
                <select
                    v-model="partForm.ritual_category_id"
                    class="field-input"
                    required
                >
                    <option disabled value="">Category</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </select
                ><input
                v-model="partForm.name"
                class="field-input"
                placeholder="Part name"
                required
            /><input
                v-model="partForm.key"
                class="field-input"
                placeholder="Stable key (optional)"
            /><label class="flex items-center gap-2"
            ><input
                v-model="partForm.counts_toward_program"
                type="checkbox"
            />
                Counts toward program</label
            ><input
                v-if="partForm.counts_toward_program"
                v-model.number="partForm.point_value"
                class="field-input"
                min="1"
                placeholder="Points"
                required
                type="number"
            />
                <button
                    class="primary-button"
                >
                    Add part
                </button>
            </form>
            <div
                v-for="category in categories"
                :key="category.id"
                class="space-y-2"
            >
                <h3 class="text-sm font-medium">{{ category.name }}</h3>
                <form
                    v-for="part in category.parts"
                    :key="part.id"
                    class="rounded-lg border border-border/80 p-3"
                    @submit.prevent="savePart(part)"
                >
                    <div class="grid gap-2 md:grid-cols-6">
                        <input
                            v-model="part.name"
                            class="field-input"
                            required
                        /><input
                        :value="part.key"
                        aria-label="Stable part key"
                        class="field-input bg-muted"
                        disabled
                    /><input
                        v-model.number="part.sort_order"
                        class="field-input"
                        min="0"
                        type="number"
                    /><label class="flex items-center gap-2"
                    ><input
                        v-model="part.counts_toward_program"
                        type="checkbox"
                    />
                        Points</label
                    ><input
                        v-if="part.counts_toward_program"
                        v-model.number="part.point_value"
                        class="field-input"
                        min="1"
                        required
                        type="number"
                    /><label class="flex items-center gap-2"
                    ><input v-model="part.is_active" type="checkbox"/>
                        Active</label
                    >
                    </div>
                    <textarea
                        v-model="part.description"
                        class="field-input mt-3 min-h-20 w-full"
                        placeholder="Description"
                    />
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ warning(part) }}
                    </p>
                    <label class="mt-2 flex items-center gap-2 text-sm"
                    ><input v-model="part.confirm_impact" type="checkbox"/>
                        Confirm any impact change</label
                    >
                    <button class="secondary-button mt-3">
                        Save part
                    </button>
                </form>
            </div>
        </section>
        <section class="space-y-4 rounded-lg border border-border/80 bg-card p-4 sm:p-5">
            <div><h2 class="font-semibold">Program levels</h2>
                <p class="mt-1 text-sm text-muted-foreground">Set the point thresholds used to recognize program
                    progress.</p></div>
            <form
                class="grid gap-3 rounded-lg border border-border/80 bg-muted/30 p-3 md:grid-cols-3"
                @submit.prevent="createLevel"
            >
                <input
                    v-model="levelForm.name"
                    class="field-input"
                    placeholder="Level name"
                    required
                /><input
                v-model.number="levelForm.point_threshold"
                class="field-input"
                min="1"
                placeholder="Point threshold"
                required
                type="number"
            />
                <button
                    class="primary-button"
                >
                    Add level
                </button>
            </form>
            <form
                v-for="level in levels"
                :key="level.id"
                class="grid gap-3 rounded-lg border border-border/80 p-3 md:grid-cols-5"
                @submit.prevent="saveLevel(level)"
            >
                <input
                    v-model="level.name"
                    class="field-input"
                    required
                /><input
                :value="level.key"
                aria-label="Stable level key"
                class="field-input bg-muted"
                disabled
            /><input
                v-model.number="level.point_threshold"
                class="field-input"
                min="1"
                required
                type="number"
            /><label class="flex items-center gap-2"
            ><input v-model="level.is_active" type="checkbox"/>
                Active</label
            ><input
                v-model.number="level.sort_order"
                class="field-input"
                min="0"
                type="number"
            />
                <p class="text-xs text-muted-foreground md:col-span-5">
                    {{ warning(level) }}
                </p>
                <label class="flex items-center gap-2 text-sm md:col-span-4"
                ><input v-model="level.confirm_impact" type="checkbox"/>
                    Confirm any impact change</label
                >
                <button class="secondary-button">Save level</button>
            </form>
        </section>
    </main>
</template>
