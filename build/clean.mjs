/**
 * Remove build artefacts.
 *
 * Only touches generated output (ARCHITECTURE §1: `assets/css/` and the bundled
 * `assets/js/main.js` are artefacts, never hand-edited). Authored sources —
 * `assets/scss/`, `assets/js/src/`, images, fonts — are never deleted.
 */

import { rm } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

/** Artefacts, relative to the repo root. */
const artefacts = [
	'theme/assets/css',
	'theme/assets/js/vendor',
	'theme/assets/js/main.js'
];

for (const artefact of artefacts) {
	await rm(resolve(root, artefact), { recursive: true, force: true });
	process.stdout.write(`  removed  ${artefact}\n`);
}

process.stdout.write('Clean. Run `npm run build` to regenerate.\n');
