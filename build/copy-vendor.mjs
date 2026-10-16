/**
 * Copy vendor libraries out of node_modules into the theme.
 *
 * `inc/enqueue.php` registers vendor handles against fixed paths under
 * `theme/assets/js/vendor/` and `theme/assets/css/vendor/` (ARCHITECTURE §3).
 * Those paths are a contract: this script is what fulfils it. Nothing in the
 * theme may import from node_modules at runtime — the shipped theme folder must
 * be self-contained.
 *
 * The copied files are build artefacts. Never hand-edit them; change the
 * dependency version in package.json and re-run `npm run build:vendor`.
 */

import { createRequire } from 'node:module';
import { copyFile, mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

/**
 * Source (relative to the repo root) → destination (relative to the repo root).
 *
 * Destination names match `inc/enqueue.php` exactly, including the rename of
 * GSAP's `ScrollTrigger.min.js` to the kebab-case `scroll-trigger.min.js` the
 * `bgcf-gsap-scrolltrigger` handle points at.
 */
const files = [
	{
		from: 'node_modules/gsap/dist/gsap.min.js',
		to: 'theme/assets/js/vendor/gsap.min.js',
		pkg: 'gsap'
	},
	{
		from: 'node_modules/gsap/dist/ScrollTrigger.min.js',
		to: 'theme/assets/js/vendor/scroll-trigger.min.js',
		pkg: 'gsap'
	},
	{
		from: 'node_modules/swiper/swiper-bundle.min.js',
		to: 'theme/assets/js/vendor/swiper-bundle.min.js',
		pkg: 'swiper'
	},
	{
		from: 'node_modules/swiper/swiper-bundle.min.css',
		to: 'theme/assets/css/vendor/swiper-bundle.min.css',
		pkg: 'swiper'
	}
];

/**
 * Read the installed version of a dependency, for the provenance README.
 *
 * @param {string} name Package name.
 * @returns {string} Semver string, or 'unknown' when it cannot be read.
 */
function installedVersion(name) {
	try {
		return require(`${name}/package.json`).version;
	} catch {
		return 'unknown';
	}
}

const missing = [];

for (const file of files) {
	const from = resolve(root, file.from);
	const to = resolve(root, file.to);

	await mkdir(dirname(to), { recursive: true });

	try {
		await copyFile(from, to);
		process.stdout.write(`  copied  ${file.to}\n`);
	} catch (error) {
		if (error.code !== 'ENOENT') {
			throw error;
		}
		missing.push(file.from);
	}
}

if (missing.length > 0) {
	process.stderr.write(
		`\nMissing vendor sources:\n  ${missing.join('\n  ')}\n\n` +
			'Run `npm install` first. If a dependency changed its file layout, fix the\n' +
			'`files` map in build/copy-vendor.mjs and the matching paths in\n' +
			'theme/inc/enqueue.php together — they are one contract.\n'
	);
	process.exit(1);
}

// Provenance note next to the artefacts, so nobody has to guess where a minified
// blob in the theme came from or hand-patches it.
const versions = [...new Set(files.map((file) => file.pkg))]
	.map((pkg) => `- ${pkg} ${installedVersion(pkg)}`)
	.join('\n');

const readme = `# Vendor assets — build artefacts, do not edit

Copied from node_modules by \`npm run build:vendor\` (build/copy-vendor.mjs).
Regenerate instead of editing; bump the version in package.json to update.

Installed versions at last build:

${versions}

Consumed by \`theme/inc/enqueue.php\` via the \`bgcf-gsap\`,
\`bgcf-gsap-scrolltrigger\` and \`bgcf-swiper\` handles, which are registered
always and enqueued only on demand through \`bgcf_use_gsap()\` /
\`bgcf_use_swiper()\`.
`;

for (const dir of ['theme/assets/js/vendor', 'theme/assets/css/vendor']) {
	await writeFile(resolve(root, dir, 'README.md'), readme, 'utf8');
}

process.stdout.write('Vendor assets in place.\n');
