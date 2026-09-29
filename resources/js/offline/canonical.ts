function ordered(value: unknown): unknown {
    if (Array.isArray(value)) return value.map(ordered);
    if (value !== null && typeof value === "object") {
        return Object.fromEntries(
            Object.entries(value)
                .sort(([left], [right]) => left.localeCompare(right))
                .map(([key, child]) => [key, ordered(child)]),
        );
    }
    return value;
}

export function canonicalJson(value: Record<string, unknown>): string {
    return JSON.stringify(ordered(value));
}

export async function canonicalHash(
    value: Record<string, unknown>,
): Promise<string> {
    const bytes = new TextEncoder().encode(canonicalJson(value));
    const digest = await crypto.subtle.digest("SHA-256", bytes);
    return [...new Uint8Array(digest)]
        .map((byte) => byte.toString(16).padStart(2, "0"))
        .join("");
}
