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

## Security notes

- The endpoint is reachable **only** from the Tailscale/LAN CIDRs
  (`RequireLocalNetwork` middleware) and is rate-limited.
- The token is a bearer credential: anyone on your tailnet with it can read
  your vault's passwords. Keep it inside the Shortcut only, and rotate it with
  `--rotate` if a device is lost.
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
