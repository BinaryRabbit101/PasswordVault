<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bell, BellOff, Download, FileUp, Star } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { usePush } from '@/composables/usePush';
import {
    exportMethod,
    index as vaultIndex,
} from '@/routes/vault';
import {
    cancel as importCancel,
    create as importCreate,
    preview as importPreview,
    store as importStore,
} from '@/routes/vault/import';
import type { VaultSummary } from '@/types/vault';

interface PreviewRow {
    name: string;
    url: string | null;
    username: string | null;
    folder: string | null;
    has_password: boolean;
    has_totp: boolean;
    has_notes: boolean;
    favorite: boolean;
}

interface Preview {
    importId: string;
    total: number;
    errors: { row: number; reason: string }[];
    rows: PreviewRow[];
}

const props = defineProps<{
    vaults: VaultSummary[];
    preview: Preview | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Vault', href: vaultIndex() },
            { title: 'Import & export', href: importCreate() },
        ],
    },
});

const push = usePush();

const uploadForm = useForm<{ csv: string; file: File | null }>({
    csv: '',
    file: null,
});

const confirmForm = useForm({
    import_id: props.preview?.importId ?? '',
    vault_id: props.vaults[0]?.id ?? null,
});

const fileInput = ref<HTMLInputElement | null>(null);

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    uploadForm.file = target.files?.[0] ?? null;
};

const submitPreview = () => {
    uploadForm.submit(importPreview(), { preserveScroll: true });
};

const confirmImport = () => {
    confirmForm.import_id = props.preview?.importId ?? '';
    confirmForm.submit(importStore());
};

const cancelImport = () => {
    router.delete(importCancel.url(), { preserveScroll: true });
};
</script>

<template>
    <Head title="Import & export" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-8 p-4">
        <!-- ============ Import ============ -->
        <section v-if="!preview" class="space-y-4">
            <Heading
                variant="small"
                title="Import from LastPass"
                description="Export your LastPass vault as CSV (Account → Fix a problem yourself → Export), then paste it below or upload the file."
            />

            <form class="space-y-4" @submit.prevent="submitPreview">
                <div class="grid gap-2">
                    <Label for="import-csv">Paste CSV</Label>
                    <textarea
                        id="import-csv"
                        v-model="uploadForm.csv"
                        rows="8"
                        class="rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs"
                        placeholder="url,username,password,totp,extra,name,grouping,fav&#10;https://example.com,me@example.com,hunter2,,,Example,Banking,0"
                        autocapitalize="none"
                        autocomplete="off"
                        spellcheck="false"
                    ></textarea>
                </div>

                <div class="grid gap-2">
                    <Label for="import-file">…or choose the CSV file</Label>
                    <input
                        id="import-file"
                        ref="fileInput"
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm"
                        @change="onFileChange"
                    />
                </div>

                <InputError :message="uploadForm.errors.csv || uploadForm.errors.file" />

                <Button type="submit" :disabled="uploadForm.processing">
                    <FileUp class="size-4" />
                    Preview import
                </Button>
            </form>
        </section>

        <!-- ============ Preview ============ -->
        <section v-else class="space-y-4">
            <Heading
                variant="small"
                title="Review your import"
                :description="`${preview.total} items parsed. Passwords stay hidden until imported.`"
            />

            <div
                v-if="preview.errors.length > 0"
                class="space-y-1 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm"
            >
                <p class="font-medium text-destructive">
                    {{ preview.errors.length }} row(s) could not be read:
                </p>
                <p
                    v-for="error in preview.errors"
                    :key="`${error.row}-${error.reason}`"
                    class="text-muted-foreground"
                >
                    Row {{ error.row }}: {{ error.reason }}
                </p>
            </div>

            <div class="overflow-x-auto rounded-lg border border-input">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-input text-left text-muted-foreground">
                            <th class="px-3 py-2 font-medium">Name</th>
                            <th class="px-3 py-2 font-medium">Username</th>
                            <th class="px-3 py-2 font-medium">Folder</th>
                            <th class="px-3 py-2 font-medium">Has</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, index) in preview.rows"
                            :key="index"
                            class="border-b border-input/50 last:border-0"
                        >
                            <td class="max-w-40 truncate px-3 py-2">
                                <span class="flex items-center gap-1">
                                    <Star
                                        v-if="row.favorite"
                                        class="size-3 shrink-0 fill-amber-400 text-amber-400"
                                    />
                                    {{ row.name }}
                                </span>
                            </td>
                            <td class="max-w-40 truncate px-3 py-2 text-muted-foreground">
                                {{ row.username ?? '—' }}
                            </td>
                            <td class="max-w-32 truncate px-3 py-2 text-muted-foreground">
                                {{ row.folder ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">
                                {{ [row.has_password ? 'password' : null, row.has_totp ? '2FA' : null, row.has_notes ? 'notes' : null].filter(Boolean).join(', ') || '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="preview.total > preview.rows.length"
                    class="border-t border-input px-3 py-2 text-xs text-muted-foreground"
                >
                    …and {{ preview.total - preview.rows.length }} more.
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="confirmImport">
                <div class="grid gap-2">
                    <Label for="import-vault">Import into</Label>
                    <select
                        id="import-vault"
                        v-model="confirmForm.vault_id"
                        class="h-9 w-full max-w-xs rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option
                            v-for="vault in vaults"
                            :key="vault.id"
                            :value="vault.id"
                        >
                            {{ vault.name }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Items identical to ones already in this vault are skipped
                        automatically, so re-importing is safe.
                    </p>
                </div>

                <InputError :message="confirmForm.errors.import_id || confirmForm.errors.vault_id" />

                <div class="flex gap-2">
                    <Button type="submit" :disabled="confirmForm.processing">
                        Import {{ preview.total }} items
                    </Button>
                    <Button type="button" variant="outline" @click="cancelImport">
                        Start over
                    </Button>
                </div>
            </form>
        </section>

        <!-- ============ Export ============ -->
        <section class="space-y-3 border-t border-input pt-6">
            <Heading
                variant="small"
                title="Export"
                description="Download everything as a LastPass-format CSV backup. You'll confirm your password first. The file is unencrypted — store it somewhere safe and delete it after use."
            />
            <Button as-child variant="outline">
                <a :href="exportMethod.url()">
                    <Download class="size-4" />
                    Download CSV backup
                </a>
            </Button>
        </section>

        <!-- ============ Notifications ============ -->
        <section
            v-if="push.isSupported.value"
            class="space-y-3 border-t border-input pt-6"
        >
            <Heading
                variant="small"
                title="Notifications"
                description="Get a push notification on this device when someone changes an item in a shared vault."
            />
            <Button
                variant="outline"
                :disabled="push.isBusy.value"
                @click="push.isSubscribed.value ? push.unsubscribe() : push.subscribe()"
            >
                <BellOff v-if="push.isSubscribed.value" class="size-4" />
                <Bell v-else class="size-4" />
                {{ push.isSubscribed.value ? 'Disable on this device' : 'Enable on this device' }}
            </Button>
            <p v-if="push.error.value" class="text-sm text-destructive">
                {{ push.error.value }}
            </p>
        </section>
    </div>
</template>
