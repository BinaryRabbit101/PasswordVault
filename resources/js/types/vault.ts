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
    has_totp: boolean;
    has_notes: boolean;
}

export interface ItemCustomField {
    id?: number;
    label: string;
    type: 'text' | 'password' | 'url' | 'note' | 'totp' | 'email';
    value: string | null;
    is_secret: boolean;
}

export interface ItemSecrets {
    password: string | null;
    totp_secret: string | null;
    notes: string | null;
    fields: ItemCustomField[];
}
