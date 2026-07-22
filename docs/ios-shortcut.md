# iOS Shortcut: password lookup from any login page

iOS only allows custom keyboards and AutoFill providers from native Xcode-built
apps, so this Shortcut is the practical stand-in: from a login page in Safari,
two taps put the right password on your clipboard.

## How it works

1. You're on a login page in Safari → tap **Share → Vault Lookup** (or trigger
   via Back Tap / Action Button).
2. The Shortcut sends the page URL to `GET /api/lookup` over Tailscale,
   authenticated with your personal device token.
3. The API matches items by URL host and returns name, username, current TOTP
   code, and password.
4. The Shortcut shows a menu of matches → copies the password (or username /
   TOTP) to the clipboard → tap the password field and Paste.

## One-time setup

### 1. Mint a device token (on the server)

```bash
php artisan vault:token you@example.com
# rotate (revokes the old token, e.g. if a phone is lost):
php artisan vault:token you@example.com --rotate
```

Each person gets their own token; a token only sees vaults that user is a
member of.

### 2. Build the Shortcut (on the iPhone)

Open **Shortcuts → + → New Shortcut**, add these actions in order:

1. **Receive input from Share Sheet** — shortcut settings (ⓘ) → enable
   *Show in Share Sheet*, accept **URLs**. (Add a *Get URLs from Input* action
   first if you also want to accept text.)
2. **URL** — `https://<your-tailnet-host>/api/lookup?url=[Shortcut Input]`
   (insert the magic variable for Shortcut Input into the query string).
3. **Get Contents of URL** — Method: `GET`; Headers:
   `X-Device-Token` = *your token*.
4. **Get Dictionary Value** — key `matches`.
5. **Repeat with Each** *(optional when you expect a single match)* →
   **Choose from List** — pick the match by `name`.
6. **Get Dictionary Value** — key `password` from the chosen item.
7. **Copy to Clipboard**.
8. *(Optional)* **Show Notification** — "Password copied — paste it now."

Name it **Vault Lookup**. To trigger without the share sheet:
**Settings → Accessibility → Touch → Back Tap → Double Tap → Vault Lookup**,
or assign it to the Action Button.

Variant: duplicate the shortcut and change step 6's key to `username` or
`totp` for copy-username / copy-2FA-code versions, or add a **Choose from
Menu** step offering all three.

## Autofill in place — no clipboard (recommended)

Copying is one value at a time, so a full login is three copy → switch → paste
round-trips. Instead, run a small script **inside the login page** that fetches
the credential and fills the fields directly — one tap, no app switching, and
it fills the TOTP on the 2FA step too.

It talks to the same `/api/lookup` endpoint but with a **separate fill token**
that is *Origin-scoped*: it only ever returns the credential for the site the
request actually came from (the browser sets `Origin`; page JS cannot forge
it), so even if the token leaks it can't be used to dump other logins.

### 1. Mint a fill token

```bash
php artisan vault:token you@example.com --fill
# rotate it (it lives inside web pages, so rotate freely):
php artisan vault:token you@example.com --fill --rotate
```

### 2. The filler script

Paste your tailnet host and fill token into the top two lines. This same script
works both as a Safari bookmarklet and inside the Shortcuts *Run JavaScript on
Web Page* action.

