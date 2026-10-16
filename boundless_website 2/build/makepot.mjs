/**
 * Generates `theme/languages/bgcf.pot` from the theme's PHP sources.
 *
 * WHY A HOME-GROWN EXTRACTOR. The canonical tool is `wp i18n make-pot`, which
 * needs WP-CLI and therefore PHP. This project's toolchain is Node-only, so a
 * translator would otherwise have nothing to work from at all — and "no
 * catalogue" is the state that makes a multilingual build (Polylang is in the
 * stack) impossible to start.
 *
 * It is a lexer, not a parser, and it is honest about that: it finds the
 * gettext calls, their text domain, their translator comments and their source
 * references. If WP-CLI becomes available, replace this with `make-pot` and
 * delete the file — the output format is deliberately identical so nothing
 * downstream notices.
 *
 * Run: `npm run build:pot`
 */

import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const themeDir = join(root, 'theme');
const outFile = join(themeDir, 'languages', 'bgcf.pot');

const DOMAIN = 'bgcf';

/**
 * The gettext functions WordPress ships, and where their arguments sit.
 *
 * `singular` / `plural` / `context` are 1-based argument positions; `domain` is
 * the position of the text domain, which is what lets us ignore strings
 * belonging to another plugin's domain.
 */
const FUNCTIONS = {
	__: { singular: 1, domain: 2 },
	_e: { singular: 1, domain: 2 },
	esc_html__: { singular: 1, domain: 2 },
	esc_html_e: { singular: 1, domain: 2 },
	esc_attr__: { singular: 1, domain: 2 },
	esc_attr_e: { singular: 1, domain: 2 },
	_x: { singular: 1, context: 2, domain: 3 },
	_ex: { singular: 1, context: 2, domain: 3 },
	esc_html_x: { singular: 1, context: 2, domain: 3 },
	esc_attr_x: { singular: 1, context: 2, domain: 3 },
	_n: { singular: 1, plural: 2, domain: 4 },
	_nx: { singular: 1, plural: 2, context: 4, domain: 5 }
};

/**
 * Walk a directory for PHP files, skipping vendor trees.
 *
 * @param {string} dir Directory to walk.
 * @returns {string[]} Absolute file paths.
 */
function phpFiles(dir) {
	const found = [];

	for (const entry of readdirSync(dir)) {
		if (entry === 'node_modules' || entry === 'vendor' || entry.startsWith('.')) {
			continue;
		}

		const full = join(dir, entry);

		if (statSync(full).isDirectory()) {
			found.push(...phpFiles(full));
		} else if (entry.endsWith('.php')) {
			found.push(full);
		}
	}

	return found;
}

/**
 * Split a PHP argument list into top-level arguments.
 *
 * Tracks quote state and nesting depth so a comma inside a string or a nested
 * call does not split an argument. This is the part a regex cannot do, and the
 * reason the extractor is written as a small scanner.
 *
 * @param {string} source Full file text.
 * @param {number} start  Index of the opening parenthesis.
 * @returns {?{args: string[], end: number}} Arguments and the closing index.
 */
function readArgs(source, start) {
	const args = [];
	let depth = 0;
	let current = '';
	let quote = null;

	for (let i = start; i < source.length; i += 1) {
		const char = source[i];
		const previous = source[i - 1];

		if (quote) {
			current += char;

			// A quote preceded by an odd number of backslashes is escaped.
			if (char === quote) {
				let slashes = 0;
				let j = i - 1;

				while (j >= 0 && source[j] === '\\') {
					slashes += 1;
					j -= 1;
				}

				if (slashes % 2 === 0) {
					quote = null;
				}
			}

			continue;
		}

		if (char === "'" || char === '"') {
			quote = char;
			current += char;
			continue;
		}

		if (char === '(' || char === '[') {
			depth += 1;

			if (depth === 1 && char === '(') {
				continue;
			}

			current += char;
			continue;
		}

		if (char === ')' || char === ']') {
			depth -= 1;

			if (depth === 0 && char === ')') {
				args.push(current.trim());
				return { args, end: i };
			}

			current += char;
			continue;
		}

		if (char === ',' && depth === 1) {
			args.push(current.trim());
			current = '';
			continue;
		}

		// Collapse newlines so a wrapped call reads as one argument.
		current += char === '\n' || char === '\r' ? ' ' : char;
		void previous;
	}

	return null;
}

/**
 * Resolve a PHP string literal, including simple `'a' . 'b'` concatenation.
 *
 * Returns null for anything dynamic — a variable or a function call cannot be
 * extracted, and emitting it as a literal would put `$label` in the catalogue.
 *
 * @param {string} raw Argument source.
 * @returns {?string} The string value.
 */
