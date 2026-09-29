import type { OfflineOutboxCommand, OfflineSale } from "./types";

const databaseName = "mypos-offline-v1";
const databaseVersion = 1;
const stores = [
    "meta",
    "lease",
    "catalogue",
    "pricing",
    "register_session",
    "sales",
    "outbox",
    "sync_receipts",
    "diagnostics",
] as const;

function requestResult<T>(request: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () =>
            reject(request.error ?? new Error("IndexedDB request failed."));
    });
}

function completed(transaction: IDBTransaction): Promise<void> {
    return new Promise((resolve, reject) => {
        transaction.oncomplete = () => resolve();
        transaction.onabort = () =>
            reject(
                transaction.error ??
                    new Error("Offline transaction was aborted."),
            );
        transaction.onerror = () =>
            reject(
                transaction.error ?? new Error("Offline transaction failed."),
            );
    });
}

export async function openOfflineDatabase(): Promise<IDBDatabase> {
    const request = indexedDB.open(databaseName, databaseVersion);
    request.onupgradeneeded = () => {
        for (const store of stores)
            if (!request.result.objectStoreNames.contains(store))
                request.result.createObjectStore(store);
    };
    return requestResult(request);
}

export async function commitOfflineSale(
    sale: OfflineSale,
    command: OfflineOutboxCommand,
): Promise<void> {
    if (sale.commandId !== command.commandId)
        throw new Error("Sale and outbox command identities differ.");
    const database = await openOfflineDatabase();
    try {
        const transaction = database.transaction(
            ["sales", "outbox"],
            "readwrite",
            { durability: "strict" },
        );
        transaction.objectStore("sales").put(sale, sale.commandId);
        transaction.objectStore("outbox").put(command, command.commandId);
        await completed(transaction);
    } finally {
        database.close();
    }
}

export async function listOfflineSales(): Promise<OfflineSale[]> {
    const database = await openOfflineDatabase();
    try {
        const transaction = database.transaction("sales", "readonly");
        const sales = await requestResult(
            transaction.objectStore("sales").getAll(),
        );
        await completed(transaction);
        return (sales as OfflineSale[]).sort((left, right) =>
            right.committedAtLocal.localeCompare(left.committedAtLocal),
        );
    } finally {
        database.close();
    }
}

export async function updateOfflineSale(
    commandId: string,
    state: OfflineSale["state"],
    conflict?: OfflineSale["conflict"],
): Promise<void> {
    const database = await openOfflineDatabase();
    try {
        const transaction = database.transaction(
            ["sales", "outbox"],
            "readwrite",
            { durability: "strict" },
        );
        const sale = (await requestResult(
            transaction.objectStore("sales").get(commandId),
        )) as OfflineSale | undefined;
        const command = (await requestResult(
            transaction.objectStore("outbox").get(commandId),
        )) as OfflineOutboxCommand | undefined;
        if (!sale || !command)
            throw new Error("Offline evidence is incomplete.");
        transaction
            .objectStore("sales")
            .put(
                { ...sale, state, ...(conflict ? { conflict } : {}) },
                commandId,
            );
        transaction.objectStore("outbox").put({ ...command, state }, commandId);
        await completed(transaction);
    } finally {
        database.close();
    }
}

export async function exportUnresolvedEvidence(): Promise<string> {
    const sales = (await listOfflineSales()).filter(
        (sale) => sale.state !== "accepted",
    );
    return JSON.stringify(
        { schemaVersion: 1, exportedAt: new Date().toISOString(), sales },
        null,
        2,
    );
}

export async function requestDurableStorage(): Promise<{
    persisted: boolean;
    quota?: number;
    usage?: number;
}> {
    const persisted = (await navigator.storage?.persist?.()) ?? false;
    const estimate = await navigator.storage?.estimate?.();
    return {
        persisted,
        ...(estimate?.quota === undefined ? {} : { quota: estimate.quota }),
        ...(estimate?.usage === undefined ? {} : { usage: estimate.usage }),
    };
}
