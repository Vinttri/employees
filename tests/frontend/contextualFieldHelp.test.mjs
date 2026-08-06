import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '../..')
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8')
const walk = directory => fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
	const fullPath = path.join(directory, entry.name)
	return entry.isDirectory() ? walk(fullPath) : [fullPath]
})

const enhancer = read('src/utils/contextualFieldHelp.js')
const catalog = read('src/utils/fieldHelpCatalog.js')
const styles = read('src/styles/contextualFieldHelp.scss')
const main = read('src/main.js')
const settings = read('src/settings.js')
const russian = JSON.parse(read('l10n/ru.json')).translations
const vueFiles = walk(path.join(root, 'src')).filter(file => file.endsWith('.vue'))
const controlPattern = /<NcTextField\b|<NcSelect\b|<input\b|<textarea\b|<select\b/g
const controls = vueFiles.reduce((count, file) => count + (fs.readFileSync(file, 'utf8').match(controlPattern)?.length || 0), 0)
const filesWithControls = vueFiles.filter(file => (fs.readFileSync(file, 'utf8').match(controlPattern)?.length || 0) > 0)

assert.ok(controls >= 300, `expected app-wide form coverage, found ${controls} controls`)
assert.ok(filesWithControls.length >= 45, `expected broad form coverage, found ${filesWithControls.length} files`)
for (const selector of ['input:not([type="hidden"])', 'textarea', 'select', '[role="combobox"]']) {
	assert.ok(enhancer.includes(selector), `missing field selector ${selector}`)
}
for (const contract of [
	'MutationObserver',
	'aria-describedby',
	'aria-expanded',
	'role',
	'tooltip',
	"event.key === 'Escape'",
	'dataset.employeesFieldHelp',
	'.modal-mask[role="dialog"]',
	'document.body || root',
]) {
	assert.ok(enhancer.includes(contract), `missing accessibility or lifecycle contract ${contract}`)
}
assert.match(main, /startContextualFieldHelp\(view\.\$el\)/)
assert.match(settings, /startContextualFieldHelp\(view\.\$el\)/)
assert.match(catalog, /IBAN/)
assert.match(catalog, /salary|payroll/)
assert.match(catalog, /absence|vacation/)
assert.match(catalog, /department|organisation/)
assert.match(catalog, /inventory/)
assert.match(catalog, /search\|filter/)
assert.match(catalog, /period\|период/)
assert.equal(catalog.includes('банковск|сч[её]т|'), false, 'bank rule must not match the word “расчётный”')
assert.equal(/#[0-9a-f]{3,8}\b/i.test(styles), false, 'field help must not hardcode colors')
for (const token of ['--color-main-background', '--color-main-text', '--color-primary-element', '--color-border']) {
	assert.ok(styles.includes(token), `missing Nextcloud theme token ${token}`)
}
for (const key of [
	'Why: {purpose} Example: {example}.',
	'Example: {example}.',
	'Help for {field}',
	'this field',
	'Used as the base of the payroll calculation.',
	'Defines how an absence affects payroll and calendars.',
	'Identifies the equipment in inventory.',
	'Enter the value that should be saved in this field.',
	'Filters the visible records without changing them.',
	'Selects the reporting or calculation period.',
]) {
	assert.ok(russian[key], `missing Russian field help translation: ${key}`)
}

process.stdout.write(`CONTEXTUAL_FIELD_HELP_OK controls=${controls} files=${filesWithControls.length} locale=ru_en\n`)
