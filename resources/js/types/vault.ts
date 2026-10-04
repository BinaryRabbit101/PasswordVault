export interface VaultSummary {
    id: number;
    name: string;
    type: 'personal' | 'shared';
}

export interface VaultItem {
    id: number;
    vault_id: number;
    name: string;
    url: string | null;
    username: string | null;
    folder: string | null;
    favorite: boolean;
    /** The name of the field quick-copy treats as the username, if any. */
    username_label: string | null;
    /** The name of the field quick-copy treats as the password, if any. */
    password_label: string | null;
}

export type FieldType = 'text' | 'password' | 'email' | 'url' | 'totp' | 'note';

/** null = auto: first text/email is the username, first password the password. */
export type FieldAutofill = 'username' | 'password' | 'none' | null;

export interface ItemField {
    id?: number;
    label: string;
    type: FieldType;
    autofill: FieldAutofill;
    value: string | null;
}

export interface ItemSecrets {
    /** Whichever field autofill would use as the password. */
    password: string | null;
    fields: ItemField[];
}

export interface FieldHistoryEntry {
    id: number;
    value: string;
    created_at: string;
}
