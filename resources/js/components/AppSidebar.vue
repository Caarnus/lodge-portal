<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type AuthenticatedSharedData, type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, ClipboardCheck, Globe2, LayoutGrid, Settings } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<AuthenticatedSharedData>();
const auth = computed(() => page.props.auth);
const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [{
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    }];

    if (auth.value.user.is_platform_admin) {
        items.push({ title: 'Platform lodges', href: '/platform/lodges', icon: Building2 });
    }
    if (auth.value.can_review_registrations) {
        items.push({ title: 'Registrations', href: '/registrations', icon: ClipboardCheck });
    }
    for (const lodge of auth.value.lodges) {
        items.push({ title: `${lodge.name} settings`, href: `/lodges/${lodge.id}/settings`, icon: Settings });
        if (lodge.can_manage_website) {
            items.push({ title: `${lodge.name} website`, href: `/lodges/${lodge.id}/website`, icon: Globe2 });
        }
    }

    return items;
});

const activate = (lodgeId: number) => router.post(`/lodges/${lodgeId}/activate`);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <div v-if="auth.lodges.length" class="px-3 py-2 group-data-[collapsible=icon]:hidden">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-sidebar-foreground/60">Active lodge</p>
                <button
                    v-for="lodge in auth.lodges"
                    :key="lodge.id"
                    type="button"
                    class="mb-1 block w-full rounded px-2 py-1.5 text-left text-sm hover:bg-sidebar-accent"
                    :class="{ 'bg-sidebar-accent font-semibold': auth.user.current_lodge_id === lodge.id }"
                    @click="activate(lodge.id)"
                >
                    {{ lodge.name }}
                </button>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
