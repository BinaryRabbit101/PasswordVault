<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, Plus, Star, Trash2, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PasswordGenerator from '@/components/vault/PasswordGenerator.vue';
import TotpCode from '@/components/vault/TotpCode.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useClipboard } from '@/composables/useClipboard';
import { destroy, secrets, store, update } from '@/routes/items';
import type {
    ItemCustomField,
    ItemSecrets,
    VaultItem,
    VaultSummary,
} from '@/types/vault';

const props = defineProps<{
    open: boolean;
    item: VaultItem | null;
    vaults: VaultSummary[];
    clipboardClearSeconds: number;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { copy } = useClipboard(props.clipboardClearSeconds);

const editing = ref(false);
const showPassword = ref(false);
const showGenerator = ref(false);
const loadedSecrets = ref<ItemSecrets | null>(null);

const FIELD_TYPES: ItemCustomField['type'][] = [
    'text',
    'password',
    'url',
    'note',
    'totp',
    'email',
];

const form = useForm<{
    vault_id: number | null;
    name: string;
    url: string;
    username: string;
    password: string;
    notes: string;
    totp_secret: string;
    favorite: boolean;
    folder: string;
    fields: ItemCustomField[];
}>({
    vault_id: null,
    name: '',
    url: '',
    username: '',
    password: '',
    notes: '',
    totp_secret: '',
    favorite: false,
    folder: '',
    fields: [],
});

const isCreate = computed(() => props.item === null);
const vaultName = computed(
    () =>
        props.vaults.find((vault) => vault.id === props.item?.vault_id)?.name ??
        '',
);

const fetchSecrets = async (itemId: number): Promise<ItemSecrets> => {
    const response = await fetch(secrets.url(itemId), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Failed to load secrets (${response.status})`);
    }

    return response.json();
};

watch(
    () => props.open,
    async (open) => {
        editing.value = false;
        showPassword.value = false;
        showGenerator.value = false;
        loadedSecrets.value = null;
        form.reset();
        form.clearErrors();

        if (!open) return;

        if (props.item) {
            try {
                loadedSecrets.value = await fetchSecrets(props.item.id);
            } catch {
                loadedSecrets.value = null;
            }
        } else {
            editing.value = true;
            form.vault_id = props.vaults[0]?.id ?? null;
        }
    },
);

const startEditing = () => {
    if (!props.item || !loadedSecrets.value) return;

    form.vault_id = props.item.vault_id;
    form.name = props.item.name;
    form.url = props.item.url ?? '';
    form.username = props.item.username ?? '';
    form.password = loadedSecrets.value.password ?? '';
    form.notes = loadedSecrets.value.notes ?? '';
    form.totp_secret = loadedSecrets.value.totp_secret ?? '';
    form.favorite = props.item.favorite;
    form.folder = props.item.folder ?? '';
    form.fields = loadedSecrets.value.fields.map((field) => ({ ...field }));

    editing.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    if (isCreate.value) {
        form.submit(store(), options);
    } else if (props.item) {
        form.submit(update(props.item.id), options);
    }
};

const deleteItem = () => {
    if (!props.item) return;
    if (!window.confirm(`Delete "${props.item.name}"? It can be restored from the database if needed.`)) {
        return;
    }

    router.delete(destroy.url(props.item.id), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
};

const addField = () => {
    form.fields.push({ label: '', type: 'text', value: '', is_secret: true });
};

const removeField = (index: number) => {
    form.fields.splice(index, 1);
};

const copyPassword = () => {
    if (loadedSecrets.value?.password) {
        void copy('Password', loadedSecrets.value.password);
    }
};

const maskedPassword = computed(() =>
    loadedSecrets.value?.password ? '••••••••••••' : '—',
);
</script>

<template>
    <Sheet :open="open" @update:open="(value) => emit('update:open', value)">
        <SheetContent
            side="bottom"
            class="max-h-[92dvh] overflow-y-auto rounded-t-2xl sm:mx-auto sm:max-w-lg"
        >
            <SheetHeader class="text-left">
                <SheetTitle>
                    {{ isCreate ? 'New item' : (item?.name ?? '') }}
                </SheetTitle>
                <SheetDescription v-if="!isCreate">
                    {{ vaultName }}
                    <template v-if="item?.folder"> · {{ item.folder }}</template>
                </SheetDescription>
                <SheetDescription v-else>
                    Add a login or secure note to your vault.
                </SheetDescription>
            </SheetHeader>

            <!-- ============ View mode ============ -->
            <div v-if="!editing && item" class="space-y-4 px-4 pb-6">
                <div v-if="item.url" class="space-y-1">
                    <Label class="text-muted-foreground">Website</Label>
                    <div class="flex items-center gap-2">
                        <a
                            :href="item.url.startsWith('http') ? item.url : `https://${item.url}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="truncate text-sm underline underline-offset-4"
                        >
                            {{ item.url }}
                        </a>
                    </div>
                </div>

                <div v-if="item.username" class="space-y-1">
                    <Label class="text-muted-foreground">Username</Label>
                    <button
                        type="button"
                        class="block w-full truncate rounded-md bg-muted px-3 py-2 text-left font-mono text-sm hover:bg-accent"
                        @click="copy('Username', item.username!)"
                    >
                        {{ item.username }}
                    </button>
                </div>

                <div class="space-y-1">
                    <Label class="text-muted-foreground">Password</Label>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="min-w-0 flex-1 truncate rounded-md bg-muted px-3 py-2 text-left font-mono text-sm hover:bg-accent"
                            title="Tap to copy"
                            @click="copyPassword"
                        >
                            {{ showPassword ? (loadedSecrets?.password ?? '—') : maskedPassword }}
                        </button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            @click="showPassword = !showPassword"
                        >
                            <Eye v-if="!showPassword" class="size-4" />
                            <EyeOff v-else class="size-4" />
                        </Button>
                    </div>
                </div>

                <div v-if="loadedSecrets?.totp_secret" class="space-y-1">
                    <Label class="text-muted-foreground">One-time code</Label>
                    <TotpCode
                        :secret="loadedSecrets.totp_secret"
                        @copy="(code) => copy('Code', code)"
                    />
                </div>

                <div v-if="loadedSecrets?.notes" class="space-y-1">
                    <Label class="text-muted-foreground">Notes</Label>
                    <p
                        class="rounded-md bg-muted px-3 py-2 text-sm whitespace-pre-wrap"
                    >
                        {{ loadedSecrets.notes }}
                    </p>
                </div>

                <div
                    v-for="field in loadedSecrets?.fields ?? []"
                    :key="field.id"
                    class="space-y-1"
                >
                    <Label class="text-muted-foreground">{{ field.label }}</Label>
                    <button
                        type="button"
                        class="block w-full truncate rounded-md bg-muted px-3 py-2 text-left font-mono text-sm hover:bg-accent"
                        @click="copy(field.label, field.value ?? '')"
                    >
                        {{ field.is_secret ? '••••••••' : (field.value ?? '') }}
                    </button>
                </div>

                <div class="flex gap-2 pt-2">
                    <Button
                        class="flex-1"
                        :disabled="!loadedSecrets"
                        @click="startEditing"
                    >
                        Edit
                    </Button>
                    <Button variant="destructive" size="icon" @click="deleteItem">
                        <Trash2 class="size-4" />
                        <span class="sr-only">Delete item</span>
                    </Button>
                </div>
            </div>

            <!-- ============ Edit / create mode ============ -->
            <form
                v-else-if="editing"
                class="space-y-4 px-4 pb-6"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="item-name">Name</Label>
                    <Input id="item-name" v-model="form.name" required />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="item-vault">Vault</Label>
                        <select
                            id="item-vault"
                            v-model="form.vault_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option
                                v-for="vault in vaults"
                                :key="vault.id"
                                :value="vault.id"
                            >
                                {{ vault.name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="item-folder">Folder</Label>
                        <Input
                            id="item-folder"
                            v-model="form.folder"
                            placeholder="None"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="item-url">Website</Label>
                    <Input
                        id="item-url"
                        v-model="form.url"
                        inputmode="url"
                        placeholder="https://example.com"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="item-username">Username</Label>
                    <Input
                        id="item-username"
                        v-model="form.username"
                        autocapitalize="none"
                        autocomplete="off"
                    />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="item-password">Password</Label>
                        <button
                            type="button"
                            class="text-sm text-muted-foreground underline underline-offset-4"
                            @click="showGenerator = !showGenerator"
                        >
                            {{ showGenerator ? 'Hide generator' : 'Generate' }}
                        </button>
                    </div>
                    <Input
                        id="item-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="off"
                        class="font-mono"
                    />
                    <PasswordGenerator
                        v-if="showGenerator"
                        @use="(password) => { form.password = password; showGenerator = false; showPassword = true; }"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="item-totp">TOTP secret (2FA)</Label>
                    <Input
                        id="item-totp"
                        v-model="form.totp_secret"
                        autocapitalize="none"
                        autocomplete="off"
                        placeholder="Base32 secret or otpauth:// URI"
                        class="font-mono"
                    />
                    <p
                        v-if="form.errors.totp_secret"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.totp_secret }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="item-notes">Notes</Label>
                    <textarea
                        id="item-notes"
                        v-model="form.notes"
                        rows="3"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                    ></textarea>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Custom fields</Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="addField"
                        >
                            <Plus class="size-4" /> Add field
                        </Button>
                    </div>
                    <div
                        v-for="(field, index) in form.fields"
                        :key="index"
                        class="grid grid-cols-[1fr_auto] gap-2 rounded-lg border border-input p-2"
                    >
                        <div class="space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <Input
                                    v-model="field.label"
                                    placeholder="Label"
                                    required
                                />
                                <select
                                    v-model="field.type"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option
                                        v-for="type in FIELD_TYPES"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ type }}
                                    </option>
                                </select>
                            </div>
                            <Input
                                :model-value="field.value ?? ''"
                                placeholder="Value"
                                class="font-mono"
                                @update:model-value="(v) => (field.value = String(v))"
                            />
                            <label
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <input
                                    v-model="field.is_secret"
                                    type="checkbox"
                                    class="accent-primary"
                                />
                                Hide value until tapped
                            </label>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            @click="removeField(index)"
                        >
                            <X class="size-4" />
                            <span class="sr-only">Remove field</span>
                        </Button>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.favorite"
                        type="checkbox"
                        class="accent-primary"
                    />
                    <Star class="size-4 text-amber-400" />
                    Favorite
                </label>

                <div class="flex gap-2 pt-2">
                    <Button
                        type="submit"
                        class="flex-1"
                        :disabled="form.processing"
                    >
                        {{ isCreate ? 'Add item' : 'Save changes' }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="isCreate ? emit('update:open', false) : (editing = false)"
                    >
                        Cancel
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
