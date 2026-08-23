export type DateDisplayFormat =
    "month_year" | "month_day_year" | "day_month_year";

export function formatLodgeDate(
    value: string | null | undefined,
    format: DateDisplayFormat = "day_month_year",
): string {
    if (!value) return "";

    const date = new Date(`${value.slice(0, 10)}T12:00:00`);
    const options: Intl.DateTimeFormatOptions =
        format === "month_year"
            ? { month: "long", year: "numeric" }
            : format === "month_day_year"
              ? { month: "long", day: "numeric", year: "numeric" }
              : { day: "numeric", month: "long", year: "numeric" };

    return new Intl.DateTimeFormat(undefined, options).format(date);
}

export function formatLocalTimestamp(value: string | null | undefined): string {
    if (!value) return "—";

    const date = new Date(
        /[zZ]|[+-]\d{2}:?\d{2}$/.test(value) ? value : `${value}Z`,
    );
    const parts = new Intl.DateTimeFormat("en-CA", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23",
    }).formatToParts(date);
    const values = Object.fromEntries(
        parts
            .filter((part) => part.type !== "literal")
            .map((part) => [part.type, part.value]),
    );

    return `${values.year}-${values.month}-${values.day} ${values.hour}:${values.minute}`;
}
