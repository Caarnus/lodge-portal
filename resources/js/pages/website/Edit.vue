<script setup lang="ts">
import SectionFields from "@/components/website/SectionFields.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { normalizeSlug } from "@/utils/slug";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import {
    ArrowDown,
    ArrowUp,
    Download,
    Eye,
    ImagePlus,
    Plus,
    RotateCcw,
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
    sectionTypes: Record<string, string>;
    canPublish: boolean;
}>();
const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
const metadata = useForm({
    title: props.draft.title,
    slug: props.draft.slug,
    is_home: props.draft.is_home,
    show_in_navigation: props.draft.show_in_navigation,
    navigation_order: props.draft.navigation_order,
    navigation_parent_page_id: props.draft.navigation_parent_page_id,
});
const sections = ref<any[]>(clone(props.draft.sections));
watch(
    () => props.draft.sections,
    (value) => (sections.value = clone(value)),
);
const newSection = ref("rich_text");
const sectionForm = useForm({ type: newSection.value });
const upload = useForm<{ file: File | null; alt_text: string }>({
    file: null,
    alt_text: "",
});
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
const sendUpload = () =>
    upload.post(`/lodges/${props.lodge.id}/website/media`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => upload.reset(),
    });
const retryMedia = (asset: any) =>
    router.post(
        `/lodges/${props.lodge.id}/website/media/${asset.id}/retry`,
        {},
        { preserveScroll: true },
    );
const deleteMedia = (asset: any) =>
    router.delete(`/lodges/${props.lodge.id}/website/media/${asset.id}`, {
        preserveScroll: true,
    });
</script>

<template>
    <Head :title="`Edit ${draft.title}`" />
    <main class="mx-auto w-full max-w-6xl space-y-8 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <Link
                    :href="`/lodges/${lodge.id}/website`"
                    class="text-sm text-slate-500 hover:underline"
                    >← Website</Link
                >
                <h1 class="text-3xl font-bold">{{ draft.title }}</h1>
            </div>
            <div class="flex gap-2">
                <a
                    :href="`/lodges/${lodge.id}/website/pages/${websitePage.id}/preview`"
                    target="_blank"
                    class="icon-button"
                    aria-label="Preview draft"
                    v-tooltip.top="{ value: 'Preview draft', showDelay: 2000 }"
                    ><Eye class="size-5" /></a
                ><button
                    v-if="canPublish"
                    class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-white"
                    @click="publish"
                >
                    <Rocket class="size-4" /> Publish
                </button>
            </div>
        </header>

        <section class="rounded-lg border p-5">
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
                        required
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
                ><label class="field-label"
                    >Navigation order<input
                        v-model.number="metadata.navigation_order"
                        type="number"
                        min="0"
                        class="field-input" /></label
                ><label class="flex items-center gap-2 text-sm"
                    ><input v-model="metadata.is_home" type="checkbox" /> Home
                    page</label
                ><label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="metadata.show_in_navigation"
                        type="checkbox"
                    />
                    Show in navigation</label
                >
                <p
                    v-if="Object.keys(metadata.errors).length"
                    class="text-sm text-red-600 sm:col-span-2"
                >
                    {{ Object.values(metadata.errors)[0] }}
                </p>
                <button
                    class="inline-flex w-fit items-center gap-2 rounded-md border px-4 py-2"
                >
                    <Save class="size-4" /> Save settings
                </button>
            </form>
        </section>

        <section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold">Sections</h2>
                    <p class="text-sm text-slate-500">
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
            <p v-if="sectionForm.errors.type" class="mt-2 text-sm text-red-600">
                {{ sectionForm.errors.type }}
            </p>
            <div class="mt-4 space-y-4">
                <article
                    v-for="(section, index) in sections"
                    :key="section.id"
                    class="rounded-lg border bg-white p-5"
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
                            class="icon-button text-red-600"
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
                    /><button
                        class="mt-4 inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm text-white"
                        @click="saveSection(section)"
                    >
                        <Save class="size-4" /> Save section
                    </button>
                </article>
                <p
                    v-if="sections.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-slate-500"
                >
                    Add first section.
                </p>
            </div>
        </section>

        <section class="rounded-lg border p-5">
            <h2 class="flex items-center gap-2 text-lg font-semibold">
                <ImagePlus class="size-5" /> Media
            </h2>
            <form
                class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]"
                @submit.prevent="sendUpload"
            >
                <label class="field-label"
                    >Image<input
                        required
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif"
                        class="field-input"
                        @change="
                            upload.file =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] ?? null
                        " /></label
                ><label class="field-label"
                    >Alternative text<input
                        v-model="upload.alt_text"
                        required
                        class="field-input" /></label
                ><button
                    class="self-end rounded-md bg-slate-900 px-4 py-2 text-white"
                >
                    Upload
                </button>
                <p
                    v-if="Object.keys(upload.errors).length"
                    class="text-sm text-red-600 sm:col-span-3"
                >
                    {{ Object.values(upload.errors)[0] }}
                </p>
            </form>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="asset in media"
                    :key="asset.id"
                    class="rounded border p-3"
                >
                    <img
                        v-if="asset.url"
                        :src="asset.url"
                        :alt="asset.alt_text"
                        class="aspect-video w-full rounded object-cover"
                    />
                    <div
                        v-else
                        class="grid aspect-video place-items-center rounded bg-slate-100 text-sm text-slate-500"
                    >
                        {{ asset.processing_status }}
                    </div>
                    <p class="mt-2 truncate text-sm font-medium">
                        {{ asset.original_name }}
                    </p>
                    <p
                        v-if="asset.processing_error"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ asset.processing_error }}
                    </p>
                    <div class="mt-2 flex justify-end gap-1">
                        <a
                            :href="`/lodges/${lodge.id}/website/media/${asset.id}/original`"
                            class="icon-button"
                            aria-label="Download original"
                            v-tooltip.top="{
                                value: 'Download original',
                                showDelay: 2000,
                            }"
                            ><Download class="size-4" /></a
                        ><button
                            v-if="asset.processing_status === 'failed'"
                            class="icon-button"
                            aria-label="Retry processing"
                            v-tooltip.top="{
                                value: 'Retry processing',
                                showDelay: 2000,
                            }"
                            @click="retryMedia(asset)"
                        >
                            <RotateCcw class="size-4" /></button
                        ><button
                            class="icon-button text-red-600"
                            aria-label="Delete media"
                            v-tooltip.top="{
                                value: 'Delete media',
                                showDelay: 2000,
                            }"
                            @click="deleteMedia(asset)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </article>
            </div>
        </section>
    </main>
</template>
