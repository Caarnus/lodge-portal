<script setup lang="ts">
import type { AuthenticatedSharedData } from "@/types";
import TabBar from "@/components/TabBar.vue";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps<{
    lodge: { id: number };
    workspace: "people" | "content" | "settings";
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
            access.can_view_people && {
                key: "people",
                label: "People",
                href: `/lodges/${props.lodge.id}/people`,
            },
            access.can_manage_officers && {
                key: "officers",
                label: "Officers",
                href: `/lodges/${props.lodge.id}/officers`,
            },
            access.can_manage_officers && {
                key: "ritual",
                label: "Ritual management",
                href: `/lodges/${props.lodge.id}/ritual-management`,
            },
            access.can_manage_roles && {
                key: "roles",
                label: "Roles",
                href: `/lodges/${props.lodge.id}/role-assignments`,
            },
        ].filter(Boolean) as { key: string; label: string; href: string }[];
    }

    if (props.workspace === "content") {
        return [
            access.can_manage_website && {
                key: "website",
                label: "Website",
                href: `/lodges/${props.lodge.id}/website`,
            },
            access.can_manage_galleries && {
                key: "galleries",
                label: "Media galleries",
                href: `/lodges/${props.lodge.id}/galleries/manage`,
            },
            access.can_manage_newsletters && {
                key: "newsletters",
                label: "Newsletters",
                href: `/lodges/${props.lodge.id}/newsletters/manage`,
            },
        ].filter(Boolean) as { key: string; label: string; href: string }[];
    }

    return [
        access.can_manage_lodge && {
            key: "lodge",
            label: "Lodge settings",
            href: `/lodges/${props.lodge.id}/settings`,
        },
        access.can_manage_events && {
            key: "event-categories",
            label: "Event categories",
            href: `/lodges/${props.lodge.id}/event-categories`,
        },
        access.can_manage_roles && {
            key: "roles",
            label: "Role definitions",
            href: `/lodges/${props.lodge.id}/roles`,
        },
    ].filter(Boolean) as { key: string; label: string; href: string }[];
});
</script>

<template>
    <TabBar
        v-if="tabs.length > 1"
        :tabs="tabs"
        :active="active"
        :ariaLabel="`${workspace} workspace`"
    />
</template>
