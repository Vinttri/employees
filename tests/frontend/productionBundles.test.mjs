import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { existsSync, readdirSync, statSync } from 'node:fs'
import test from 'node:test'

const requiredBundles = [
	'js/main.js',
	'js/employees-settings.js',
	'js/employees-dashboard-reports.js',
	'js/employees-dashboard-support.js',
]

test('all runtime JavaScript entry bundles are packaged in Git', () => {
	const tracked = new Set(execFileSync('git', ['ls-files', 'js/*.js'], { encoding: 'utf8' })
		.trim()
		.split('\n')
		.filter(Boolean))

	for (const bundle of requiredBundles) {
		assert.equal(existsSync(bundle), true, `${bundle} is missing after the production build`)
		assert.equal(statSync(bundle).size > 1024, true, `${bundle} is unexpectedly empty`)
		assert.equal(tracked.has(bundle), true, `${bundle} is not included in Git archives`)
	}

	const generatedChunks = readdirSync('js')
		.filter(file => file.endsWith('.js'))
		.map(file => `js/${file}`)
	for (const chunk of generatedChunks) {
		assert.equal(tracked.has(chunk), true, `${chunk} is generated but missing from Git archives`)
	}
})
