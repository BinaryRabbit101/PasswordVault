<?php

namespace App\Support;

use App\Models\ItemField;

/**
 * Every part of an item except its name, folder and favorite is a field, so
 * autofill, the list subtitle, export and the TOTP lookup all ask this class
 * which field plays which role.
 *
 * A field's `autofill` is null for "auto": the first filled email/text field
 * is the username and the first filled password field is the password. An
 * explicit `username`/`password` wins over auto; `none` opts a field out.
 *
 * Works on plain rows (a request's `fields` array) as well as models, so an
 * item's derived columns can be worked out before it is saved.
 */
final class FieldRoles
{
    /**
     * @param  iterable<ItemField|array<string, mixed>>  $fields
     */
    public static function username(iterable $fields): ?string
    {
        return self::role($fields, ItemField::AUTOFILL_USERNAME, ['email', 'text']);
    }

    /**
     * @param  iterable<ItemField|array<string, mixed>>  $fields
     */
    public static function password(iterable $fields): ?string
    {
        return self::role($fields, ItemField::AUTOFILL_PASSWORD, ['password']);
    }

    /**
     * @param  iterable<ItemField|array<string, mixed>>  $fields
     */
    public static function totp(iterable $fields): ?string
    {
        return self::ofType($fields, 'totp')[0] ?? null;
    }

    /**
     * @param  iterable<ItemField|array<string, mixed>>  $fields
     * @return list<string>
     */
    public static function urls(iterable $fields): array
    {
        return self::ofType($fields, 'url');
    }

    /**
     * @param  iterable<ItemField|array<string, mixed>>  $fields
     * @param  list<string>  $autoTypes
     */
    private static function role(iterable $fields, string $role, array $autoTypes): ?string
    {
        $rows = self::rows($fields);

        foreach ($rows as $row) {
            if ($row['autofill'] === $role && $row['value'] !== null) {
                return $row['value'];
            }
        }

        foreach ($rows as $row) {
            if ($row['autofill'] === null && in_array($row['type'], $autoTypes, true) && $row['value'] !== null) {
                return $row['value'];
            }
        }

        return null;
    }

    /**
     * @param  iterable<ItemField|array<string, mixed>>  $fields
     * @return list<string>
     */
    private static function ofType(iterable $fields, string $type): array
    {
        $values = [];

        foreach (self::rows($fields) as $row) {
            if ($row['type'] === $type && $row['value'] !== null) {
                $values[] = $row['value'];
            }
        }

        return $values;
    }

    /**
     * @param  iterable<ItemField|array<string, mixed>>  $fields
     * @return list<array{type: string, value: string|null, autofill: string|null}>
     */
    private static function rows(iterable $fields): array
    {
        $rows = [];

        foreach ($fields as $field) {
            $row = $field instanceof ItemField ? $field->only(['type', 'value', 'autofill']) : $field;
            $value = $row['value'] ?? null;

            $rows[] = [
                'type' => (string) ($row['type'] ?? 'text'),
                'value' => is_string($value) && $value !== '' ? $value : null,
                'autofill' => $row['autofill'] ?? null,
            ];
        }

        return $rows;
    }
}
