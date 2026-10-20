<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { usePush } from '@/composables/usePush';

/**
 * Estate standard §6: the notification grant sits at the top of the page.
 * Shows only when it applies on this device — signed in, push available (or
 * an iPhone that needs installing first) and not subscribed yet.
 */

const SNOOZE_KEY = 'notification-banner-snoozed-until';
const SNOOZE_MS = 7 * 24 * 60 * 60 * 1000;

function isSnoozed(): boolean {
    try {
        const until = Number(window.localStorage.getItem(SNOOZE_KEY));

        return Number.isFinite(until) && until > Date.now();
    } catch {
        return false;
    }
}

const page = usePage();
const push = usePush();
const snoozed = ref(isSnoozed());

// iPhone Safari outside a home-screen app has no push at all.
const ua = navigator.userAgent;
const isIos =
    /iPad|iPhone|iPod/.test(ua) ||
    (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
const isStandalone =
    window.matchMedia('(display-mode: standalone)').matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true;
const needsInstall = isIos && !isStandalone && !('PushManager' in window);

const mode = computed<'install' | 'blocked' | 'ask' | null>(() => {
    if (!page.props.auth?.user || snoozed.value) {
        return null;
    }

    if (needsInstall) {
        return 'install';
    }

    if (
        !push.isSupported.value ||
        !push.checked.value ||
        push.isSubscribed.value
    ) {
        return null;
    }

    return push.permission.value === 'denied' ? 'blocked' : 'ask';
});

function notNow() {
    snoozed.value = true;

    try {
        window.localStorage.setItem(SNOOZE_KEY, String(Date.now() + SNOOZE_MS));
    } catch {
        // Storage blocked: it stays hidden until the next page load.
    }
}
</script>

<template>
    <div
        v-if="mode"
        data-test="notification-banner"
        :data-mode="mode"
        class="mx-4 mt-4 flex flex-col gap-3 rounded-lg border bg-card px-4 py-3 text-base text-card-foreground sm:flex-row sm:items-center"
    >
        <div class="flex flex-1 items-start gap-3">
            <Bell class="mt-0.5 size-5 shrink-0 text-muted-foreground" />
            <div class="space-y-1">
                <p v-if="mode === 'ask'">
                    Get a notification on this device when someone changes an
                    item in a shared vault.
                </p>
                <p v-else-if="mode === 'install'">
                    To get notifications on iPhone: Share → Add to Home Screen,
                    then open the app from there.
                </p>
                <p v-else>
                    Notifications are blocked on this device. To allow them, go
                    to iPhone Settings → Notifications → {{ page.props.name }},
                    or open this browser's site settings for this page.
                </p>
                <p
                    v-if="push.error.value"
                    class="text-destructive"
                    data-test="notification-banner-error"
                >
                    {{ push.error.value }}
                </p>
            </div>
        </div>
        <div class="flex shrink-0 gap-2">
            <Button
                v-if="mode === 'ask'"
                data-test="notification-banner-enable"
                class="h-11 text-base"
                :disabled="push.isBusy.value"
                @click="push.subscribe()"
            >
                Turn on notifications
            </Button>
            <Button
                variant="ghost"
                data-test="notification-banner-dismiss"
                class="h-11 text-base"
                @click="notNow"
            >
                Not now
            </Button>
        </div>
    </div>
</template>
