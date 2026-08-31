<script setup lang="ts">
import MediaLibraryModal from "@/components/media/MediaLibraryModal.vue";
import SectionFields from "@/components/website/SectionFields.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import { normalizeSlug } from "@/utils/slug";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import {
    ArrowDown,
    ArrowUp,
    Eye,
    ImagePlus,
    Plus,
    Rocket,
    Save,
    Trash2,
} from "lucide-vue-next";
import Tooltip from "primevue/tooltip";
import { ref, watch } from "vue";

const vTooltip = Tooltip;
defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: any;
    websitePage: any;
    draft: any;
    parentPages: any[];
    media: any[];
    galleries: any[];
    sectionTypes: Record<string, string>;
    canPublish: boolean;
}>();
const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
const metadata = useForm({
    title: props.draft.title,
    slug: props.draft.is_navigation_container ? "" : props.draft.slug,
    is_home: props.draft.is_home,
    show_in_navigation: props.draft.show_in_navigation,
    is_navigation_container: props.draft.is_navigation_container ?? false,
    navigation_visibility: props.draft.navigation_visibility ?? "public",
    navigation_order: props.draft.navigation_order,
    navigation_parent_page_id: props.draft.navigation_parent_page_id,
});
const sections = ref<any[]>(clone(props.draft.sections));
watch(
    () => props.draft.sections,
    (value) => (sections.value = clone(value)),
);
watch(
    () => [metadata.is_home, metadata.show_in_navigation],
    ([isHome, showInNavigation]) => {
        if (isHome || !showInNavigation) {
            metadata.is_navigation_container = false;
        }
    },
);
watch(
    () => metadata.is_navigation_container,
    (isContainer) => {
        if (isContainer) {
            metadata.slug = "";
        }
    },
);
const newSection = ref("rich_text");
const sectionForm = useForm({ type: newSection.value });
const mediaOpen = ref(false);
const parentTitle = (page: any) =>
    page.draft?.title ?? page.published?.title ?? `Page ${page.id}`;
const saveMetadata = () =>
    metadata.put(
        `/lodges/${props.lodge.id}/website/pages/${props.websitePage.id}`,
    );
const addSection = () => {
    sectionForm.type = newSection.value;
    sectionForm.post(
        `/lodges/${props.lodge.id}/website/pages/${props.websitePage.id}/sections`,
        { preserveScroll: true },
    );
};
const saveSection = (section: any) =>
    router.put(
        `/lodges/${props.lodge.id}/website/pages/${props.websitePage.id}/sections/${section.id}`,
        { configuration: section.configuration },
        { preserveScroll: true },
    );
const move = (section: any, direction: string) =>
    router.patch(
        `/lodges/${props.lodge.id}/website/pages/${props.websitePage.id}/sections/${section.id}/move`,
        { direction },
        { preserveScroll: true },
    );
const remove = (section: any) =>
    router.delete(
        `/lodges/${props.lodge.id}/website/pages/${props.websitePage.id}/sections/${section.id}`,
        { preserveScroll: true },
    );
const publish = () =>
    router.post(
        `/lodges/${props.lodge.id}/website/pages/${props.websitePage.id}/publish`,
    );
</script>

