<script setup lang="ts">
import type { AuthenticatedSharedData } from "@/types";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps<{
    lodge: { id: number };
    workspace: "people" | "content";
    active: string;
}>();

const page = usePage<AuthenticatedSharedData>();
const lodgeAccess = computed(() =>
    page.props.auth.lodges.find((lodge) => lodge.id === props.lodge.id),
);
const tabs = computed(() => {
    const access = lodgeAccess.value;
    if (!access) return [];

    if (props.workspace === "people") {
        return [
            access.can_view_people && { key: "people", label: "People", href: `/lodges/${props.lodge.id}/people` },
            access.can_manage_officers && { key: "officers", label: "Officers", href: `/lodges/${props.lodge.id}/officers` },
            access.can_manage_officers && { key: "ritual", label: "Ritual management", href: `/lodges/${props.lodge.id}/ritual-management` },
            access.can_manage_roles && { key: "roles", label: "Roles", href: `/lodges/${props.lodge.id}/role-assignments` },
        ].filter(Boolean) as { key: string; label: string; href: string }[];
    }

    return [
        access.can_manage_website && { key: "website", label: "Website", href: `/lodges/${props.lodge.id}/website` },
        access.can_manage_galleries && { key: "galleries", label: "Media galleries", href: `/lodges/${props.lodge.id}/galleries/manage` },
        access.can_manage_newsletters && { key: "newsletters", label: "Newsletters", href: `/lodges/${props.lodge.id}/newsletters/manage` },
    ].filter(Boolean) as { key: string; label: string; href: string }[];
});
</script>

<template>
    <nav v-if="tabs.length > 1" class="-mb-px flex gap-1 overflow-x-auto border-b" :aria-label="`${workspace} workspace`">
        <Link v-for="tab in tabs" :key="tab.key" :href="tab.href" class="shrink-0 border-b-2 px-3 py-2 text-sm font-medium" :class="active === tab.key ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'" :aria-current="active === tab.key ? 'page' : undefined">
            {{ tab.label }}
        </Link>
    </nav>
</template>
