<?php

namespace App\Support;

class PermissionCatalog
{
    /**
     * Flattened, memoized permission list — {key, label, group}.
     *
     * @var array<int, array{key: string, label: string, group: string}>|null
     */
    private static ?array $flattened = null;

    /**
     * The catalog grouped by module — module => {label, permissions[]}.
     *
     * @return array<string, array{label: string, permissions: array<int, array{key: string, label: string}>}>
     */
    public static function grouped(): array
    {
        return config('permissions', []);
    }

    /**
     * Every permission as a flat list with its module group attached.
     *
     * @return array<int, array{key: string, label: string, group: string}>
     */
    public static function all(): array
    {
        if (self::$flattened !== null) {
            return self::$flattened;
        }

        $flattened = [];

        foreach (self::grouped() as $group => $module) {
            foreach ($module['permissions'] ?? [] as $permission) {
                $flattened[] = [
                    'key' => $permission['key'],
                    'label' => $permission['label'],
                    'group' => $group,
                ];
            }
        }

        return self::$flattened = $flattened;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    /**
     * Permission keys belonging to a single module group.
     *
     * @return array<int, string>
     */
    public static function keysForGroup(string $group): array
    {
        return array_values(array_map(
            static fn (array $permission): string => $permission['key'],
            self::grouped()[$group]['permissions'] ?? [],
        ));
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /**
     * Clear the in-process memo (used by tests that swap config).
     */
    public static function flush(): void
    {
        self::$flattened = null;
    }
}
