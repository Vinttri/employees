import assert from 'node:assert/strict'
import { readdir, readFile } from 'node:fs/promises'
import { extname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const sourceRoot = fileURLToPath(new URL('../../src/', import.meta.url))
const russian = JSON.parse(await readFile(new URL('../../l10n/ru.json', import.meta.url), 'utf8')).translations
const english = JSON.parse(await readFile(new URL('../../l10n/en.json', import.meta.url), 'utf8')).translations
const sourceFiles = []

async function collectFiles(directory) {
	for (const entry of await readdir(directory, { withFileTypes: true })) {
		const path = join(directory, entry.name)
		if (entry.isDirectory()) {
			await collectFiles(path)
		} else if (['.js', '.vue'].includes(extname(entry.name))) {
			sourceFiles.push(path)
		}
	}
}

await collectFiles(sourceRoot)

const usedKeys = new Set()
const dynamicSystemKeys = [
	'Medical leave (demo)',
	'Personal day',
	'Personal day (demo)',
	'Sick leave',
	'Stock',
]
const translationCalls = [
	/\bt\(\s*['"]employees['"]\s*,\s*'((?:\\.|[^'\\])*)'/g,
	/\bt\(\s*['"]employees['"]\s*,\s*"((?:\\.|[^"\\])*)"/g,
]

function decodeKey(value) {
	return value
		.replace(/\\'/g, "'")
		.replace(/\\"/g, '"')
		.replace(/\\\\/g, '\\')
}

for (const path of sourceFiles) {
	const source = await readFile(path, 'utf8')
	for (const translationCall of translationCalls) {
		for (const match of source.matchAll(translationCall)) {
			usedKeys.add(decodeKey(match[1]))
		}
	}
}

for (const key of dynamicSystemKeys) usedKeys.add(key)

const missingEnglish = [...usedKeys].filter(key => !(key in english)).sort()
const missingRussian = [...usedKeys].filter(key => !(key in russian)).sort()

assert.deepEqual(missingEnglish, [], `Missing English l10n keys:\n${missingEnglish.join('\n')}`)
assert.deepEqual(missingRussian, [], `Missing Russian l10n keys:\n${missingRussian.join('\n')}`)

const placeholders = value => [...String(value).matchAll(/\{[^{}]+\}|%(?:\d+\$)?[a-z]/gi)]
	.map(match => match[0])
	.sort()
const placeholderMismatches = [...usedKeys]
	.filter(key => JSON.stringify(placeholders(key)) !== JSON.stringify(placeholders(russian[key])))
	.sort()

assert.deepEqual(
	placeholderMismatches,
	[],
	`Russian translations changed placeholders:\n${placeholderMismatches.join('\n')}`,
)

const allowedRussianIdentityKeys = new Set(['CURP', 'IMSS', 'RFC', '{percentage}%'])
const untranslatedRussian = [...usedKeys]
	.filter(key => /[A-Za-z]{2}/.test(key))
	.filter(key => russian[key] === key && !allowedRussianIdentityKeys.has(key))
	.sort()

assert.deepEqual(
	untranslatedRussian,
	[],
	`Russian translations still equal their English source:\n${untranslatedRussian.join('\n')}`,
)

console.log(`RUSSIAN_L10N_COVERAGE_OK keys=${usedKeys.size}`)
