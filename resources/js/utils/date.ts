export type DateDisplayFormat =
    "month_year" | "month_day_year" | "day_month_year";

export function formatLodgeDate(
    value: string | null | undefined,
    format: DateDisplayFormat = "month_year",
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
