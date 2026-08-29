<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { Plus, Trash2 } from "lucide-vue-next";
import Tooltip from "primevue/tooltip";
import { reactive, ref, watch } from "vue";

defineOptions({ layout: AppLayout });
const vTooltip = Tooltip;
const props = defineProps<{
    lodge: any;
    roles: any[];
    users: {
        data: any[];
        links: any[];
        from: number | null;
        to: number | null;
        total: number;
    };
    assignments: any[];
    filters: { search: string };
}>();
const search = ref(props.filters.search);
const selectedRoles = reactive<Record<number, number | null>>({});
let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(
        () =>
            router.get(
                `/lodges/${props.lodge.id}/role-assignments`,
                { search: search.value || undefined },
                { preserveState: true, replace: true },
            ),
        450,
    );
});
const rolesFor = (userId: number) =>
    props.assignments
        .filter((item) => item.user_id === userId)
        .map((item) => ({
            ...item,
            role: props.roles.find((role) => role.id === item.role_id),
        }));
const assign = (userId: number) => {
    const roleId = selectedRoles[userId];
    if (!roleId) return;
    router.post(
        `/lodges/${props.lodge.id}/role-assignments`,
        { user_id: userId, role_id: roleId },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedRoles[userId] = null;
            },
        },
    );
};
const remove = (userId: number, roleId: number) =>
    confirm("Remove this role assignment?") &&
    router.delete(`/lodges/${props.lodge.id}/role-assignments`, {
        data: { user_id: userId, role_id: roleId },
        preserveScroll: true,
    });
</script>

<template>
    <Head :title="`${lodge.name} role assignments`" />
    <main class="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">Role assignments</h1>
                <p class="text-sm text-slate-600">{{ lodge.name }}</p>
            </div>
            <Link
                :href="`/lodges/${lodge.id}/roles`"
                class="rounded-md border px-4 py-2 text-sm"
                >Edit role definitions</Link
            >
        </div>
        <WorkspaceTabs :lodge="lodge" workspace="people" active="roles" class="mt-6" />
        <label class="mt-6 block max-w-xl"
            ><span class="text-sm font-medium">Find a linked account</span
            ><input
                v-model="search"
                type="search"
                class="mt-1 w-full rounded-md border px-3 py-2"
                placeholder="Search by name or email"
        /></label>
        <p class="mt-3 text-sm text-slate-500">
            Showing {{ users.from ?? 0 }}–{{ users.to ?? 0 }} of
            {{ users.total }} linked accounts.
        </p>
        <div class="mt-4 divide-y rounded-lg border">
            <div
                v-for="user in users.data"
                :key="user.id"
                class="grid gap-3 p-4 lg:grid-cols-[minmax(12rem,1fr)_minmax(16rem,1.5fr)_minmax(14rem,1fr)] lg:items-center"
            >
                <div>
                    <p class="font-medium">{{ user.name }}</p>
                    <p class="break-all text-sm text-slate-600">
                        {{ user.email }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="item in rolesFor(user.id)"
                        :key="item.role_id"
                        class="inline-flex items-center gap-1 rounded-full bg-slate-100 py-1 pl-3 pr-1 text-sm"
                        >{{ item.role?.name
                        }}<button
                            type="button"
                            :aria-label="`Remove ${item.role?.name} from ${user.name}`"
                            class="inline-flex size-7 items-center justify-center rounded-full text-red-700 hover:bg-red-50"
                            v-tooltip.top="{
                                value: 'Remove role',
                                showDelay: 2000,
                            }"
                            @click="remove(user.id, item.role_id)"
                        >
                            <Trash2 class="size-3.5" /></button></span
                    ><span
                        v-if="!rolesFor(user.id).length"
                        class="text-sm text-slate-500"
                        >No lodge role</span
                    >
                </div>
                <div class="flex gap-2">
                    <select
                        v-model="selectedRoles[user.id]"
                        :aria-label="`Role for ${user.name}`"
                        class="min-w-0 flex-1 rounded border p-2"
                    >
                        <option :value="null">Select role</option>
                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.id"
                            :disabled="
                                rolesFor(user.id).some(
                                    (item) => item.role_id === role.id,
                                )
                            "
                        >
                            {{ role.name }}
                        </option></select
                    ><button
                        type="button"
                        :disabled="!selectedRoles[user.id]"
                        :aria-label="`Assign role to ${user.name}`"
                        class="inline-flex size-10 items-center justify-center rounded-md bg-slate-900 text-white disabled:opacity-40"
                        v-tooltip.left="{
                            value: 'Assign role',
                            showDelay: 2000,
                        }"
                        @click="assign(user.id)"
                    >
                        <Plus class="size-4" />
                    </button>
                </div>
            </div>
            <p
                v-if="!users.data.length"
                class="p-8 text-center text-sm text-slate-500"
            >
                No linked accounts match this search.
            </p>
        </div>
        <nav
            v-if="users.links.length > 3"
            class="mt-4 flex flex-wrap gap-2"
            aria-label="Role assignment pages"
        >
            <Link
                v-for="link in users.links"
                :key="link.label"
                :href="link.url || '#'"
                preserve-state
                preserve-scroll
                class="rounded border px-3 py-2 text-sm"
                :class="{
                    'bg-slate-900 text-white': link.active,
                    'pointer-events-none opacity-40': !link.url,
                }"
                ><span v-html="link.label"
            /></Link>
        </nav>
    </main>
</template>
