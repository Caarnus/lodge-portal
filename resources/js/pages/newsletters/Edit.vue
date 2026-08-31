<script lang="ts" setup>
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import RichTextField from "@/components/website/RichTextField.vue";
import {normalizeSlug} from "@/utils/slug";
import {Head, Link, router, useForm} from "@inertiajs/vue3";

defineOptions({layout: AppLayout});
const props = defineProps<{
    lodge: any;
    issue: any;
    draft: any;
    documents: any[];
    media: any[];
    canPublish: boolean;
    embedded?: boolean;
}>();
const form = useForm({
    title: props.draft.title,
    slug: props.issue.slug,
    publication_date: props.draft.publication_date,
    body_html: props.draft.body_html ?? "",
    cover_media_asset_id: props.draft.cover_media_asset_id,
    newsletter_document_id: props.draft.newsletter_document_id,
});
const upload = useForm<{ file: File | null }>({file: null});
</script>
<template>
    <Head :title="`Edit ${draft.title}`"/>
    <main
        :class="
            embedded
                ? 'w-full min-w-0 space-y-6'
                : 'mx-auto w-full min-w-0 max-w-4xl space-y-6 p-6'
        "
    >
        <PageHeader
            v-if="!embedded"
            :title="draft.title"
            description="Newsletter editor"
        >
            <template #eyebrow>
                <Link :href="`/lodges/${lodge.id}/newsletters/manage`">
                    Newsletters
                </Link>
            </template>
            <template #actions>
                <div class="flex gap-3">
                    <a
                        :href="`/lodges/${lodge.id}/newsletters/manage/${issue.id}/preview`"
                        class="secondary-button"
                        target="_blank"
                    >Preview</a
                    >
                    <button
                        v-if="canPublish"
                        class="primary-button"
                        @click="
                            router.post(
                                `/lodges/${lodge.id}/newsletters/manage/${issue.id}/publish`,
                            )
                        "
                    >
                        Publish
                    </button>
                </div>
            </template>
        </PageHeader>
        <WorkspaceTabs
            v-if="!embedded"
            :lodge="lodge"
            active="newsletters"
            workspace="content"
        />
        <header v-else class="flex justify-between">
            <div>
                <Link
                    v-if="!embedded"
                    :href="`/lodges/${lodge.id}/newsletters/manage`"
                    class="text-sm underline"
                >← Newsletters
                </Link
                >
                <h1 class="text-3xl font-bold">{{ draft.title }}</h1>
            </div>
            <div class="flex gap-3">
                <a
                    :href="`/lodges/${lodge.id}/newsletters/manage/${issue.id}/preview`"
                    class="underline"
                    target="_blank"
                >Preview</a
                >
                <button
                    v-if="canPublish"
                    class="primary-button"
                    @click="
                        router.post(
                            `/lodges/${lodge.id}/newsletters/manage/${issue.id}/publish`,
                        )
                    "
                >
                    Publish
                </button>
            </div>
        </header>
        <section class="rounded-lg border border-border/80 bg-card p-5">
            <form
                class="grid w-full min-w-0 gap-4"
                @submit.prevent="
                    form.put(
                        `/lodges/${lodge.id}/newsletters/manage/${issue.id}`,
                    )
                "
            >
                <label
                >Title<input
                    v-model="form.title"
                    class="field-input"
                    required/></label
                ><label
            >Slug<input
                v-model="form.slug"
                class="field-input"
                required
                @input="form.slug = normalizeSlug(form.slug)"/></label
            ><label
            >Publication date<input
                v-model="form.publication_date"
                class="field-input"
                type="date"/></label
            ><label
            >Cover<select
                v-model.number="form.cover_media_asset_id"
                class="field-input"
            >
                <option :value="null">None</option>
                <option
                    v-for="item in media"
                    :key="item.id"
                    :value="item.id"
                >
                    {{ item.original_name }}
                </option>
            </select></label
            ><label
            >PDF<select
                v-model.number="form.newsletter_document_id"
                class="field-input"
            >
                <option :value="null">None</option>
                <option
                    v-for="item in documents"
                    :key="item.id"
                    :value="item.id"
                >
                    {{ item.original_name }}
                </option>
            </select></label
            >
                <RichTextField v-model="form.body_html" class="min-w-0"/>
                <p
                    v-if="Object.keys(form.errors).length"
                    class="text-destructive"
                >
                    {{ Object.values(form.errors)[0] }}
                </p>
                <button class="primary-button w-fit">Save draft</button>
            </form>
        </section>
        <section class="rounded-lg border border-border/80 bg-card p-5">
            <h2 class="font-semibold">Upload PDF</h2>
            <form
                class="mt-3 flex gap-3"
                @submit.prevent="
                    upload.post(
                        `/lodges/${lodge.id}/newsletters/manage/documents`,
                        { forceFormData: true },
                    )
                "
            >
                <input
                    accept="application/pdf,.pdf"
                    class="file-input"
                    required
                    type="file"
                    @change="
                        upload.file =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                />
                <button class="secondary-button">Upload</button>
            </form>
        </section>
    </main>
</template>
