<script setup lang="ts">
import AppearanceTabs from "@/components/AppearanceTabs.vue";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import type { SharedData } from "@/types";
import { Link, usePage } from "@inertiajs/vue3";
import { LayoutDashboard, LogOut, UserRound } from "lucide-vue-next";
import { computed } from "vue";

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const loginHref = computed(
    () => `/login?return_to=${encodeURIComponent(page.url)}`,
);
</script>

<template>
    <template v-if="user">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="outline" size="sm" class="gap-2">
                    <UserRound class="size-4" />
                    <span class="max-w-32 truncate">{{ user.name }}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-64">
                <DropdownMenuLabel>{{ user.email }}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem :as-child="true">
                    <Link :href="route('dashboard')" class="w-full">
                        <LayoutDashboard class="mr-2 size-4" />
                        Access portal
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <div class="px-2 py-2">
                    <p class="mb-2 text-sm font-medium">Theme</p>
                    <AppearanceTabs compact />
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem :as-child="true">
                    <Link
                        method="post"
                        :href="route('logout')"
                        as="button"
                        class="w-full"
                    >
                        <LogOut class="mr-2 size-4" />
                        Log out
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </template>
    <template v-else>
        <a
            :href="loginHref"
            class="rounded-md border border-border/80 px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
            >Log in</a
        >
        <AppearanceTabs compact />
    </template>
</template>
