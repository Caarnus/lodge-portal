<script lang="ts" setup>
import ExpandableText from "@/components/ExpandableText.vue";
import PageHeader from "@/components/PageHeader.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import {Head, Link, router} from "@inertiajs/vue3";
import {Trash2} from "lucide-vue-next";
import Tooltip from "primevue/tooltip";
import {ref, watch} from "vue";

defineOptions({layout: AppLayout});

const vTooltip = Tooltip;
const props = defineProps<{
    accounts: {
        data: any[];
        links: any[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { search: string };
}>();
const search = ref(props.filters.search);
const deletingId = ref<number | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            "/platform/accounts",
            {search: search.value || undefined},
            {preserveState: true, replace: true},
        );
    }, 450);
});

const remove = (account: any) => {
    if (
        !confirm(
            `Permanently remove the account for ${account.name}? Their person and lodge records will remain.`,
        )
    )
        return;
    deletingId.value = account.id;
    router.delete(`/platform/accounts/${account.id}`, {
        preserveScroll: true,
        onFinish: () => (deletingId.value = null),
    });
};
</script>

<template>
    <Head title="Accounts"/>

    <main class="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
        <PageHeader
            description="Remove account access without deleting a person’s membership or historical lodge records."
            title="Accounts"
        />

        <label class="mt-6 block max-w-xl">
            <span class="text-sm font-medium">Find an account</span>
            <input
                v-model="search"
                class="field-input mt-1"
                placeholder="Search by name or email"
                type="search"
            />
        </label>
        <p class="mt-3 text-sm text-muted-foreground">
            Showing {{ accounts.from ?? 0 }}–{{ accounts.to ?? 0 }} of
            {{ accounts.total }} accounts.
        </p>

        <div class="mt-4 overflow-hidden rounded-lg border border-border/80 bg-card">
            <div
                class="hidden grid-cols-[minmax(12rem,1fr)_minmax(16rem,1.5fr)_8rem_6rem_3rem] gap-4 bg-muted px-4 py-3 text-sm font-semibold text-muted-foreground md:grid"
            >
                <span>Name</span><span>Email</span><span>Status</span
            ><span>Platform</span><span class="sr-only">Actions</span>
            </div>
            <div
                v-for="account in accounts.data"
                :key="account.id"
                class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 border-t p-4 first:border-t-0 md:grid-cols-[minmax(12rem,1fr)_minmax(16rem,1.5fr)_8rem_6rem_3rem] md:items-center md:gap-4"
            >
                <div class="min-w-0">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground md:hidden"
                    >Name</span
                    >
                    <ExpandableText
                        :text="account.name"
                        class="font-medium"
                        label="account name"
                    />
                </div>
                <div class="min-w-0">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground md:hidden"
                    >Email</span
                    >
                    <ExpandableText
                        :text="account.email"
                        class="text-sm text-muted-foreground"
                        label="email address"
                    />
                </div>
                <div>
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground md:hidden"
                    >Status</span
                    ><span class="capitalize">{{
                        account.approval_status
                    }}</span>
                </div>
                <div>
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground md:hidden"
                    >Platform</span
                    ><span>{{
                        account.is_platform_admin ? "Admin" : "—"
                    }}</span>
                </div>
                <div class="row-start-1 flex justify-end md:col-start-5">
                    <button
                        v-tooltip.left="{
                            value: 'Remove account',
                            showDelay: 2000,
                        }"
                        :aria-label="`Remove ${account.name}`"
                        :disabled="deletingId === account.id"
                        class="icon-button text-destructive hover:bg-destructive/10 disabled:cursor-wait"
                        type="button"
                        @click="remove(account)"
                    >
                        <Trash2 class="size-4"/>
                    </button>
                </div>
            </div>
            <p
                v-if="!accounts.data.length"
                class="p-8 text-center text-sm text-muted-foreground"
            >
                No accounts match this search.
            </p>
        </div>

        <nav
            v-if="accounts.links.length > 3"
            aria-label="Account pages"
            class="mt-4 flex flex-wrap gap-2"
        >
            <Link
                v-for="link in accounts.links"
                :key="link.label"
                :class="{
                    'bg-primary text-primary-foreground': link.active,
                    'pointer-events-none opacity-40': !link.url,
                }"
                :href="link.url || '#'"
                class="secondary-button text-sm"
                preserve-scroll
                preserve-state
            ><span v-html="link.label"
            /></Link>
        </nav>
    </main>
</template>
