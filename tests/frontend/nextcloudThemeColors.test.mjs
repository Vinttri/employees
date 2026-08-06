import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const sourceRoot = path.join(root, 'src')

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
	}

	assert.deepEqual(violations, [], violations.join('\n'))
})
