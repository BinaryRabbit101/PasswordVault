<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    Eye,
    EyeOff,
    History,
    Plus,
    Star,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import CopyButton from '@/components/vault/CopyButton.vue';
import PasswordGenerator from '@/components/vault/PasswordGenerator.vue';
import TotpCode from '@/components/vault/TotpCode.vue';
import { useClipboard } from '@/composables/useClipboard';
import { externalHref } from '@/lib/utils';
import { destroy, secrets, store, update } from '@/routes/items';
import { history as fieldHistory } from '@/routes/items/fields';
import type {
    FieldAutofill,
    FieldHistoryEntry,
    FieldType,
    ItemField,
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

/** A field in the edit form; `key` is local only, for stable v-for keys. */
type FormField = ItemField & { key: number };

const FIELD_TYPES: { value: FieldType; label: string }[] = [
    { value: 'text', label: 'Text' },
    { value: 'password', label: 'Password' },
    { value: 'email', label: 'Email' },
    { value: 'url', label: 'Website' },
    { value: 'totp', label: 'One-time code' },
    { value: 'note', label: 'Note' },
];

const AUTOFILL_TYPES: FieldType[] = ['text', 'email', 'password'];

const TEMPLATES: { name: string; fields: [string, FieldType][] }[] = [
    {
        name: 'Login',
        fields: [
            ['Username', 'text'],
            ['Password', 'password'],
            ['Website', 'url'],
        ],
    },
    {
        name: 'API credential',
        fields: [
            ['Credential', 'password'],
            ['Website', 'url'],
        ],
    },
    { name: 'Secure note', fields: [['Notes', 'note']] },
    { name: 'Blank', fields: [] },
];

const editing = ref(false);
const loadedSecrets = ref<ItemSecrets | null>(null);
const template = ref(TEMPLATES[0].name);

/** Field ids (view) or keys (edit) whose concealed value is shown. */
const revealed = ref(new Set<number>());
const generatorFor = ref<number | null>(null);

const historyField = ref<ItemField | null>(null);
const historyLoading = ref(false);
const history = ref<FieldHistoryEntry[]>([]);
const revealedHistoryIds = ref(new Set<number>());

let nextKey = 0;

const form = useForm<{
    vault_id: number | null;
    name: string;
    favorite: boolean;
    folder: string;
    fields: FormField[];
}>({
    vault_id: null,
    name: '',
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

const visibleFields = computed(() =>
    (loadedSecrets.value?.fields ?? []).filter(
        (field) => field.value !== null && field.value !== '',
    ),
);

const fieldErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const fetchJson = async <T,>(url: string): Promise<T> => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }

    return response.json();
};

const toggle = (set: Set<number>, id: number): Set<number> => {
    const next = new Set(set);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    return next;
};

const newField = (label: string, type: FieldType): FormField => ({
    key: nextKey++,
    label,
    type,
    autofill: null,
    value: '',
});

const applyTemplate = (name: string) => {
    const chosen = TEMPLATES.find((t) => t.name === name);

    if (!chosen) {
        return;
    }

    const hasValues = form.fields.some((field) => field.value);

    if (
        hasValues &&
        !window.confirm(
            'Switch template? The fields you filled in are cleared.',
        )
    ) {
        return;
    }

    template.value = name;
    form.fields = chosen.fields.map(([label, type]) => newField(label, type));
};

const resetSensitiveState = () => {
    editing.value = false;
    loadedSecrets.value = null;
    revealed.value = new Set();
    generatorFor.value = null;
    historyField.value = null;
    history.value = [];
    revealedHistoryIds.value = new Set();
    form.reset();
    form.clearErrors();
};

watch(
    () => props.open,
    async (open) => {
        resetSensitiveState();

        if (!open) {
            return;
        }

        if (props.item) {
            try {
                loadedSecrets.value = await fetchJson<ItemSecrets>(
                    secrets.url(props.item.id),
                );
            } catch {
                loadedSecrets.value = null;
            }
        } else {
            editing.value = true;
            form.vault_id = props.vaults[0]?.id ?? null;
            applyTemplate(TEMPLATES[0].name);
        }
    },
);

/**
 * Auto-lock's `onHide`: drop revealed secrets from memory without closing
 * the sheet, so returning to the tab doesn't lose your place in the list.
 *
 * A create/edit form in progress has no "masked" view to fall back to, so
 * that case still closes the sheet outright — same as before this existed.
 */
const lock = () => {
    if (!props.open) {
        return;
    }

    if (editing.value) {
        emit('update:open', false);

        return;
    }

    resetSensitiveState();
};

/**
 * Auto-lock's `onShow`: reload whatever `lock()` dropped, same as the
 * fetch that runs when the sheet first opens on this item.
 */
const unlock = async () => {
    if (!props.open || !props.item || editing.value) {
        return;
    }

    try {
        loadedSecrets.value = await fetchJson<ItemSecrets>(
            secrets.url(props.item.id),
        );
    } catch {
        loadedSecrets.value = null;
    }
};

defineExpose({ lock, unlock });

const startEditing = () => {
    if (!props.item || !loadedSecrets.value) {
        return;
    }

    form.vault_id = props.item.vault_id;
    form.name = props.item.name;
    form.favorite = props.item.favorite;
    form.folder = props.item.folder ?? '';
    form.fields = loadedSecrets.value.fields.map((field) => ({
        ...field,
        key: nextKey++,
    }));

    revealed.value = new Set();
    editing.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    const request = form.transform((data) => ({
        ...data,
        fields: data.fields.map((field) => ({
            id: field.id,
            label: field.label,
            type: field.type,
            autofill: field.autofill,
            value: field.value,
        })),
    }));

    if (isCreate.value) {
        request.submit(store(), options);
    } else if (props.item) {
        request.submit(update(props.item.id), options);
    }
};

const deleteItem = () => {
    if (!props.item) {
        return;
    }

    if (
        !window.confirm(
            `Delete "${props.item.name}"? It can be restored from the database if needed.`,
        )
    ) {
        return;
    }

    router.delete(destroy.url(props.item.id), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
};

const addField = () => {
    form.fields.push(newField('', 'text'));
};

const removeField = (index: number) => {
    form.fields.splice(index, 1);
};

const moveField = (index: number, offset: -1 | 1) => {
    const target = index + offset;

    if (target < 0 || target >= form.fields.length) {
        return;
    }

    const [field] = form.fields.splice(index, 1);
    form.fields.splice(target, 0, field);
};

const onTypeChange = (field: FormField) => {
    if (!AUTOFILL_TYPES.includes(field.type)) {
        field.autofill = null;
    }
};

const setAutofill = (field: FormField, value: string) => {
    field.autofill = (value === '' ? null : value) as FieldAutofill;
};

const openHistory = async (field: ItemField) => {
    if (!props.item || field.id === undefined) {
        return;
    }

    historyField.value = field;
    revealedHistoryIds.value = new Set();
    historyLoading.value = true;

    try {
        const data = await fetchJson<{ history: FieldHistoryEntry[] }>(
            fieldHistory.url({ item: props.item.id, field: field.id }),
        );
        history.value = data.history;
    } catch {
        history.value = [];
    } finally {
        historyLoading.value = false;
    }
};

const historyOpen = computed({
    get: () => historyField.value !== null,
    set: (open: boolean) => {
        if (!open) {
            historyField.value = null;
            history.value = [];
        }
    },
});

const formatHistoryDate = (value: string) =>
    new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

const MASK = '••••••••••••';
</script>

<template>
    <Sheet :open="open" @update:open="(value) => emit('update:open', value)">
        <SheetContent
            side="bottom"
            class="max-h-[92dvh] overflow-y-auto rounded-t-2xl sm:mx-auto sm:max-w-lg"
            @interact-outside="
                (event: Event) => editing && event.preventDefault()
            "
        >
            <SheetHeader class="text-left">
                <SheetTitle>
                    {{ isCreate ? 'New item' : (item?.name ?? '') }}
                </SheetTitle>
                <SheetDescription v-if="!isCreate">
                    {{ vaultName }}
                    <template v-if="item?.folder">
                        · {{ item.folder }}</template
                    >
                </SheetDescription>
                <SheetDescription v-else>
                    Pick a starting point — every field can be renamed, retyped
                    or removed.
                </SheetDescription>
            </SheetHeader>

            <!-- ============ View mode ============ -->
            <div v-if="!editing && item" class="space-y-4 px-4 pb-6">
                <div
                    v-for="(field, index) in visibleFields"
                    :key="field.id"
                    class="space-y-1"
                    :data-test="`view-field-${index}`"
                    :data-field-type="field.type"
                >
                    <div class="flex items-center justify-between">
                        <Label
                            class="text-muted-foreground"
                            data-test="view-field-label"
                            >{{ field.label }}</Label
                        >
                        <button
                            type="button"
                            class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                            data-test="view-field-history"
                            :title="`Previous values of ${field.label}`"
                            @click="openHistory(field)"
                        >
                            <History class="size-3.5" />
                            <span class="sr-only">History</span>
                        </button>
                    </div>

                    <!-- Every value is plain selectable text with its copy
                         button on the left; nothing copies on a stray tap. -->
                    <TotpCode
                        v-if="field.type === 'totp'"
                        :secret="field.value!"
                        :label="field.label"
                        @copy="(code) => copy(field.label, code)"
                    />

                    <div
                        v-else-if="field.type === 'note'"
                        class="flex items-start gap-2"
                    >
                        <CopyButton
                            :label="field.label"
                            data-test="view-field-copy"
                            @click="copy(field.label, field.value!)"
                        />
                        <p
                            class="min-w-0 flex-1 rounded-md bg-muted px-3 py-2 text-sm break-words whitespace-pre-wrap select-text"
                            data-test="view-field-value"
                        >
                            {{ field.value }}
                        </p>
                    </div>

                    <div
                        v-else-if="field.type === 'url'"
                        class="flex items-center gap-2"
                    >
                        <CopyButton
                            :label="field.label"
                            data-test="view-field-copy"
                            @click="copy(field.label, field.value!)"
                        />
                        <a
                            :href="externalHref(field.value!)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block min-w-0 flex-1 truncate text-sm underline underline-offset-4"
                            data-test="view-field-value"
                        >
                            {{ field.value }}
                        </a>
                    </div>

                    <div v-else class="flex items-center gap-2">
                        <CopyButton
                            :label="field.label"
                            data-test="view-field-copy"
                            @click="copy(field.label, field.value!)"
                        />
                        <span
                            class="min-w-0 flex-1 rounded-md bg-muted px-3 py-2 font-mono text-sm break-all select-text"
                            data-test="view-field-value"
                        >
                            {{
                                field.type === 'password' &&
                                !revealed.has(field.id!)
                                    ? MASK
                                    : field.value
                            }}
                        </span>
                        <Button
                            v-if="field.type === 'password'"
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="
                                revealed.has(field.id!) ? 'Hide' : 'Show'
                            "
                            data-test="view-field-reveal"
                            @click="revealed = toggle(revealed, field.id!)"
                        >
                            <EyeOff
                                v-if="revealed.has(field.id!)"
                                class="size-4"
                            />
                            <Eye v-else class="size-4" />
                        </Button>
                    </div>
                </div>

                <p
                    v-if="loadedSecrets && visibleFields.length === 0"
                    class="text-sm text-muted-foreground"
                    data-test="no-fields"
                >
                    No fields yet — tap Edit to add some.
                </p>

                <div class="flex gap-2 pt-2">
                    <Button
                        class="flex-1"
                        :disabled="!loadedSecrets"
                        data-test="item-edit"
                        @click="startEditing"
                    >
                        Edit
                    </Button>
                    <Button
                        variant="destructive"
                        size="icon"
                        data-test="item-delete"
                        @click="deleteItem"
                    >
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
                <div v-if="isCreate" class="flex flex-wrap gap-2">
                    <button
                        v-for="option in TEMPLATES"
                        :key="option.name"
                        type="button"
                        :data-test="`template-${option.name.toLowerCase().replace(' ', '-')}`"
                        class="rounded-full border px-3 py-1 text-sm transition-colors"
                        :class="
                            template === option.name
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input text-muted-foreground hover:bg-accent'
                        "
                        @click="applyTemplate(option.name)"
                    >
                        {{ option.name }}
                    </button>
                </div>

                <div class="grid gap-2">
                    <Label for="item-name">Name</Label>
                    <Input
                        id="item-name"
                        v-model="form.name"
                        data-test="item-name"
                        required
                    />
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

                <div class="space-y-2">
                    <Label>Fields</Label>

                    <div
                        v-for="(field, index) in form.fields"
                        :key="field.key"
                        class="space-y-2 rounded-lg border border-input p-2"
                        :data-test="`field-${index}`"
                    >
                        <div class="grid grid-cols-[1fr_auto] gap-2">
                            <Input
                                v-model="field.label"
                                placeholder="Label"
                                aria-label="Field label"
                                data-test="field-label"
                                required
                            />
                            <select
                                v-model="field.type"
                                aria-label="Field type"
                                data-test="field-type"
                                class="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                                @change="onTypeChange(field)"
                            >
                                <option
                                    v-for="type in FIELD_TYPES"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>

                        <div
                            class="flex gap-2"
                            :class="
                                field.type === 'note'
                                    ? 'items-start'
                                    : 'items-center'
                            "
                        >
                            <CopyButton
                                :label="field.label || 'value'"
                                :disabled="!field.value"
                                data-test="field-copy"
                                @click="
                                    copy(
                                        field.label || 'Value',
                                        field.value ?? '',
                                    )
                                "
                            />

                            <textarea
                                v-if="field.type === 'note'"
                                v-model="field.value"
                                rows="3"
                                :aria-label="field.label || 'Value'"
                                data-test="field-value"
                                class="min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            ></textarea>

                            <div
                                v-else-if="field.type === 'password'"
                                class="relative min-w-0 flex-1"
                            >
                                <Input
                                    :model-value="field.value ?? ''"
                                    :type="
                                        revealed.has(field.key)
                                            ? 'text'
                                            : 'password'
                                    "
                                    :aria-label="field.label || 'Value'"
                                    data-test="field-value"
                                    autocomplete="off"
                                    class="pr-10 font-mono"
                                    @update:model-value="
                                        (v) => (field.value = String(v))
                                    "
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-muted-foreground hover:text-foreground"
                                    :aria-label="
                                        revealed.has(field.key)
                                            ? 'Hide'
                                            : 'Show'
                                    "
                                    data-test="field-reveal"
                                    @click="
                                        revealed = toggle(revealed, field.key)
                                    "
                                >
                                    <EyeOff
                                        v-if="revealed.has(field.key)"
                                        class="size-4"
                                    />
                                    <Eye v-else class="size-4" />
                                </button>
                            </div>

                            <Input
                                v-else
                                :model-value="field.value ?? ''"
                                :type="
                                    field.type === 'email' ? 'email' : 'text'
                                "
                                :inputmode="
                                    field.type === 'url' ? 'url' : undefined
                                "
                                :placeholder="
                                    field.type === 'totp'
                                        ? 'Base32 secret or otpauth:// link'
                                        : field.type === 'url'
                                          ? 'https://example.com'
                                          : ''
                                "
                                :aria-label="field.label || 'Value'"
                                data-test="field-value"
                                autocapitalize="none"
                                autocomplete="off"
                                class="min-w-0 flex-1"
                                :class="
                                    field.type === 'text' ? '' : 'font-mono'
                                "
                                @update:model-value="
                                    (v) => (field.value = String(v))
                                "
                            />
                        </div>

                        <p
                            v-if="fieldErrors[`fields.${index}.value`]"
                            class="text-sm text-destructive"
                            data-test="field-error"
                        >
                            {{ fieldErrors[`fields.${index}.value`] }}
                        </p>

                        <PasswordGenerator
                            v-if="generatorFor === field.key"
                            @use="
                                (password) => {
                                    field.value = password;
                                    generatorFor = null;
                                    revealed = new Set([
                                        ...revealed,
                                        field.key,
                                    ]);
                                }
                            "
                        />

                        <div class="flex items-center gap-1">
                            <select
                                v-if="AUTOFILL_TYPES.includes(field.type)"
                                :value="field.autofill ?? ''"
                                aria-label="Autofill as"
                                data-test="field-autofill"
                                class="h-8 rounded-md border border-input bg-transparent px-2 text-xs text-muted-foreground"
                                @change="
                                    (event) =>
                                        setAutofill(
                                            field,
                                            (event.target as HTMLSelectElement)
                                                .value,
                                        )
                                "
                            >
                                <option value="">Autofill: auto</option>
                                <option value="username">
                                    Autofill: username
                                </option>
                                <option value="password">
                                    Autofill: password
                                </option>
                                <option value="none">Don't autofill</option>
                            </select>
                            <button
                                v-if="field.type === 'password'"
                                type="button"
                                class="px-2 text-xs text-muted-foreground underline underline-offset-4"
                                data-test="field-generate"
                                @click="
                                    generatorFor =
                                        generatorFor === field.key
                                            ? null
                                            : field.key
                                "
                            >
                                {{
                                    generatorFor === field.key
                                        ? 'Hide generator'
                                        : 'Generate'
                                }}
                            </button>

                            <span class="flex-1"></span>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :disabled="index === 0"
                                data-test="field-up"
                                @click="moveField(index, -1)"
                            >
                                <ChevronUp class="size-4" />
                                <span class="sr-only">Move up</span>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :disabled="index === form.fields.length - 1"
                                data-test="field-down"
                                @click="moveField(index, 1)"
                            >
                                <ChevronDown class="size-4" />
                                <span class="sr-only">Move down</span>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                data-test="field-remove"
                                @click="removeField(index)"
                            >
                                <X class="size-4" />
                                <span class="sr-only">Remove field</span>
                            </Button>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        data-test="add-field"
                        @click="addField"
                    >
                        <Plus class="size-4" /> Add field
                    </Button>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.favorite"
                        type="checkbox"
                        data-test="item-favorite"
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
                        data-test="item-save"
                    >
                        {{ isCreate ? 'Add item' : 'Save changes' }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="
                            isCreate
                                ? emit('update:open', false)
                                : (editing = false)
                        "
                    >
                        Cancel
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>

    <Dialog v-model:open="historyOpen">
        <DialogContent class="max-h-[80dvh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ historyField?.label }} history</DialogTitle>
                <DialogDescription>
                    The last 10 values this field held.
                </DialogDescription>
            </DialogHeader>

            <p v-if="historyLoading" class="text-sm text-muted-foreground">
                Loading…
            </p>
            <ul v-else-if="history.length" class="space-y-2">
                <li
                    v-for="entry in history"
                    :key="entry.id"
                    class="space-y-1"
                    data-test="history-entry"
                >
                    <Label class="text-muted-foreground">{{
                        formatHistoryDate(entry.created_at)
                    }}</Label>
                    <div class="flex items-center gap-2">
                        <CopyButton
                            :label="`previous ${historyField?.label ?? 'value'}`"
                            data-test="history-copy"
                            @click="
                                copy(
                                    `Previous ${historyField?.label ?? 'value'}`,
                                    entry.value,
                                )
                            "
                        />
                        <span
                            class="min-w-0 flex-1 rounded-md bg-muted px-3 py-2 font-mono text-sm break-all select-text"
                            data-test="history-value"
                        >
                            {{
                                historyField?.type === 'password' &&
                                !revealedHistoryIds.has(entry.id)
                                    ? MASK
                                    : entry.value
                            }}
                        </span>
                        <Button
                            v-if="historyField?.type === 'password'"
                            type="button"
                            data-test="history-reveal"
                            variant="ghost"
                            size="icon"
                            @click="
                                revealedHistoryIds = toggle(
                                    revealedHistoryIds,
                                    entry.id,
                                )
                            "
                        >
                            <Eye
                                v-if="!revealedHistoryIds.has(entry.id)"
                                class="size-4"
                            />
                            <EyeOff v-else class="size-4" />
                        </Button>
                    </div>
                </li>
            </ul>
            <p
                v-else
                class="text-sm text-muted-foreground"
                data-test="history-empty"
            >
                No previous values recorded.
            </p>
        </DialogContent>
    </Dialog>
</template>