function literal(raw) {
	const parts = [];
	let rest = raw.trim();

	while (rest.length) {
		const match = /^('(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")/.exec(rest);

		if (!match) {
			return null;
		}

		const token = match[1];
		const body = token.slice(1, -1);

		parts.push(
			token[0] === "'"
				? body.replace(/\\\\/g, '\\').replace(/\\'/g, "'")
				: body.replace(/\\\\/g, '\\').replace(/\\"/g, '"').replace(/\\n/g, '\n').replace(/\\t/g, '\t')
		);

		rest = rest.slice(token.length).trim();

		if (rest.startsWith('.')) {
			rest = rest.slice(1).trim();
			continue;
		}

		if (rest.length) {
			return null;
		}
	}

	return parts.length ? parts.join('') : null;
}

/**
 * The translator comment immediately above a call, if any.
 *
 * @param {string} source Full file text.
 * @param {number} index  Index of the call.
 * @returns {?string} Comment text.
 */
function translatorComment(source, index) {
	const before = source.slice(Math.max(0, index - 400), index);
	const match = /\/\*\s*translators:\s*([\s\S]*?)\*\/\s*$/i.exec(before);

	if (!match) {
		return null;
	}

	return match[1]
		.split('\n')
		.map((line) => line.replace(/^\s*\*?\s?/, '').trim())
		.filter(Boolean)
		.join(' ');
}

/**
 * Escape a string for a PO file.
 *
 * @param {string} value Raw string.
 * @returns {string} Escaped string.
 */
function poEscape(value) {
	return value
		.replace(/\\/g, '\\\\')
		.replace(/"/g, '\\"')
		.replace(/\n/g, '\\n')
		.replace(/\t/g, '\\t');
}

/** @type {Map<string, Object>} Entries keyed by context + singular + plural. */
const entries = new Map();

for (const file of phpFiles(themeDir)) {
	const source = readFileSync(file, 'utf8');
	const reference = relative(themeDir, file).split('\\').join('/');

	const pattern = /(?<![\w$>:])(__|_e|_x|_ex|_n|_nx|esc_html__|esc_html_e|esc_html_x|esc_attr__|esc_attr_e|esc_attr_x)\s*\(/g;
	let match;

	while ((match = pattern.exec(source)) !== null) {
		const spec = FUNCTIONS[match[1]];
		const parsed = readArgs(source, match.index + match[0].length - 1);

		if (!spec || !parsed) {
			continue;
		}

		const domain = literal(parsed.args[spec.domain - 1] ?? '');

		// Strings belonging to another domain are not ours to translate.
		if (domain !== DOMAIN) {
			continue;
		}

		const singular = literal(parsed.args[spec.singular - 1] ?? '');

		if (singular === null) {
			continue;
		}

		const plural = spec.plural ? literal(parsed.args[spec.plural - 1] ?? '') : null;
		const context = spec.context ? literal(parsed.args[spec.context - 1] ?? '') : null;
		const line = source.slice(0, match.index).split('\n').length;
		const key = `${context ?? ''}\u0004${singular}\u0004${plural ?? ''}`;

		const existing = entries.get(key) ?? {
			singular,
			plural,
			context,
			comments: new Set(),
			references: []
		};

		const comment = translatorComment(source, match.index);

		if (comment) {
			existing.comments.add(comment);
		}

		existing.references.push(`${reference}:${line}`);
		entries.set(key, existing);
	}
}

const sorted = [...entries.values()].sort((a, b) =>
	a.references[0].localeCompare(b.references[0], 'en') || a.singular.localeCompare(b.singular, 'en')
);

const now = new Date().toISOString().replace(/\.\d+Z$/, '+0000').replace('T', ' ');

const out = [
	'# Copyright (C) Boundless Gracia Children Foundation',
	'# This file is distributed under the same licence as the theme.',
	'msgid ""',
	'msgstr ""',
	'"Project-Id-Version: Boundless Gracia Children Foundation 1.2.0\\n"',
	'"Report-Msgid-Bugs-To: https://wordpress.org/support/theme/bgcf\\n"',
	`"POT-Creation-Date: ${now}\\n"`,
	'"MIME-Version: 1.0\\n"',
	'"Content-Type: text/plain; charset=UTF-8\\n"',
	'"Content-Transfer-Encoding: 8bit\\n"',
	'"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"',
	'"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"',
	'"Language-Team: LANGUAGE <LL@li.org>\\n"',
	'"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
	'"X-Generator: build/makepot.mjs\\n"',
	'"X-Domain: bgcf\\n"',
	''
];

for (const entry of sorted) {
	for (const comment of entry.comments) {
		out.push(`#. translators: ${comment}`);
	}

	// Cap the reference list: a string used in forty places produces forty lines
	// of noise that no translator reads.
	out.push(`#: ${entry.references.slice(0, 6).join(' ')}`);

	if (entry.context !== null) {
		out.push(`msgctxt "${poEscape(entry.context)}"`);
	}

	out.push(`msgid "${poEscape(entry.singular)}"`);

	if (entry.plural !== null) {
		out.push(`msgid_plural "${poEscape(entry.plural)}"`);
		out.push('msgstr[0] ""');
		out.push('msgstr[1] ""');
	} else {
		out.push('msgstr ""');
	}

	out.push('');
}

writeFileSync(outFile, out.join('\n'), 'utf8');
process.stdout.write(`Wrote ${sorted.length} strings to theme/languages/bgcf.pot\n`);
