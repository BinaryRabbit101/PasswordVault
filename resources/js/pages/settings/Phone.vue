<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Check, Copy, RefreshCw, Smartphone, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import PhoneController from '@/actions/App/Http/Controllers/Settings/PhoneController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/phone';

type PhoneKey = {
    kind: 'device_token' | 'fill_token';
    label: string;
    token: string | null;
    header: string | null;
};

type Props = {
    phone: {
        lookup_endpoint: string;
        keys: PhoneKey[];
    };
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Phone',
                href: edit(),
            },
        ],
    },
});

// Which copy button is showing its tick, if any.
const copied = ref<string | null>(null);

async function copy(field: string, value: string | null): Promise<void> {
    if (!value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        copied.value = field;
        window.setTimeout(() => {
            // Only clear our own tick, so a second copy inside the two seconds
            // isn't cut short by the first one's timer.
            if (copied.value === field) {
                copied.value = null;
            }
        }, 2000);
    } catch {
        copied.value = null;
    }
}

function confirmRotate(key: PhoneKey): boolean {
    if (!key.token) {
        return true;
    }

    return window.confirm(
        `Generate a new ${key.label.toLowerCase()}? The current one stops working immediately.`,
    );
}

function confirmRevoke(key: PhoneKey): boolean {
    return window.confirm(
        `Revoke the ${key.label.toLowerCase()}? Whatever carries it stops working until you generate a new one.`,
    );
}

const blurb: Record<PhoneKey['kind'], string> = {
    device_token:
        'For the Vault Lookup Shortcut: Get Contents of URL with this key as the header. It can search any of your vaults by URL, so treat it like a password.',
    fill_token:
        'For the in-page filler script (FILL_TOKEN in the bookmarklet). It only ever answers for the page it runs on, so a leak exposes one login, not the vault — but roll it anyway.',
};
</script>

<template>
    <Head title="Phone" />

    <h1 class="sr-only">Phone settings</h1>

    <!--
        Two keys, two panels: the Shortcut and the in-page filler have
        different exposure, so each key rolls on its own (see
        docs/ios-shortcut.md).
    -->
    <div class="space-y-10" data-test="phone-key-panel">
        <Heading
            variant="small"
            title="Your phone's keys"
            description="Private keys for the Vault Lookup shortcut and the in-page filler"
        />

        <div class="grid gap-2">
            <Label for="lookup-endpoint">Lookup endpoint</Label>
            <div class="flex flex-wrap items-center gap-2">
                <code
                    id="lookup-endpoint"
                    class="min-w-0 flex-1 rounded-md border bg-muted px-3 py-2 font-mono text-xs break-all"
                    data-test="lookup-endpoint"
                >
                    GET {{ props.phone.lookup_endpoint }}?url=…
                </code>
                <Button
                    variant="outline"
                    size="icon"
                    type="button"
                    aria-label="Copy lookup endpoint"
                    @click="copy('endpoint', props.phone.lookup_endpoint)"
                >
                    <Check v-if="copied === 'endpoint'" />
                    <Copy v-else />
                </Button>
            </div>
        </div>

        <section
            v-for="key in props.phone.keys"
            :key="key.kind"
            class="space-y-4"
            :data-test="`key-${key.kind}`"
        >
            <Heading
                variant="small"
                :title="key.label"
                :description="blurb[key.kind]"
            />

            <div v-if="key.token" class="grid gap-2">
                <Label :for="`token-${key.kind}`">
                    {{ key.header ? `Header ${key.header}` : 'The key' }}
                </Label>
                <div class="flex flex-wrap items-center gap-2">
                    <code
                        :id="`token-${key.kind}`"
                        class="min-w-0 flex-1 rounded-md border bg-muted px-3 py-2 font-mono text-xs break-all"
                        :data-test="`token-${key.kind}`"
                    >
                        {{ key.token }}
                    </code>
                    <Button
                        variant="outline"
                        size="icon"
                        type="button"
                        :aria-label="`Copy ${key.label}`"
                        :data-test="`copy-${key.kind}`"
                        @click="copy(key.kind, key.token)"
                    >
                        <Check v-if="copied === key.kind" />
                        <Copy v-else />
                    </Button>
                </div>
            </div>

            <p
                v-else
                class="text-sm text-muted-foreground"
                :data-test="`no-${key.kind}`"
            >
                No {{ key.label.toLowerCase() }} yet.
            </p>

            <div class="flex flex-wrap gap-2">
                <Form
                    v-bind="PhoneController.regenerate.form()"
                    :options="{ preserveScroll: true }"
                    :on-before="() => confirmRotate(key)"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="kind" :value="key.kind" />
                    <Button
                        type="submit"
                        :variant="key.token ? 'outline' : 'default'"
                        :disabled="processing"
                        :data-test="`regenerate-${key.kind}`"
                    >
                        <RefreshCw v-if="key.token" />
                        <Smartphone v-else />
                        {{
                            key.token
                                ? 'Generate a new key'
                                : `Generate ${key.label.toLowerCase()}`
                        }}
                    </Button>
                </Form>

                <Form
                    v-if="key.token"
                    v-bind="PhoneController.revoke.form()"
                    :options="{ preserveScroll: true }"
                    :on-before="() => confirmRevoke(key)"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="kind" :value="key.kind" />
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                        :data-test="`revoke-${key.kind}`"
                    >
                        <Trash2 />
                        Revoke
                    </Button>
                </Form>
            </div>
        </section>
    </div>
</template>
