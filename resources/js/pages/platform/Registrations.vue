<script setup lang="ts">
import ExpandableText from "@/components/ExpandableText.vue";
import PageHeader from "@/components/PageHeader.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { Check, X } from "lucide-vue-next";
import Tooltip from "primevue/tooltip";
import { ref } from "vue";

const vTooltip = Tooltip;

defineOptions({ layout: AppLayout });

interface Registration {
    id: number;
    name: string;
    email: string;
    home_lodge?: { name: string } | null;
}

defineProps<{ registrations: Registration[] }>();

const processingId = ref<number | null>(null);

const decide = (id: number, decision: "approved" | "rejected") => {
    processingId.value = id;
    router.patch(
        `/registrations/${id}`,
        {
            decision,
            reason:
                decision === "rejected" ? "Rejected by administrator" : null,
        },
        {
            preserveScroll: true,
            onFinish: () => (processingId.value = null),
        },
    );
};
</script>

<template>
    <Head title="Registrations" />

    <main class="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
        <PageHeader title="Pending registrations" />

        <div
            class="mt-6 overflow-hidden rounded-lg border border-border/80 bg-card"
        >
            <div
                v-for="registration in registrations"
                :key="registration.id"
                class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-4 border-t border-border/80 p-4 first:border-t-0"
            >
                <div class="min-w-0 space-y-1">
                    <ExpandableText
                        :text="registration.name"
                        label="registrant name"
                        class="font-semibold"
                    />
                    <ExpandableText
                        :text="registration.email"
                        label="email address"
                        class="text-sm text-muted-foreground"
                    />
                    <ExpandableText
                        :text="registration.home_lodge?.name"
                        label="home lodge"
                        class="text-sm text-muted-foreground"
                    />
                </div>

                <div class="flex shrink-0 gap-2">
                    <button
                        type="button"
                        :aria-label="`Approve ${registration.name}`"
                        :disabled="processingId === registration.id"
                        class="icon-button border-primary bg-primary text-primary-foreground hover:bg-primary/90 disabled:cursor-wait disabled:opacity-50"
                        v-tooltip.top="{
                            value: `Approve ${registration.name}`,
                            showDelay: 2000,
                        }"
                        @click="decide(registration.id, 'approved')"
                    >
                        <Check class="size-5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        :aria-label="`Reject ${registration.name}`"
                        :disabled="processingId === registration.id"
                        class="icon-button border-destructive bg-destructive text-destructive-foreground hover:bg-destructive/90 disabled:cursor-wait disabled:opacity-50"
                        v-tooltip.top="{
                            value: `Reject ${registration.name}`,
                            showDelay: 2000,
                        }"
                        @click="decide(registration.id, 'rejected')"
                    >
                        <X class="size-5" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <p
                v-if="registrations.length === 0"
                class="p-8 text-center text-sm text-muted-foreground"
            >
                There are no pending registrations.
            </p>
        </div>
    </main>
</template>
