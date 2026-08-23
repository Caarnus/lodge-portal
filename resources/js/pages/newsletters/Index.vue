<script setup lang="ts">
import {
    Dialog,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import AppLayout from "@/layouts/AppLayout.vue";
import NewsletterEditor from "@/pages/newsletters/Edit.vue";
import { formatLodgeDate } from "@/utils/date";
import { normalizeSlug } from "@/utils/slug";
import { Head, router, useForm } from "@inertiajs/vue3";
import { Eye, Pencil, Plus, Rocket, Search, Trash2 } from "lucide-vue-next";
import { computed, ref } from "vue";

defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: any;
    issues: any[];
    deletedIssues: any[];
    documents: any[];
    media: any[];
    canPublish: boolean;
}>();
const query = ref("");
const creating = ref(false);
const editing = ref<any | null>(null);
const form = useForm({
    title: "",
    slug: "",
    publication_date: null as string | null,
    body_html: "",
    cover_media_asset_id: null as number | null,
    newsletter_document_id: null as number | null,
});
const issues = computed(() =>
    props.issues.filter((issue) =>
        (issue.draft?.title ?? issue.published?.title ?? "")
            .toLowerCase()
            .includes(query.value.toLowerCase()),
    ),
);
const create = () =>
    form.post(`/lodges/${props.lodge.id}/newsletters/manage`, {
        onSuccess: () => {
            creating.value = false;
            form.reset();
        },
    });
const publish = (issue: any) =>
    router.post(
        `/lodges/${props.lodge.id}/newsletters/manage/${issue.id}/publish`,
    );
const unpublish = (issue: any) =>
    router.post(
        `/lodges/${props.lodge.id}/newsletters/manage/${issue.id}/unpublish`,
    );
const remove = (issue: any) =>
    router.delete(`/lodges/${props.lodge.id}/newsletters/manage/${issue.id}`);
const publicationDate = (value: string | null) =>
    value ? formatLodgeDate(value, props.lodge.date_display_format) : "—";
</script>

<template>
    <Head title="Newsletters" />
    <main class="mx-auto max-w-6xl space-y-6 p-4 md:p-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Newsletters</h1>
                <p class="text-sm text-muted-foreground">
                    Drafts stay private until published.
                </p>
            </div>
            <button class="primary-button" @click="creating = true">
                <Plus class="mr-1 size-4" /> New newsletter
            </button>
        </header>
        <div class="rounded-lg border p-4">
            <label class="relative block"
                ><Search
                    class="absolute left-3 top-3 size-4 text-muted-foreground" /><input
                    v-model="query"
                    type="search"
                    class="field-input pl-9"
                    placeholder="Search newsletters"
            /></label>
        </div>
        <div class="hidden overflow-hidden rounded-lg border md:block">
            <table class="w-full table-fixed text-left text-sm">
                <colgroup>
                    <col />
                    <col class="w-24" />
                    <col class="w-32" />
                    <col class="w-40" />
                </colgroup>
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="p-3">Title</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Publication date</th>
                        <th class="p-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="issue in issues"
                        :key="issue.id"
                        class="border-b last:border-0"
                    >
                        <td class="min-w-0 p-3 font-medium">
                            <span
                                class="block truncate"
                                :title="
                                    issue.draft?.title ?? issue.published?.title
                                "
                            >
                                {{
                                    issue.draft?.title ?? issue.published?.title
                                }}
                            </span>
                        </td>
                        <td class="p-3">
                            {{ issue.draft ? "Draft" : ""
                            }}{{ issue.draft && issue.published ? " + " : ""
                            }}{{ issue.published ? "Published" : "" }}
                        </td>
                        <td class="p-3">
                            {{
                                publicationDate(
                                    issue.draft?.publication_date ??
                                        issue.published?.publication_date ??
                                        null,
                                )
                            }}
                        </td>
                        <td class="px-2 py-3 text-right">
                            <span
                                class="inline-flex min-w-32 items-center justify-end gap-1"
                                ><a
                                    v-if="issue.published"
                                    :href="`/lodges/${lodge.id}/newsletters/${issue.slug}`"
                                    target="_blank"
                                    class="icon-button"
                                    title="View newsletter"
                                    ><Eye class="size-4" /></a
                                ><button
                                    v-else
                                    class="icon-button text-destructive"
                                    title="Delete newsletter"
                                    @click="remove(issue)"
                                >
                                    <Trash2 class="size-4" /></button
                                ><button
                                    class="icon-button"
                                    title="Edit newsletter"
                                    @click="editing = issue"
                                >
                                    <Pencil class="size-4" /></button
                                ><button
                                    v-if="canPublish && issue.draft"
                                    class="icon-button"
                                    title="Publish newsletter"
                                    @click="publish(issue)"
                                >
                                    <Rocket class="size-4" /></button
                                ><button
                                    v-else-if="canPublish && issue.published"
                                    class="icon-button"
                                    title="Unpublish newsletter"
                                    @click="unpublish(issue)"
                                >
                                    <Rocket class="size-4 rotate-180" /></button
                                ><span v-else class="inline-block size-10"
                            /></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="space-y-3 md:hidden">
            <article
                v-for="issue in issues"
                :key="issue.id"
                class="rounded-lg border p-4"
            >
                <h2 class="font-medium">
                    {{ issue.draft?.title ?? issue.published?.title }}
                </h2>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Status</dt>
                        <dd>{{ issue.draft ? "Draft" : "Published" }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Publication</dt>
                        <dd>
                            {{
                                publicationDate(
                                    issue.draft?.publication_date ??
                                        issue.published?.publication_date ??
                                        null,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-4 flex justify-end gap-1">
                    <button
                        class="icon-button"
                        title="Edit newsletter"
                        @click="editing = issue"
                    >
                        <Pencil class="size-4" /></button
                    ><button
                        v-if="canPublish && issue.draft"
                        class="icon-button"
                        title="Publish newsletter"
                        @click="publish(issue)"
                    >
                        <Rocket class="size-4" /></button
                    ><button
                        v-if="!issue.published"
                        class="icon-button text-destructive"
                        title="Delete newsletter"
                        @click="remove(issue)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </article>
        </div>
    </main>
    <Dialog :open="creating" @update:open="creating = $event"
        ><DialogScrollContent class="max-w-xl"
            ><DialogHeader
                ><DialogTitle>New newsletter</DialogTitle></DialogHeader
            >
            <form class="grid gap-4" @submit.prevent="create">
                <label class="field-label"
                    >Title<input
                        v-model="form.title"
                        required
                        class="field-input" /></label
                ><label class="field-label"
                    >Slug<input
                        v-model="form.slug"
                        @input="form.slug = normalizeSlug(form.slug)"
                        required
                        class="field-input"
                /></label>
                <div class="flex justify-end">
                    <button class="primary-button">Create newsletter</button>
                </div>
            </form></DialogScrollContent
        ></Dialog
    >
    <Dialog :open="!!editing" @update:open="!$event && (editing = null)"
        ><DialogScrollContent class="w-[calc(100vw-2rem)] max-w-5xl"
            ><NewsletterEditor
                v-if="editing"
                embedded
                :lodge="lodge"
                :issue="editing"
                :draft="editing.draft ?? editing.published"
                :documents="documents"
                :media="media"
                :can-publish="
                    canPublish && !!editing.draft
                " /></DialogScrollContent
    ></Dialog>
</template>
