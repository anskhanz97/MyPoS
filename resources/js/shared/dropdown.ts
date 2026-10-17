let active: { id: symbol; close: () => void } | undefined;

export function claimDropdown(id: symbol, close: () => void): void {
    if (active && active.id !== id) active.close();
    active = { id, close };
}

export function releaseDropdown(id: symbol): void {
    if (active?.id === id) active = undefined;
}
