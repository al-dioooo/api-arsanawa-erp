<?php

use Symfony\Component\Finder\Finder;

/**
 * Modules may not import each other's Models (AGENTS.md). Two carve-outs:
 *
 * - Files inside a module's Models/ directory may reference other modules'
 *   models to declare cross-module FK relations (Bill→Partner, Sale→Currency,
 *   ...). These are read-only object graphs, not behavioural coupling.
 * - The LEGACY_BASELINE below freezes the violations that existed when this
 *   guard was introduced. The list may only SHRINK: fix a file, delete its
 *   entry. Adding an entry needs an architectural decision, not a convenience.
 */
const LEGACY_BASELINE = [
    'app/Modules/Platform/Services/InventoryProductImportProcessor.php',
];

describe('Module boundaries', function () {
    it('does not gain new cross-module model imports outside Models directories', function (): void {
        $finder = Finder::create()
            ->files()
            ->in(base_path('app/Modules'))
            ->name('*.php');

        $violations = [];

        foreach ($finder as $file) {
            $relative = 'app/Modules/'.str_replace('\\', '/', $file->getRelativePathname());

            if (! preg_match('#^app/Modules/([^/]+)/#', $relative, $matches)) {
                continue;
            }

            $ownModule = $matches[1];

            // Cross-module FK relations declared on models are sanctioned.
            if (str_starts_with($relative, "app/Modules/{$ownModule}/Models/")) {
                continue;
            }

            preg_match_all(
                '#^use App\\\\Modules\\\\([^\\\\]+)\\\\Models\\\\#m',
                $file->getContents(),
                $imports,
            );

            foreach ($imports[1] as $importedModule) {
                if ($importedModule !== $ownModule) {
                    $violations[] = $relative;
                    break;
                }
            }
        }

        sort($violations);
        $baseline = LEGACY_BASELINE;
        sort($baseline);

        $new = array_values(array_diff($violations, $baseline));
        $fixed = array_values(array_diff($baseline, $violations));

        expect($new)->toBe([], 'New cross-module model imports were added. Route the access through the owning module\'s Actions/Services instead: '.implode(', ', $new));
        expect($fixed)->toBe([], 'These files no longer violate the boundary — remove them from LEGACY_BASELINE so the ratchet keeps tightening: '.implode(', ', $fixed));
    });
});
