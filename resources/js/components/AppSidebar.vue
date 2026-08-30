<script setup lang="ts">
import NavMain from "@/components/NavMain.vue";
import NavUser from "@/components/NavUser.vue";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { type AuthenticatedSharedData, type NavItem } from "@/types";
import { Link, router, usePage } from "@inertiajs/vue3";
import {
    Building2,
    CalendarCog,
    ClipboardCheck,
    ContactRound,
    ExternalLink,
    Mail,
    PanelsTopLeft,
    LayoutGrid,
    BookOpen,
    HandHelping,
    Settings,
    Tags,
    Network,
    UserCog,
    Users,
} from "lucide-vue-next";
import { computed } from "vue";
import AppLogo from "./AppLogo.vue";

const page = usePage<AuthenticatedSharedData>();
const auth = computed(() => page.props.auth);
const activeLodge = computed(
    () =>
        auth.value.lodges.find(
            (lodge) => lodge.id === auth.value.user.current_lodge_id,
        ) ??
        auth.value.lodges[0] ??
        null,
);
const platformNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: "Dashboard", href: "/dashboard", icon: LayoutGrid },
    ];
    items.push({ title: "Ritual", href: "/ritual", icon: BookOpen });
    if (auth.value.user.is_platform_admin) {
        items.push({
            title: "Platform Lodges",
            href: "/platform/lodges",
            icon: Building2,
        });
        items.push({
            title: "Accounts",
            href: "/platform/accounts",
            icon: UserCog,
        });
        items.push({
            title: "Event Categories",
            href: "/platform/event-categories",
            icon: Tags,
        });
        items.push({
            title: "Lodge groups",
            href: "/platform/lodge-groups",
            icon: Network,
        });
        items.push({
            title: "Ritual Reference",
            href: "/platform/ritual-reference",
            icon: BookOpen,
        });
        items.push({
            title: "Merge People",
            href: "/platform/people/merge",
            icon: Users,
        });
    }
    if (auth.value.can_review_registrations)
        items.push({
            title: "Registrations",
            href: "/registrations",
            icon: ClipboardCheck,
        });
    return items;
});
const lodgeNavItems = computed<NavItem[]>(() => {
    const lodge = activeLodge.value;
    if (!lodge) return [];
    const items: NavItem[] = [];
    if (lodge.can_view_lodge_site)
        items.push({
            title: "Lodge Website",
            href: `/l/${lodge.slug}`,
            icon: ExternalLink,
            external: true,
        });
    if (lodge.can_manage_website || lodge.can_manage_galleries || lodge.can_manage_newsletters)
        items.push({
            title: "Content Management",
            href: lodge.can_manage_website
                ? `/lodges/${lodge.id}/website`
                : lodge.can_manage_galleries
                  ? `/lodges/${lodge.id}/galleries/manage`
                  : `/lodges/${lodge.id}/newsletters/manage`,
            icon: PanelsTopLeft,
        });
    if (lodge.can_manage_events)
        items.push({
            title: "Events",
            href: `/lodges/${lodge.id}/events`,
            icon: CalendarCog,
        });
    if (lodge.can_manage_communications)
        items.push({
            title: "Communications",
            href: `/lodges/${lodge.id}/communications/manage`,
            icon: Mail,
        });
    if (lodge.can_view_people || lodge.can_manage_officers || lodge.can_manage_roles)
        items.push({
            title: "People",
            href: lodge.can_view_people
                ? `/lodges/${lodge.id}/people`
                : lodge.can_manage_officers
                  ? `/lodges/${lodge.id}/officers`
                  : `/lodges/${lodge.id}/role-assignments`,
            icon: ContactRound,
        });
    if (lodge.can_search_ritual)
        items.push({
            title: "Ritual Assistance",
            href: `/lodges/${lodge.id}/ritual-assistance`,
            icon: HandHelping,
        });
    if (lodge.can_manage_lodge)
        items.push({
            title: "Lodge settings",
            href: `/lodges/${lodge.id}/settings`,
            icon: Settings,
        });
    return items;
});
const activate = (event: Event) => {
    const lodgeId = Number((event.target as HTMLSelectElement).value);
    if (lodgeId && lodgeId !== auth.value.user.current_lodge_id)
        router.post(`/lodges/${lodgeId}/activate`);
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu class="group-data-[collapsible=icon]:items-center"
                ><SidebarMenuItem class="group-data-[collapsible=icon]:w-8"
                    ><SidebarMenuButton
                        size="lg"
                        as-child
                        class="group-data-[collapsible=icon]:justify-center"
                        ><Link :href="route('dashboard')"
                            ><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem
            ></SidebarMenu>
            <div
                v-if="auth.lodges.length"
                class="px-2 group-data-[collapsible=icon]:hidden"
            >
                <span
                    class="mb-1 block text-xs font-semibold uppercase tracking-wide text-sidebar-foreground/60"
                    >Active lodge</span
                ><select
                    aria-label="Active lodge"
                    :value="activeLodge?.id"
                    class="w-full rounded-md border border-sidebar-border bg-sidebar px-2 py-2 text-sm"
                    @change="activate"
                >
                    <option
                        v-for="lodge in auth.lodges"
                        :key="lodge.id"
                        :value="lodge.id"
                    >
                        {{ lodge.name }}
                    </option>
                </select>
            </div>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Platform" :items="platformNavItems" />
            <NavMain
                v-if="activeLodge && lodgeNavItems.length"
                :label="activeLodge.name"
                :items="lodgeNavItems"
            />
        </SidebarContent>

        <SidebarFooter><NavUser /></SidebarFooter>
    </Sidebar>
    <slot />
</template>
