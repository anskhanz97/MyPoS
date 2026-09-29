export type DisplayLine = {
    name: string;
    quantity: string;
    unit: string;
    gross: string;
    discount: string;
    extras: string[];
};

export type DisplayProjection = {
    schemaVersion: 1;
    registerId: string;
    sequence: number;
    state: "idle" | "cart" | "suspended" | "cancelled" | "completed";
    message: string;
    lines: DisplayLine[];
    totals: null | {
        subtotal: string;
        discount: string;
        tax: string;
        gross: string;
    };
};

export type DisplayLocation = { channelId: string; registerId: string };

const prefix = "mypos.customer-display.v1";

export function channelName(channelId: string): string {
    return `${prefix}:${channelId}`;
}

export function storageKey(channelId: string): string {
    return `${channelName(channelId)}:projection`;
}

export function displayUrl(location: DisplayLocation): string {
    const hash = new URLSearchParams({
        channel: location.channelId,
        register: location.registerId,
    });
    return `/?display=1#${hash.toString()}`;
}

export function parseDisplayLocation(url: URL): DisplayLocation | null {
    if (url.searchParams.get("display") !== "1") return null;
    const hash = new URLSearchParams(url.hash.slice(1));
    const channelId = hash.get("channel");
    const registerId = hash.get("register");
    if (!channelId || !registerId) return null;
    return { channelId, registerId };
}

export function writeProjection(
    storage: Pick<Storage, "setItem">,
    location: DisplayLocation,
    projection: DisplayProjection,
): void {
    if (projection.registerId !== location.registerId)
        throw new Error("Display projection belongs to another register.");
    storage.setItem(storageKey(location.channelId), JSON.stringify(projection));
}

export function readProjection(
    storage: Pick<Storage, "getItem">,
    location: DisplayLocation,
): DisplayProjection | null {
    const raw = storage.getItem(storageKey(location.channelId));
    if (!raw) return null;
    try {
        const value = JSON.parse(raw) as Partial<DisplayProjection>;
        if (
            value.schemaVersion !== 1 ||
            value.registerId !== location.registerId ||
            !Number.isInteger(value.sequence) ||
            !["idle", "cart", "suspended", "cancelled", "completed"].includes(
                value.state ?? "",
            ) ||
            !Array.isArray(value.lines)
        )
            return null;
        return value as DisplayProjection;
    } catch {
        return null;
    }
}
