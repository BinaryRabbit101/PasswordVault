<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, Search, Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import ItemRow from '@/components/vault/ItemRow.vue';
import ItemSheet from '@/components/vault/ItemSheet.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { useAutoLock } from '@/composables/useAutoLock';
import { useClipboard } from '@/composables/useClipboard';
import { secrets } from '@/routes/items';
import { index as vaultIndex } from '@/routes/vault';
import type { VaultItem, VaultSummary } from '@/types/vault';

const props = defineProps<{
    vaults: VaultSummary[];
    items: VaultItem[];
    clipboardClearSeconds: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vault', href: vaultIndex() }],
    },
});

const { copy } = useClipboard(props.clipboardClearSeconds);

const search = ref('');
const activeVaultId = ref<number | null>(null);
const sheetOpen = ref(false);
const activeItem = ref<VaultItem | null>(null);

// Backgrounding the app closes the sheet, dropping any revealed secrets.
useAutoLock(() => {
    sheetOpen.value = false;
});

const filteredItems = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.items.filter((item) => {
        if (activeVaultId.value !== null && item.vault_id !== activeVaultId.value) {
            return false;
        }

        if (query === '') return true;

        return [item.name, item.url, item.username, item.folder].some(
            (value) => value?.toLowerCase().includes(query),
        );
    });
});

const favorites = computed(() =>
    filteredItems.value.filter((item) => item.favorite),
);

const sections = computed(() => {
    const groups = new Map<string, VaultItem[]>();

    for (const item of filteredItems.value) {
        const key = item.folder ?? '';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key)!.push(item);
    }

    return [...groups.entries()]
        .sort(([a], [b]) => {
            if (a === '') return 1;
            if (b === '') return -1;
            return a.localeCompare(b);
        })
        .map(([title, items]) => ({ title: title || 'No folder', items }));
});

const openItem = (item: VaultItem) => {
    activeItem.value = item;
    sheetOpen.value = true;
};

const openCreate = () => {
    activeItem.value = null;
    sheetOpen.value = true;
};

const copyUsername = (item: VaultItem) => {
    if (item.username) {
        void copy('Username', item.username);
    }
};

// The ClipboardItem promise is created synchronously inside the tap gesture
// so iOS Safari accepts the write once the fetch resolves.
const copyPassword = (item: VaultItem) => {
    void copy(
        'Password',
        fetch(secrets.url(item.id), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => response.json())
            .then((data: { password: string | null }) => data.password ?? ''),
    );
};
</script>

<template>
    <Head title="Vault" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
        <div class="sticky top-0 z-10 -mx-4 space-y-3 bg-background px-4 pt-1 pb-3">
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search vault…"
                    class="pl-9"
                    autocapitalize="none"
                    autocomplete="off"
                />
            </div>

            <div v-if="vaults.length > 1" class="flex gap-2 overflow-x-auto">
                <button
                    type="button"
                    class="shrink-0 rounded-full border px-3 py-1 text-sm transition-colors"
                    :class="activeVaultId === null ? 'border-primary bg-primary text-primary-foreground' : 'border-input text-muted-foreground hover:bg-accent'"
                    @click="activeVaultId = null"
                >
                    All
                </button>
                <button
                    v-for="vault in vaults"
                    :key="vault.id"
                    type="button"
                    class="shrink-0 rounded-full border px-3 py-1 text-sm transition-colors"
                    :class="activeVaultId === vault.id ? 'border-primary bg-primary text-primary-foreground' : 'border-input text-muted-foreground hover:bg-accent'"
                    @click="activeVaultId = vault.id"
                >
                    {{ vault.name }}
                </button>
            </div>
        </div>

        <section v-if="favorites.length > 0 && search === ''">
            <h2
                class="mb-1 flex items-center gap-1.5 px-2 text-sm font-medium text-muted-foreground"
            >
                <Star class="size-3.5 fill-amber-400 text-amber-400" />
                Favorites
            </h2>
            <ItemRow
                v-for="item in favorites"
                :key="`fav-${item.id}`"
                :item="item"
                @open="openItem(item)"
                @copy-username="copyUsername(item)"
                @copy-password="copyPassword(item)"
            />
        </section>

        <section v-for="section in sections" :key="section.title">
            <h2 class="mb-1 px-2 text-sm font-medium text-muted-foreground">
                {{ section.title }}
            </h2>
            <ItemRow
                v-for="item in section.items"
                :key="item.id"
                :item="item"
                @open="openItem(item)"
                @copy-username="copyUsername(item)"
                @copy-password="copyPassword(item)"
            />
        </section>

        <p
            v-if="filteredItems.length === 0"
            class="py-16 text-center text-muted-foreground"
        >
            {{ items.length === 0 ? 'Your vault is empty. Add your first item or import from LastPass.' : 'No items match your search.' }}
        </p>

        <Button
            class="fixed right-5 bottom-5 z-20 size-14 rounded-full shadow-lg"
            size="icon"
            title="Add item"
            @click="openCreate"
        >
            <Plus class="size-6" />
            <span class="sr-only">Add item</span>
        </Button>

        <ItemSheet
            v-model:open="sheetOpen"
            :item="activeItem"
            :vaults="vaults"
            :clipboard-clear-seconds="clipboardClearSeconds"
        />
    </div>
</template>