<template>
    <Head :title="`Edit ${draft.title}`" />
    <main class="mx-auto w-full max-w-6xl space-y-8 p-4 sm:p-6 lg:p-8">
        <PageHeader :title="draft.title" description="Website page editor">
            <template #eyebrow
                ><Link
                    :href="`/lodges/${lodge.id}/website`"
                    class="hover:underline"
                    >Website</Link
                ></template
            >
            <template #actions
                ><div class="flex gap-2">
                    <button class="secondary-button" @click="mediaOpen = true">
                        <ImagePlus class="mr-1 size-4" /> Media library
                    </button>
                    <a
                        :href="`/lodges/${lodge.id}/website/pages/${websitePage.id}/preview`"
                        target="_blank"
                        class="icon-button"
                        aria-label="Preview draft"
                        v-tooltip.top="{
                            value: 'Preview draft',
                            showDelay: 2000,
                        }"
                        ><Eye class="size-5" /></a
                    ><button
                        v-if="canPublish"
                        class="primary-button inline-flex items-center gap-2"
                        @click="publish"
                    >
                        <Rocket class="size-4" /> Publish
                    </button>
                </div></template
            >
        </PageHeader>
        <WorkspaceTabs :lodge="lodge" workspace="content" active="website" />

        <section class="rounded-lg border border-border/80 bg-card p-5">
            <h2 class="text-lg font-semibold">Page settings</h2>
            <form
                class="mt-4 grid gap-4 sm:grid-cols-2"
                @submit.prevent="saveMetadata"
            >
                <label class="field-label"
                    >Title<input
                        v-model="metadata.title"
                        required
                        class="field-input" /></label
                ><label class="field-label"
                    >Slug<input
                        v-model="metadata.slug"
                        @input="metadata.slug = normalizeSlug(metadata.slug)"
                        :disabled="metadata.is_navigation_container"
                        :required="!metadata.is_navigation_container"
                        class="field-input" /></label
                ><label class="field-label"
                    >Parent page<select
                        v-model.number="metadata.navigation_parent_page_id"
                        class="field-input"
                    >
                        <option :value="null">None</option>
                        <option
                            v-for="parent in parentPages"
                            :key="parent.id"
                            :value="parent.id"
                        >
                            {{ parentTitle(parent) }}
                        </option>
                    </select></label
                >
                <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                    <div class="grid gap-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="field-toggle w-fit"
                                ><input
                                    v-model="metadata.is_home"
                                    type="checkbox"
                                />
                                Home page</label
                            >
                            <label class="field-toggle w-fit"
                                ><input
                                    v-model="metadata.show_in_navigation"
                                    type="checkbox"
                                />
                                Show in navigation</label
                            >
                            <label
                                class="field-toggle w-fit"
                                :class="{
                                    'opacity-50':
                                        !metadata.show_in_navigation ||
                                        metadata.is_home,
                                }"
                                ><input
                                    v-model="metadata.is_navigation_container"
                                    type="checkbox"
                                    :disabled="
                                        !metadata.show_in_navigation ||
                                        metadata.is_home
                                    "
                                />
                                Nav container</label
                            >
                        </div>
                        <fieldset
                            class="rounded-lg border border-border p-3 transition-opacity"
                            :class="{
                                'bg-muted/40 opacity-50':
                                    !metadata.show_in_navigation,
                            }"
                            :disabled="!metadata.show_in_navigation"
                        >
                            <legend class="px-1 text-sm font-medium">
                                Navigation visibility
                            </legend>
                            <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                                <label class="flex items-center gap-2"
                                    ><input
                                        v-model="metadata.navigation_visibility"
                                        type="radio"
                                        value="public"
                                    />
                                    All visitors</label
                                >
                                <label class="flex items-center gap-2"
                                    ><input
                                        v-model="metadata.navigation_visibility"
                                        type="radio"
                                        value="masons"
                                    />
                                    Masons</label
                                >
                                <label class="flex items-center gap-2"
                                    ><input
                                        v-model="metadata.navigation_visibility"
                                        type="radio"
                                        value="lodge"
                                    />
                                    Lodge members</label
                                >
                            </div>
                        </fieldset>
                    </div>
                    <div class="flex items-end justify-end">
                        <button class="primary-button">
                            <Save class="size-4" /> Save settings
                        </button>
                    </div>
                </div>
                <p
                    v-if="Object.keys(metadata.errors).length"
                    class="text-sm text-destructive sm:col-span-2"
                >
                    {{ Object.values(metadata.errors)[0] }}
                </p>
            </form>
        </section>

        <section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold">Sections</h2>
                    <p class="text-sm text-muted-foreground">
                        Use arrow buttons to set display order.
                    </p>
                </div>
                <div class="flex gap-2">
                    <select
                        v-model="newSection"
                        class="rounded-md border px-3 py-2 text-sm"
                    >
                        <option
                            v-for="(label, type) in sectionTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ label }}
                        </option></select
                    ><button
                        :disabled="sectionForm.processing"
                        class="icon-button border"
                        aria-label="Add section"
                        v-tooltip.top="{
                            value: 'Add section',
                            showDelay: 2000,
                        }"
                        @click="addSection"
                    >
                        <Plus class="size-5" />
                    </button>
                </div>
            </div>
            <p
                v-if="sectionForm.errors.type"
                class="mt-2 text-sm text-destructive"
            >
                {{ sectionForm.errors.type }}
            </p>
            <div class="mt-4 space-y-4">
                <article
                    v-for="(section, index) in sections"
                    :key="section.id"
                    class="rounded-lg border border-border/80 bg-card p-5"
                >
                    <header class="mb-4 flex items-center gap-2">
                        <h3 class="min-w-0 flex-1 font-semibold">
                            {{ sectionTypes[section.type] }}
                        </h3>
                        <button
                            :disabled="index === 0"
                            class="icon-button"
                            aria-label="Move section up"
                            v-tooltip.top="{
                                value: 'Move up',
                                showDelay: 2000,
                            }"
                            @click="move(section, 'up')"
                        >
                            <ArrowUp class="size-4" /></button
                        ><button
                            :disabled="index === sections.length - 1"
                            class="icon-button"
                            aria-label="Move section down"
                            v-tooltip.top="{
                                value: 'Move down',
                                showDelay: 2000,
                            }"
                            @click="move(section, 'down')"
                        >
                            <ArrowDown class="size-4" /></button
                        ><button
                            class="icon-button text-destructive hover:bg-destructive/10"
                            aria-label="Delete section"
                            v-tooltip.top="{
                                value: 'Delete section',
                                showDelay: 2000,
                            }"
                            @click="remove(section)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </header>
                    <SectionFields
                        v-model="section.configuration"
                        :type="section.type"
                        :media="media"
                        :galleries="galleries"
                    />
                    <div class="mt-4 flex justify-end">
                        <button
                            class="primary-button"
                            @click="saveSection(section)"
                        >
                            <Save class="size-4" /> Save section
                        </button>
                    </div>
                </article>
                <p
                    v-if="sections.length === 0"
                    class="rounded-lg border border-dashed border-border/80 p-8 text-center text-sm text-muted-foreground"
                >
                    Add first section.
                </p>
            </div>
        </section>
    </main>
    <MediaLibraryModal
        :open="mediaOpen"
        :lodge="lodge"
        :media="media"
        @update:open="mediaOpen = $event"
    />
</template>
