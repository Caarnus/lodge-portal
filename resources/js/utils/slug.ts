export function normalizeSlug(value: string): string {
    return value.replace(/\s+/g, "-");
}
