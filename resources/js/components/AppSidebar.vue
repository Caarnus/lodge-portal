<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type AuthenticatedSharedData, type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, ClipboardCheck, Globe2, LayoutGrid, Settings, ShieldCheck, Users, UserStar } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<AuthenticatedSharedData>();
const auth = computed(() => page.props.auth);
const activeLodge = computed(() => auth.value.lodges.find((lodge) => lodge.id === auth.value.user.current_lodge_id) ?? auth.value.lodges[0] ?? null);
const platformNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [{ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid }];
    if (auth.value.user.is_platform_admin) {
        items.push({ title: 'Lodges', href: '/platform/lodges', icon: Building2 });
        items.push({ title: 'Merge people', href: '/platform/people/merge', icon: Users });
    }
    if (auth.value.can_review_registrations) items.push({ title: 'Registrations', href: '/registrations', icon: ClipboardCheck });
    return items;
});
const lodgeNavItems = computed<NavItem[]>(() => {
    const lodge = activeLodge.value;
    if (!lodge) return [];
    const items: NavItem[] = [];
    if (lodge.can_manage_lodge) items.push({ title: 'Settings', href: `/lodges/${lodge.id}/settings`, icon: Settings });
    if (lodge.can_manage_website) items.push({ title: 'Website', href: `/lodges/${lodge.id}/website`, icon: Globe2 });
    if (lodge.can_view_people) items.push({ title: 'People', href: `/lodges/${lodge.id}/people`, icon: Users });
    if (lodge.can_manage_officers) items.push({ title: 'Officers', href: `/lodges/${lodge.id}/officers`, icon: UserStar });
    if (lodge.can_manage_roles) items.push({ title: 'Roles', href: `/lodges/${lodge.id}/roles`, icon: ShieldCheck });
    return items;
});
const activate = (event: Event) => {
    const lodgeId = Number((event.target as HTMLSelectElement).value);
    if (lodgeId && lodgeId !== auth.value.user.current_lodge_id) router.post(`/lodges/${lodgeId}/activate`);
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu class="group-data-[collapsible=icon]:items-center"><SidebarMenuItem class="group-data-[collapsible=icon]:w-8"><SidebarMenuButton size="lg" as-child class="group-data-[collapsible=icon]:justify-center"><Link :href="route('dashboard')"><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu>
            <label v-if="auth.lodges.length" class="px-2 group-data-[collapsible=icon]:hidden"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-sidebar-foreground/60">Active lodge</span><select :value="activeLodge?.id" class="w-full rounded-md border border-sidebar-border bg-sidebar px-2 py-2 text-sm" @change="activate"><option v-for="lodge in auth.lodges" :key="lodge.id" :value="lodge.id">{{ lodge.name }}</option></select></label>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Platform" :items="platformNavItems" />
            <NavMain v-if="activeLodge && lodgeNavItems.length" :label="activeLodge.name" :items="lodgeNavItems" />
        </SidebarContent>

        <SidebarFooter><NavUser /></SidebarFooter>
    </Sidebar>
    <slot />
</template>
