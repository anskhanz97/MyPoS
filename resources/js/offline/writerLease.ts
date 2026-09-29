export class OfflineWriterLease {
    private releaseLock: (() => void) | undefined;
    private channel: BroadcastChannel | undefined;

    constructor(private readonly name: string) {}

    async acquire(): Promise<boolean> {
        if (!navigator.locks) return this.acquireFallback();
        let decided!: (value: boolean) => void;
        const decision = new Promise<boolean>((resolve) => (decided = resolve));
        void navigator.locks.request(
            this.name,
            { ifAvailable: true },
            async (lock) => {
                if (!lock) {
                    decided(false);
                    return;
                }
                this.openChannel();
                decided(true);
                await new Promise<void>(
                    (resolve) => (this.releaseLock = resolve),
                );
            },
        );
        return decision;
    }

    release(): void {
        this.releaseLock?.();
        this.releaseLock = undefined;
        this.channel?.close();
        if (!navigator.locks) localStorage.removeItem(this.name);
    }

    private acquireFallback(): boolean {
        const previous = Number(localStorage.getItem(this.name) ?? "0");
        if (Date.now() - previous < 15_000) return false;
        localStorage.setItem(this.name, String(Date.now()));
        this.openChannel();
        return true;
    }

    private openChannel(): void {
        this.channel =
            typeof BroadcastChannel === "undefined"
                ? undefined
                : new BroadcastChannel(this.name);
        this.channel?.postMessage({
            type: "writer-active",
            at: new Date().toISOString(),
        });
    }
}
