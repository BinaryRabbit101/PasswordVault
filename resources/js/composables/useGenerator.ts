export interface GeneratorOptions {
    length: number;
    lowercase: boolean;
    uppercase: boolean;
    digits: boolean;
    symbols: boolean;
}

export const defaultGeneratorOptions: GeneratorOptions = {
    length: 20,
    lowercase: true,
    uppercase: true,
    digits: true,
    symbols: true,
};

const SETS = {
    lowercase: 'abcdefghijkmnopqrstuvwxyz',
    uppercase: 'ABCDEFGHJKLMNPQRSTUVWXYZ',
    digits: '23456789',
    symbols: '!@#$%^&*()-_=+[]{};:,.?',
};

export function generatePassword(options: GeneratorOptions): string {
    const pools = (
        ['lowercase', 'uppercase', 'digits', 'symbols'] as const
    ).filter((key) => options[key]);

    if (pools.length === 0 || options.length < pools.length) {
        return '';
    }

    const alphabet = pools.map((key) => SETS[key]).join('');
    const pick = (chars: string) => {
        // Rejection sampling keeps the distribution uniform.
        const max = Math.floor(0xffffffff / chars.length) * chars.length;
        const buffer = new Uint32Array(1);
        let value: number;
        do {
            crypto.getRandomValues(buffer);
            value = buffer[0];
        } while (value >= max);

        return chars[value % chars.length];
    };

    // Guarantee at least one character from each selected set, then fill.
    const required = pools.map((key) => pick(SETS[key]));
    const rest = Array.from(
        { length: options.length - required.length },
        () => pick(alphabet),
    );

    const password = [...required, ...rest];

    // Fisher-Yates shuffle with crypto randomness.
    for (let i = password.length - 1; i > 0; i--) {
        const buffer = new Uint32Array(1);
        crypto.getRandomValues(buffer);
        const j = buffer[0] % (i + 1);
        [password[i], password[j]] = [password[j], password[i]];
    }

    return password.join('');
}
