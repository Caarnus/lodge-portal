<script setup lang="ts">
import ExpandableText from '@/components/ExpandableText.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import Tooltip from 'primevue/tooltip';
import { ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const vTooltip = Tooltip;
const props = defineProps<{
    accounts: { data: any[]; links: any[]; from: number | null; to: number | null; total: number };
    filters: { search: string };
}>();
const search = ref(props.filters.search);
const deletingId = ref<number | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/platform/accounts', { search: search.value || undefined }, { preserveState: true, replace: true });
    }, 450);
});

const remove = (account: any) => {
    if (!confirm(`Permanently remove the account for ${account.name}? Their person and lodge records will remain.`)) return;
    deletingId.value = account.id;
    router.delete(`/platform/accounts/${account.id}`, {
        preserveScroll: true,
        onFinish: () => (deletingId.value = null),
    });
};
</script>

<template>
    <Head title="Accounts" />

    <main class="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">Accounts</h1>
            <p class="mt-1 text-sm text-slate-600">Remove account access without deleting a person’s membership or historical lodge records.</p>
        </div>

        <label class="mt-6 block max-w-xl">
            <span class="text-sm font-medium">Find an account</span>
            <input v-model="search" type="search" class="field-input mt-1" placeholder="Search by name or email" />
        </label>
        <p class="mt-3 text-sm text-slate-500">Showing {{ accounts.from ?? 0 }}–{{ accounts.to ?? 0 }} of {{ accounts.total }} accounts.</p>

        <div class="mt-4 overflow-hidden rounded-lg border">
            <div class="hidden grid-cols-[minmax(12rem,1fr)_minmax(16rem,1.5fr)_8rem_6rem_3rem] gap-4 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 md:grid">
                <span>Name</span><span>Email</span><span>Status</span><span>Platform</span><span class="sr-only">Actions</span>
            </div>
            <div v-for="account in accounts.data" :key="account.id" class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 border-t p-4 first:border-t-0 md:grid-cols-[minmax(12rem,1fr)_minmax(16rem,1.5fr)_8rem_6rem_3rem] md:items-center md:gap-4">
                <div class="min-w-0"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 md:hidden">Name</span><ExpandableText :text="account.name" label="account name" class="font-medium" /></div>
                <div class="min-w-0"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 md:hidden">Email</span><ExpandableText :text="account.email" label="email address" class="text-sm text-slate-600" /></div>
                <div><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 md:hidden">Status</span><span class="capitalize">{{ account.approval_status }}</span></div>
                <div><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 md:hidden">Platform</span><span>{{ account.is_platform_admin ? 'Admin' : '—' }}</span></div>
                <div class="row-start-1 flex justify-end md:col-start-5"><button type="button" :disabled="deletingId === account.id" :aria-label="`Remove ${account.name}`" class="icon-button text-red-700 hover:bg-red-50 disabled:cursor-wait" v-tooltip.left="{ value: 'Remove account', showDelay: 2000 }" @click="remove(account)"><Trash2 class="size-4" /></button></div>
            </div>
            <p v-if="!accounts.data.length" class="p-8 text-center text-sm text-slate-500">No accounts match this search.</p>
        </div>

        <nav v-if="accounts.links.length > 3" class="mt-4 flex flex-wrap gap-2" aria-label="Account pages"><Link v-for="link in accounts.links" :key="link.label" :href="link.url || '#'" preserve-state preserve-scroll class="rounded border px-3 py-2 text-sm" :class="{ 'bg-slate-900 text-white': link.active, 'pointer-events-none opacity-40': !link.url }"><span v-html="link.label" /></Link></nav>
    </main>
</template>
