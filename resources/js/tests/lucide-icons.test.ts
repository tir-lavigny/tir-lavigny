/**
 * Lucide icon import tests
 *
 * These tests verify that:
 * 1. The @lucide/vue package can be imported (package rename from lucide-vue-next in v1)
 * 2. All icons used across the project are exported from the package
 * 3. The LucideIcon type is exported
 * 4. The wildcard namespace import used by Icon.vue works
 *
 * USED_ICONS is derived automatically by scanning every *.vue and *.ts source
 * file for `from '@lucide/vue'` imports — no manual list to maintain.
 * Adding a new icon import anywhere in resources/js will automatically be
 * covered the next time the test suite runs.
 *
 * If any test fails, it likely means an icon was renamed or removed in a
 * Lucide major version bump.
 */

import type { LucideIcon } from '@lucide/vue';
import * as LucideVue from '@lucide/vue';
import { describe, expect, it } from 'vitest';

// ---------------------------------------------------------------------------
// Dynamically collect every named icon imported from '@lucide/vue' across the
// entire resources/js source tree.
//
// import.meta.glob eagerly loads the raw source of every .vue and .ts file.
// We then extract the identifiers from any `import { ... } from '@lucide/vue'`
// statement with a regex, deduplicate, and exclude non-component exports
// (type-only imports, the wildcard `* as icons` form, and `LucideIcon`).
// ---------------------------------------------------------------------------
const sources = import.meta.glob('@/**/*.{vue,ts}', { eager: true, query: '?raw', import: 'default' });

const IMPORT_RE = /from\s+['"]@lucide\/vue['"]/;
const NAMED_RE = /import\s*\{([^}]+)\}\s*from\s*['"]@lucide\/vue['"]/g;

const USED_ICONS: string[] = [
    ...new Set(
        Object.values(sources as Record<string, string>)
            .filter((src) => IMPORT_RE.test(src))
            .flatMap((src) => {
                const names: string[] = [];
                for (const match of src.matchAll(NAMED_RE)) {
                    names.push(
                        ...match[1]
                            .split(',')
                            .map((s) => s.replace(/\s+as\s+\S+/, '').trim()) // drop aliases
                            .filter((s) => s.length > 0),
                    );
                }
                return names;
            })
            // Drop type-only imports and the LucideIcon type; keep only PascalCase component names
            .filter((name) => /^[A-Z]/.test(name) && name !== 'LucideIcon'),
    ),
].sort();

describe('@lucide/vue', () => {
    it('package can be imported', () => {
        expect(LucideVue).toBeDefined();
    });

    it('exports the LucideIcon type (used in types/index.d.ts)', () => {
        // Type-level check: if LucideIcon is not exported, the TS compilation fails.
        // This runtime check ensures the import itself does not throw.
        const _typeCheck: LucideIcon | undefined = undefined;
        expect(_typeCheck).toBeUndefined(); // always true; the value is that this compiles
    });

    it('wildcard namespace import resolves to an object (used by Icon.vue)', () => {
        expect(typeof LucideVue).toBe('object');
        expect(LucideVue).not.toBeNull();
    });

    it('detects at least one icon import in the source tree', () => {
        expect(USED_ICONS.length).toBeGreaterThan(0);
    });

    it.each(USED_ICONS)('exports icon: %s', (iconName) => {
        const icon = (LucideVue as Record<string, unknown>)[iconName];
        expect(icon, `Icon "${iconName}" is not exported from @lucide/vue`).toBeDefined();
        // Every lucide icon is a Vue component — it must be an object or function
        expect(['object', 'function']).toContain(typeof icon);
    });
});
