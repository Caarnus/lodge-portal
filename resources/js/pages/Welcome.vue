<script setup lang="ts">
import AppearanceTabs from "@/components/AppearanceTabs.vue";
import type { SharedData } from "@/types";
import { Head, Link, usePage } from "@inertiajs/vue3";

defineProps<{
    lodges: Array<{
        name: string;
        slug: string;
        number: string;
        city: string;
        state: string;
        logo_path: string | null;
        seal_path: string | null;
    }>;
}>();

const page = usePage<SharedData>();
</script>

<template>
    <Head title="Lodge Directory" />
    <main class="min-h-dvh bg-muted/30 px-5 py-8 text-foreground sm:py-12">
        <div class="mx-auto max-w-5xl">
            <header class="flex items-center justify-between gap-4">
                <p class="text-lg font-semibold">Lodge Directory</p>
                <div class="flex items-center gap-3">
                    <AppearanceTabs compact />
                    <Link
                        :href="
                            page.props.auth.user
                                ? route('dashboard')
                                : route('login')
                        "
                        class="rounded-md border bg-background px-3 py-2 text-sm font-medium hover:bg-muted"
                    >
                        {{ page.props.auth.user ? "Portal" : "Log in" }}
                    </Link>
                </div>
            </header>

            <section class="py-16 text-center sm:py-24">
                <p
                    class="text-sm font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                >
                    Welcome
                </p>
                <h1 class="mt-4 text-4xl font-bold tracking-tight sm:text-5xl">
                    Find your lodge
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg text-muted-foreground">
                    Choose your lodge to see its events, news, and community.
                </p>
            </section>

            <section
                v-if="lodges.length"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <a
                    v-for="lodge in lodges"
                    :key="lodge.slug"
                    :href="`/l/${lodge.slug}`"
                    class="group flex min-h-40 items-center gap-4 rounded-xl border bg-background p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-foreground/30 hover:shadow-md"
                >
                    <img
                        v-if="lodge.seal_path || lodge.logo_path"
                        :src="`/storage/${lodge.seal_path || lodge.logo_path}`"
                        alt=""
                        class="size-16 shrink-0 object-contain"
                    />
                    <div>
                        <h2 class="font-semibold group-hover:underline">
                            {{ lodge.name
                            }}<template v-if="lodge.number">
                                No. {{ lodge.number }}</template
                            >
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ lodge.city }}, {{ lodge.state }}
                        </p>
                    </div>
                </a>
            </section>
            <p
                v-else
                class="rounded-lg border bg-background p-8 text-center text-muted-foreground"
            >
                No lodges are available yet.
            </p>
        </div>
    </main>
</template>
