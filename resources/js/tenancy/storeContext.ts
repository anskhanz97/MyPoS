import { ref } from "vue";

export interface StoreOption {
    id: string;
    name: string;
    code: string;
}

export function useStoreContext(initialStoreId: string) {
    const activeStoreId = ref(initialStoreId);
    const draftReference = ref("");
    const searchTerm = ref("");
    const cacheGeneration = ref(0);
    const announcement = ref("");

    function switchStore(store: StoreOption): void {
        if (store.id === activeStoreId.value) return;

        activeStoreId.value = store.id;
        draftReference.value = "";
        searchTerm.value = "";
        cacheGeneration.value += 1;
        announcement.value = `${store.name} is active. Store-specific drafts and cached views were cleared.`;
    }

    return {
        activeStoreId,
        announcement,
        cacheGeneration,
        draftReference,
        searchTerm,
        switchStore,
    };
}
