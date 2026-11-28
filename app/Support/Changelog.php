<?php

namespace App\Support;

/** Release notes shown on the public "What's new" page, in the app and in update packages (resources/data/changelog.json). */
class Changelog
{
    public const TYPES = ['new' => 'New', 'improved' => 'Improved', 'fixed' => 'Fixed'];

    /** @return array<int, array{version: string, date: string, title: array, changes: array}> newest first */
    public static function releases(): array
    {
        static $releases;

        return $releases ??= json_decode((string) @file_get_contents(resource_path('data/changelog.json')), true)['releases'] ?? [];
    }

    public static function latest(): ?array
    {
        return self::releases()[0] ?? null;
    }

    /** Text in the current language (Hindi falls back to English). */
    public static function text(array $item): string
    {
        return app()->getLocale() === 'hi' && ! empty($item['hi']) ? $item['hi'] : ($item['en'] ?? '');
    }
}
