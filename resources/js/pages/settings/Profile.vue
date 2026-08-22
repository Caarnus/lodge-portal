<script setup lang="ts">
import { TransitionRoot } from "@headlessui/vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

import DeleteUser from "@/components/DeleteUser.vue";
import HeadingSmall from "@/components/HeadingSmall.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AppLayout from "@/layouts/AppLayout.vue";
import SettingsLayout from "@/layouts/settings/Layout.vue";
import { type AuthenticatedSharedData, type BreadcrumbItem } from "@/types";

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    profile: {
        preferred_name: string | null;
        email: string;
        phone: string | null;
        mailing_address_line_1: string | null;
        mailing_address_line_2: string | null;
        mailing_city: string | null;
        mailing_state: string | null;
        mailing_postal_code: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Profile settings",
        href: "/settings/profile",
    },
];

const page = usePage<AuthenticatedSharedData>();
const user = page.props.auth.user;

const form = useForm({
    preferred_name: props.profile.preferred_name ?? "",
    email: props.profile.email,
    phone: props.profile.phone ?? "",
    mailing_address_line_1: props.profile.mailing_address_line_1 ?? "",
    mailing_address_line_2: props.profile.mailing_address_line_2 ?? "",
    mailing_city: props.profile.mailing_city ?? "",
    mailing_state: props.profile.mailing_state ?? "",
    mailing_postal_code: props.profile.mailing_postal_code ?? "",
});

const submit = () => {
    form.patch(route("profile.update"), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Profile information"
                    description="Update your preferred name and contact information. These changes update your shared member profile."
                />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="preferred_name">Preferred name</Label>
                        <Input
                            id="preferred_name"
                            class="mt-1 block w-full"
                            v-model="form.preferred_name"
                            autocomplete="given-name"
                            placeholder="Preferred first name"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.preferred_name"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            class="mt-1 block w-full"
                            v-model="form.phone"
                            autocomplete="tel"
                            placeholder="Phone number"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div class="space-y-4">
                        <div>
                            <Label for="mailing_address_line_1"
                                >Mailing address</Label
                            >
                            <p class="mt-1 text-sm text-muted-foreground">
                                Address changes apply to your shared member
                                profile.
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Input
                                id="mailing_address_line_1"
                                v-model="form.mailing_address_line_1"
                                autocomplete="address-line1"
                                placeholder="Address line 1"
                            />
                            <InputError
                                :message="form.errors.mailing_address_line_1"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Input
                                id="mailing_address_line_2"
                                v-model="form.mailing_address_line_2"
                                autocomplete="address-line2"
                                placeholder="Address line 2"
                            />
                            <InputError
                                :message="form.errors.mailing_address_line_2"
                            />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-[1fr_5rem_8rem]">
                            <div class="grid gap-2">
                                <Label for="mailing_city">City</Label>
                                <Input
                                    id="mailing_city"
                                    v-model="form.mailing_city"
                                    autocomplete="address-level2"
                                />
                                <InputError
                                    :message="form.errors.mailing_city"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="mailing_state">State</Label>
                                <Input
                                    id="mailing_state"
                                    v-model="form.mailing_state"
                                    autocomplete="address-level1"
                                    maxlength="2"
                                />
                                <InputError
                                    :message="form.errors.mailing_state"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="mailing_postal_code">ZIP code</Label>
                                <Input
                                    id="mailing_postal_code"
                                    v-model="form.mailing_postal_code"
                                    autocomplete="postal-code"
                                />
                                <InputError
                                    :message="form.errors.mailing_postal_code"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="mt-2 text-sm text-neutral-800">
                            Your email address is unverified.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="focus:outline-hidden rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email
                            address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">Saved.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
