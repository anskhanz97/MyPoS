export type OfflineState = "queued" | "submitting" | "accepted" | "conflict";

export type OfflineSale = {
    commandId: string;
    clientTransactionId: string;
    committedAtLocal: string;
    snapshotVersion: string;
    fencingEpoch: number;
    total: string;
    currency: "PKR";
    state: OfflineState;
    receipt: {
        number: string;
        lines: Array<{ name: string; quantity: string; gross: string }>;
    };
    conflict?: { code: string; message: string };
};

export type OfflineOutboxCommand = {
    commandId: string;
    commandType: "finalize_cash_sale" | "cash_movement";
    payloadHash: string;
    payload: Record<string, unknown>;
    state: OfflineState;
    attemptCount: number;
    nextAttemptAt: string;
};

export type OfflineLease = {
    issuedAt: string;
    expiresAt: string;
    lastTrustedServerTime: string;
};
