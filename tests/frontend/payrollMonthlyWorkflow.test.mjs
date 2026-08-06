import assert from 'node:assert/strict'
import fs from 'node:fs'

const root = new URL('../../', import.meta.url)
const read = path => fs.readFileSync(new URL(path, root), 'utf8')

const view = read('src/views/components/Payroll/Payroll.vue')
const service = read('src/services/payrollService.js')
const routes = read('appinfo/routes.php')
const backend = read('lib/Service/PayrollPackageService.php')
const migration = read('lib/Migration/Version2053Date20260806150000.php')
const russianTranslations = JSON.parse(read('l10n/ru.json')).translations

for (const contract of [
	'Salary calculations by month',
	'Employee payroll setup',
	'Payroll register',
	'SEPA bank transfer',
	'Regenerate files in Team Folder',
]) assert.match(view, new RegExp(contract))

assert.match(service, /sepaUrl\(periodId\)/)
assert.match(service, /payslipUrl\(payslipId\)/)
assert.match(routes, /\/payroll\/periods\/\{periodId\}\/sepa\.xml/)
assert.match(routes, /\/payroll\/periods\/\{periodId\}\/publish/)
assert.match(routes, /\/payroll\/employees\/\{employeeId\}\/setup/)
assert.match(backend, /pain\.001\.001\.09/)
assert.match(backend, /sep=;/)
assert.match(backend, /Employees_storage.*Payroll.*Calculations/s)
assert.match(backend, /Official documents\/Payroll/)
assert.match(migration, /document_file_id.*bigint/s)

for (const key of [
	'Create payroll period',
	'Create salary plan',
	'Add monthly input',
	'Create tax profile',
	'Define a calculation window and currency.',
	'Enter hours, bonuses, taxes, deductions or a one-off payment.',
]) {
	assert.ok(russianTranslations[key], `Missing Russian payroll translation: ${key}`)
	assert.notEqual(russianTranslations[key], key, `Untranslated Russian payroll label: ${key}`)
}

assert.doesNotMatch(view, /#[0-9a-fA-F]{3,8}\b/)
for (const token of ['--color-primary-element', '--color-main-background', '--color-border']) {
	assert.match(view, new RegExp(token))
}

console.log('PAYROLL_MONTHLY_WORKFLOW_OK')
