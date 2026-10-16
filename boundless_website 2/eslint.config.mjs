/**
 * ESLint flat config.
 *
 * Two environments, deliberately separate:
 *   - `theme/assets/js/src/` — browser code, bundled by esbuild to
 *     `theme/assets/js/main.js`. No Node globals, no `process`.
 *   - `build/` — Node build scripts. No DOM globals.
 *
 * Compiled output (`theme/assets/js/main.js`, `theme/assets/js/vendor/`) is a
 * build artefact and is never linted (ARCHITECTURE §1).
 */

import js from '@eslint/js';
import globals from 'globals';

export default [
	{
		ignores: [
			'node_modules/**',
			'theme/assets/js/main.js',
			'theme/assets/js/vendor/**',
			'theme/assets/css/**'
		]
	},

	js.configs.recommended,

	{
		name: 'bgcf/theme-frontend',
		files: ['theme/assets/js/src/**/*.js'],
		languageOptions: {
			ecmaVersion: 2021,
			sourceType: 'module',
			globals: {
				...globals.browser,
				bgcfData: 'readonly',
				gsap: 'readonly',
				ScrollTrigger: 'readonly',
				Swiper: 'readonly'
			}
		},
		rules: {
			'no-console': 'error',
			'no-alert': 'error',
			'no-var': 'error',
			'prefer-const': 'error',
			eqeqeq: ['error', 'always'],
			curly: ['error', 'all'],
			'no-implicit-globals': 'error',
			'no-unused-vars': ['error', { argsIgnorePattern: '^_' }]
		}
	},

	{
		name: 'bgcf/build-scripts',
		files: ['build/**/*.mjs'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: {
				...globals.node
			}
		},
		rules: {
			'no-var': 'error',
			'prefer-const': 'error',
			eqeqeq: ['error', 'always'],
			curly: ['error', 'all']
		}
	}
];
