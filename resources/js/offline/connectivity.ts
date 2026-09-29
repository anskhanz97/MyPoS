export async function serverReachable(signal?: AbortSignal): Promise<boolean> {
    try {
        const response = await fetch("/api/v1/health", {
            cache: "no-store",
            credentials: "same-origin",
            ...(signal ? { signal } : {}),
            headers: { Accept: "application/json" },
        });
        return response.ok;
    } catch {
        return false;
    }
}
