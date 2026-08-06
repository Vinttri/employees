import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const sourceRoot = path.join(root, 'src')
const supportedThemeVariables = new Set([
	'--color-background-dark', '--color-background-darker', '--color-background-hover',
	'--color-border', '--color-border-dark', '--color-border-error',
	'--color-border-maxcontrast', '--color-border-success', '--color-box-shadow',
	'--color-element-success', '--color-element-warning', '--color-error',
	'--color-error-hover', '--color-error-text', '--color-info', '--color-info-text',
	'--color-main-background', '--color-main-background-translucent', '--color-main-text',
	'--color-primary', '--color-primary-element', '--color-primary-element-hover',
	'--color-primary-element-light', '--color-primary-element-light-text',
	'--color-primary-element-text', '--color-primary-light', '--color-primary-text',
	'--color-success', '--color-success-hover', '--color-success-text',
	'--color-text-maxcontrast', '--color-warning', '--color-warning-hover',
	'--color-warning-text',
])

function sourceFiles(directory) {
	return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
		const item = path.join(directory, entry.name)
		return entry.isDirectory() ? sourceFiles(item) : [item]
	}).filter((file) => /\.(?:css|js|scss|vue)$/.test(file))
}

test('UI colors are inherited from semantic Nextcloud theme variables', () => {
	const violations = []
	for (const file of sourceFiles(sourceRoot)) {
		const source = fs.readFileSync(file, 'utf8')
		const checks = [
			['hex color', /#[\da-f]{3,8}\b/gi],
			['absolute rgb or hsl color', /(?:rgba?|hsla?)\((?!from\s+var\()[^\n)]*\)/gi],
			['named color', /(?:color|background(?:-color)?|border(?:-[^:]*)?|fill|stroke)\s*:\s*(?:white|black|red|blue|green|orange|yellow|gray|grey)\b/gi],
			['quoted named color', /['"](?:white|black|red|blue|green|orange|yellow|gray|grey|pink|purple)['"]/gi],
		]

		for (const [label, expression] of checks) {
			for (const match of source.matchAll(expression)) {
				if (label === 'absolute rgb or hsl color' && file.endsWith('utils/nextcloudTheme.js')) continue
				const line = source.slice(0, match.index).split('\n').length
				violations.push(`${path.relative(root, file)}:${line}: ${label}: ${match[0]}`)
			}
		}

		for (const match of source.matchAll(/--color-[\w-]+/g)) {
			if (supportedThemeVariables.has(match[0])) continue
			const line = source.slice(0, match.index).split('\n').length
			violations.push(`${path.relative(root, file)}:${line}: unsupported Nextcloud theme variable: ${match[0]}`)
		}
	}

	assert.deepEqual(violations, [], violations.join('\n'))
})
