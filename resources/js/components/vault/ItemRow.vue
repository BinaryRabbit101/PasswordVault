<script setup lang="ts">
import { Copy, KeyRound, Star, WandSparkles } from '@lucide/vue';
import { computed } from 'vue';
import { externalHref } from '@/lib/utils';
import type { VaultItem } from '@/types/vault';

const props = defineProps<{
    item: VaultItem;
}>();

const emit = defineEmits<{
    open: [];
    copyUsername: [];
    copyPassword: [];
    autofill: [];
}>();

const AVATAR_CLASSES = [
    'bg-red-500/15 text-red-600 dark:text-red-400',
    'bg-orange-500/15 text-orange-600 dark:text-orange-400',
    'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    'bg-green-500/15 text-green-600 dark:text-green-400',
    'bg-teal-500/15 text-teal-600 dark:text-teal-400',
    'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
    'bg-purple-500/15 text-purple-600 dark:text-purple-400',
    'bg-pink-500/15 text-pink-600 dark:text-pink-400',
];

const avatarClass = computed(() => {
    let hash = 0;

    for (const char of props.item.name) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    return AVATAR_CLASSES[hash % AVATAR_CLASSES.length];
});

const ACTION_CLASS =
    'flex w-13 flex-col items-center gap-0.5 rounded-md px-0.5 py-1.5 text-muted-foreground hover:bg-background hover:text-foreground';
const CAPTION_CLASS = 'block w-full truncate text-center text-[10px] leading-3';

const initial = computed(() => props.item.name.charAt(0).toUpperCase() || '?');
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-accent"
        data-test="item-row"
        :data-item-id="item.id"
    >
        <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-3 text-left"
            data-test="item-open"
            @click="emit('open')"
        >
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-lg text-base font-semibold"
                :class="avatarClass"
            >
                {{ initial }}
            </span>
            <span class="min-w-0">
                <span class="flex items-center gap-1.5">
                    <span class="truncate font-medium" data-test="item-name">{{
                        item.name
                    }}</span>
                    <Star
                        v-if="item.favorite"
                        class="size-3.5 shrink-0 fill-amber-400 text-amber-400"
                    />
                </span>
                <span
                    v-if="item.username"
                    class="block truncate text-sm text-muted-foreground"
                    data-test="item-username"
                >
                    {{ item.username }}
                </span>
            </span>
        </button>

        <!-- Each quick button wears a caption: the copy ones are named after
             the field they copy, so a token never passes for a password. -->
        <div class="flex shrink-0 items-stretch">
            <a
                v-if="item.url"
                :href="externalHref(item.url)"
                target="_blank"
                rel="noopener noreferrer"
                :class="ACTION_CLASS"
                title="Open site & autofill"
                data-test="item-autofill"
                @click="emit('autofill')"
            >
                <WandSparkles class="size-4" />
                <span :class="CAPTION_CLASS" aria-hidden="true">Fill</span>
                <span class="sr-only">Open site and autofill</span>
            </a>
            <button
                v-if="item.username && item.username_label"
                type="button"
                :class="ACTION_CLASS"
                :title="`Copy ${item.username_label}`"
                data-test="item-copy-username"
                @click="emit('copyUsername')"
            >
                <Copy class="size-4" />
                <span :class="CAPTION_CLASS" aria-hidden="true">{{
                    item.username_label
                }}</span>
                <span class="sr-only">Copy {{ item.username_label }}</span>
            </button>
            <button
                v-if="item.password_label"
                type="button"
                :class="ACTION_CLASS"
                :title="`Copy ${item.password_label}`"
                data-test="item-copy-password"
                @click="emit('copyPassword')"
            >
                <KeyRound class="size-4" />
                <span :class="CAPTION_CLASS" aria-hidden="true">{{
                    item.password_label
                }}</span>
                <span class="sr-only">Copy {{ item.password_label }}</span>
            </button>
        </div>
    </div>
</template>
