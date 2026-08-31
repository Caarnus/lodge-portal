<script lang="ts" setup>
import {DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,} from "@/components/ui/dropdown-menu";
import {Input} from "@/components/ui/input";
import {Check, ChevronDown} from "lucide-vue-next";
import {computed, ref, watch} from "vue";

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        options: Array<{ value: number; label: string }>;
        placeholder?: string;
        filterPlaceholder?: string;
        ariaLabel: string;
        emptyLabel?: string;
    }>(),
    {
        placeholder: "Select an option",
        filterPlaceholder: "Filter options",
        emptyLabel: "No matching options.",
    },
);

const emit = defineEmits<{ "update:modelValue": [value: number | null] }>();

const open = ref(false);
const query = ref("");
const selected = computed(() =>
    props.options.find((option) => option.value === props.modelValue),
);
const matches = computed(() => {
    const filter = query.value.trim().toLowerCase();

    return filter
        ? props.options.filter((option) =>
            option.label.toLowerCase().includes(filter),
        )
        : props.options;
});

const select = (value: number | null) => {
    emit("update:modelValue", value);
    open.value = false;
};

watch(open, (isOpen) => {
    if (isOpen) {
        query.value = "";
    }
});
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger as-child>
            <button
                :aria-label="ariaLabel"
                class="field-input flex items-center justify-between gap-2 text-left"
                type="button"
            >
                <span class="min-w-0 truncate">{{
                        selected?.label ?? placeholder
                    }}</span>
                <ChevronDown class="size-4 shrink-0 text-muted-foreground"/>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="start"
            class="w-[var(--radix-dropdown-menu-trigger-width)] min-w-64 p-0"
            @open-auto-focus.prevent
        >
            <div class="border-b border-border/70 p-2">
                <Input
                    v-model="query"
                    :placeholder="filterPlaceholder"
                    autofocus
                    type="search"
                    @keydown.stop
                />
            </div>
            <div class="max-h-64 overflow-y-auto p-1">
                <DropdownMenuItem
                    :class="!modelValue && 'bg-accent'"
                    @select="select(null)"
                >
                    <Check
                        :class="
                            modelValue === null ? 'opacity-100' : 'opacity-0'
                        "
                        class="size-4"
                    />
                    {{ placeholder }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-for="option in matches"
                    :key="option.value"
                    :class="modelValue === option.value && 'bg-accent'"
                    @select="select(option.value)"
                >
                    <Check
                        :class="
                            modelValue === option.value
                                ? 'opacity-100'
                                : 'opacity-0'
                        "
                        class="size-4"
                    />
                    <span class="min-w-0 truncate">{{ option.label }}</span>
                </DropdownMenuItem>
                <p
                    v-if="matches.length === 0"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ emptyLabel }}
                </p>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
