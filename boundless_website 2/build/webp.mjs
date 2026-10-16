/**
 * Generate WebP siblings for every raster image in the theme.
 *
 * PLAN §7: all imagery is self-hosted and run through this pipeline. The
 * original JPEG/PNG stays on disk as the fallback; the `.webp` sibling sits
 * beside it with the same basename, which is what the Phase 7 `<picture>` /
 * `srcset` work keys off.
 *
 * Idempotent: a `.webp` that is newer than its source is left alone, so this is
 * safe to run on every build. Pass `--force` to regenerate everything.
 *
 * Also reports any image that is not yet accounted for in
 * `theme/assets/images/CREDITS.md` — PLAN §7 requires source, licence and
 * consent status for every final asset, and the Phase 8 go-live gate checks it.
 */

import { readFile, readdir, stat } from 'node:fs/promises';
import { basename, dirname, extname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const imagesDir = resolve(root, 'theme/assets/images');
const creditsFile = resolve(imagesDir, 'CREDITS.md');
const force = process.argv.includes('--force');

/** Raster formats worth converting. SVG is already optimal and is skipped. */
const convertible = new Set(['.jpg', '.jpeg', '.png']);

/** Quality: high enough for hero photography, low enough to beat the JPEG. */
const quality = 82;

let sharp;

try {
	({ default: sharp } = await import('sharp'));
} catch {
	process.stderr.write(
		'sharp is not installed. Run `npm install` (it is a devDependency).\n' +
			'If the platform has no prebuilt binary, install it separately — the rest\n' +
			'of the build does not depend on sharp.\n'
	);
	process.exit(1);
}

/**
 * Recursively collect files under a directory.
 *
 * @param {string} dir Absolute directory path.
 * @returns {Promise<string[]>} Absolute file paths.
 */
async function walk(dir) {
	let entries;

	try {
		entries = await readdir(dir, { withFileTypes: true });
	} catch (error) {
		if (error.code === 'ENOENT') {
			return [];
		}
		throw error;
	}

	const found = await Promise.all(
		entries.map(async (entry) => {
			const path = join(dir, entry.name);
			return entry.isDirectory() ? walk(path) : [path];
		})
	);

	return found.flat();
}

/**
 * Modification time, or 0 when the file does not exist.
 *
 * @param {string} path Absolute file path.
 * @returns {Promise<number>} Epoch milliseconds.
 */
async function mtime(path) {
	try {
		return (await stat(path)).mtimeMs;
	} catch {
		return 0;
	}
}

const files = await walk(imagesDir);
const sources = files.filter((file) => convertible.has(extname(file).toLowerCase()));

if (sources.length === 0) {
	process.stdout.write('No raster images to convert yet.\n');
}

let converted = 0;
let skipped = 0;

for (const source of sources) {
	const target = source.replace(/\.(jpe?g|png)$/i, '.webp');

	if (!force && (await mtime(target)) >= (await mtime(source))) {
		skipped += 1;
		continue;
	}

	await sharp(source).webp({ quality }).toFile(target);
	converted += 1;
	process.stdout.write(`  webp  ${relative(root, target)}\n`);
}

process.stdout.write(`WebP: ${converted} written, ${skipped} up to date.\n`);

// --- Licence / consent accounting (PLAN §7) ---------------------------------

let credits = '';

try {
	credits = await readFile(creditsFile, 'utf8');
} catch {
	process.stderr.write(
		`\nWARNING: ${relative(root, creditsFile)} is missing. Every shipped image needs\n` +
			'a source, licence and consent record before launch.\n'
	);
}

const undocumented = files
	.filter((file) => !/\.(webp|md)$/i.test(file) && basename(file) !== '.gitkeep')
	.filter((file) => !credits.includes(basename(file)));

if (undocumented.length > 0) {
	process.stderr.write(
		`\nWARNING: ${undocumented.length} image(s) not listed in CREDITS.md:\n  ` +
			undocumented.map((file) => relative(root, file)).join('\n  ') +
			'\n\nPlaceholders may stay unlisted during the build. The Phase 8 go-live gate\n' +
			'requires a record for every final asset and zero placeholders remaining.\n'
	);
}