```js
(async () => {
  const VAULT = 'https://your-tailnet-host';   // no trailing slash
  const FILL_TOKEN = 'paste-your-fill-token';

  const report = (msg) => {
    if (typeof completion !== 'undefined') completion(msg); // Shortcuts action
    else alert('Vault: ' + msg);                            // bookmarklet
  };

  const visible = el => el.offsetParent !== null && !el.disabled && !el.readOnly && el.type !== 'hidden';
  const hay = el => [el.name, el.id, el.getAttribute('autocomplete'), el.getAttribute('placeholder'), el.getAttribute('aria-label')].join(' ').toLowerCase();

  // Framework-safe write: native setter + input/change so React/Vue notice it.
  const setValue = (el, value) => {
    const proto = el instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    el.dispatchEvent(new Event('blur', { bubbles: true }));
  };

  const inputs = [...document.querySelectorAll('input, textarea')].filter(visible);

  // Score every visible field; a missing type=password just drops one signal.
  const scorePassword = el => { let s = 0, a = hay(el); if (el.type === 'password') s += 100; if (/current-password/.test(a)) s += 100; if (/pass|pwd|passwd/.test(a)) s += 40; if (/confirm|retype|again|new/.test(a)) s -= 60; return s; };
  const scoreUsername = el => { let s = 0, a = hay(el); if (/\busername\b/.test(a)) s += 100; if (el.type === 'email' || /\bemail\b/.test(a)) s += 80; if (/user|login|account|phone|mobile/.test(a)) s += 40; if (el.type === 'text') s += 10; return s; };
  const scoreOtp = el => { let s = 0, a = hay(el); if (/one-time-code/.test(a)) s += 100; if (/otp|passcode|verif|token|2fa|mfa|\bcode\b/.test(a)) s += 60; if (el.inputMode === 'numeric') s += 20; if (el.maxLength > 0 && el.maxLength <= 8) s += 20; return s; };
  const best = (score, min = 40) => { let b = null, bs = min; for (const el of inputs) { const s = score(el); if (s > bs) { bs = s; b = el; } } return b; };

  let data;
  try {
    const r = await fetch(`${VAULT}/api/lookup?token=${encodeURIComponent(FILL_TOKEN)}`, { cache: 'no-store' });
    data = await r.json();
  } catch (e) { return report('could not reach the vault (Tailscale on? site CSP blocking?)'); }

  const m = data.matches && data.matches[0];
  if (!m) return report('no saved login for this site');

  const filled = [];
  const user = best(scoreUsername), pwd = best(scorePassword);
  if (user && m.username) { setValue(user, m.username); filled.push('username'); }
  if (pwd && m.password) { setValue(pwd, m.password); filled.push('password'); }

  if (m.totp) {
    // Segmented 6-box OTP inputs, or a single code field.
    const boxes = inputs.filter(el => el.maxLength === 1 && scoreOtp(el) > 0);
    if (boxes.length >= m.totp.length) { [...m.totp].forEach((d, i) => setValue(boxes[i], d)); filled.push('code'); }
    else { const otp = best(scoreOtp); if (otp) { setValue(otp, m.totp); filled.push('code'); } }
  }

  report(filled.length ? 'filled ' + filled.join(', ') : 'found login but no matching fields here');
})();
```

Multi-step logins (username page, then password page) fill what's present on
each step — just tap the filler again on the next screen. Same for the 2FA
screen: tap again and it fills the current code.

### 3a. As a bookmarklet

Make a Safari bookmark whose URL is `javascript:` followed by the script above
on one line (minified). Save it to **Favorites**. On a login page, open the
address bar, type the bookmark's name, and tap it — the form fills.

### 3b. As a Shortcut (Share Sheet / Back Tap / Action Button)

Duplicate **Vault Lookup**, delete everything after *Receive input*, and add a
single **Run JavaScript on Web Page** action containing the script. Name it
**Vault Fill**. Trigger it from **Share → Vault Fill** on the login page.

### The vault Autofill button (pick the exact login)

Each item in the vault list has an **Autofill** (wand) button. Tapping it opens
the item's website in Safari *and* stages that exact credential on the server
for ~60s. When you then run the filler on the opened page, the vault hands it
the staged item directly instead of guessing by Origin — so multi-account
sites and entries whose stored URL doesn't quite match still fill the right
login. The staged item is only ever released if the page's domain matches the
item's, and it is consumed on first use, so it never widens what the filler can
read. If nothing is staged (or it has expired), the filler falls back to the
normal Origin match.

Note this still needs the one tap on the destination page to run the filler —
a web page cannot inject script into another site's page, so the vault button
can open and stage, but only a bookmarklet / Shortcut / native extension
running *in* that page can do the fill.

### When it can't fill

Hardened sites (some banks, Google) send a strict `connect-src` Content
Security Policy that blocks the page's fetch to your vault, and cross-origin
iframe / closed shadow-DOM login widgets can't be reached. For those, fall back
to the copy-paste **Vault Lookup** shortcut above.

## Security notes

- The endpoint is reachable **only** from the Tailscale/LAN CIDRs
  (`RequireLocalNetwork` middleware) and is rate-limited.
- The token is a bearer credential: anyone on your tailnet with it can read
  your vault's passwords. Keep it inside the Shortcut only, and rotate it with
  `--rotate` if a device is lost.
- The **fill token** is embedded in web pages, so treat it as more exposed than
  the device token. It is Origin-scoped — a page can only pull the credential
  for its own real Origin, which page JS cannot forge — but a genuinely
  malicious page you run the filler on could still capture the token itself.
  Only run the filler on real login pages, and rotate with `--fill --rotate`.
  (The native Credential Provider Extension below is the only path with zero
  in-page token exposure.)
- Set `VAULT_API_RETURNS_PASSWORDS=false` in `.env` to strip passwords from
  API responses entirely (the Shortcut then only surfaces username + TOTP, and
  you copy passwords from the PWA instead).
- Passwords copied to the clipboard are visible to any app you paste into;
  iOS shows a paste banner when an app reads the clipboard.

## Future upgrade path (needs a Mac)

A small Swift app with a **Credential Provider Extension**
(`ASCredentialProviderViewController`) plugs into the real iOS AutoFill bar
above the keyboard — nicer than both this Shortcut and a custom keyboard.
The `/api/lookup` endpoint already provides everything such an app would need.
