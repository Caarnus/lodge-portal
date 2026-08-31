import {cva, type VariantProps} from "class-variance-authority";

export {default as Badge} from "./Badge.vue";

export const badgeVariants = cva(
    "inline-flex w-fit items-center rounded-full border px-2.5 py-1 text-xs font-medium",
    {
        variants: {
            variant: {
                default: "border-primary/15 bg-primary/10 text-primary",
                secondary:
                    "border-border/60 bg-secondary text-secondary-foreground",
                muted: "border-border/60 bg-muted text-muted-foreground",
                warning:
                    "border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300",
                destructive:
                    "border-destructive/20 bg-destructive/10 text-destructive",
                outline: "border-border bg-card text-foreground",
            },
        },
        defaultVariants: {variant: "default"},
    },
);

export type BadgeVariants = VariantProps<typeof badgeVariants>;
