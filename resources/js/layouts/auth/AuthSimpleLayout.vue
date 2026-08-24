<script setup lang="ts">
import AppBrandLogo from "@/components/AppBrandLogo.vue";
import type { SharedData } from "@/types";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage<SharedData>();
const lodge = computed(() => page.props.auth_lodge);
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="lodge ? `/l/${lodge.slug}` : route('home')"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <template v-if="lodge">
                            <img
                                v-if="lodge.seal_path || lodge.logo_path"
                                :src="`/storage/${lodge.seal_path || lodge.logo_path}`"
                                :alt="lodge.name"
                                class="size-16 object-contain"
                            />
                            <span class="text-center text-lg font-semibold">
                                {{ lodge.name
                                }}<template v-if="lodge.number">
                                    No. {{ lodge.number }}</template
                                >
                            </span>
                        </template>
                        <AppBrandLogo v-else />
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
