import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const read = async path => readFile(new URL(`../../${path}`, import.meta.url), 'utf8')

const dashboard = await read('src/views/components/Dashboard/Dashboard.vue')
const employees = await read('src/views/components/EmployeeList/Employees.vue')
const employeeList = await read('src/views/components/EmployeeList/EmployeeList.vue')
const contentList = await read('src/views/components/EmployeeList/ContentList.vue')
const orgChartNetwork = await read('src/views/components/EmployeeList/OrgChart/OrgChartNetwork.vue')
const orgChartTraditional = await read('src/views/components/EmployeeList/OrgChart/OrgChartTraditional.vue')
const orgChartTable = await read('src/views/components/EmployeeList/OrgChart/OrgChartTable.vue')
const request = await read('src/views/components/savings/Request.vue')
const calendar = await read('src/views/components/TimeOff/TimeOff.vue')
const en = JSON.parse(await read('l10n/en.json')).translations
const ru = JSON.parse(await read('l10n/ru.json')).translations

assert.match(dashboard, /employees\(\)\s*\{[\s\S]*extractPlainArray\(this\.Employee\)/)
assert.match(employees, /response\?\.data\?\.ocs\?\.data \?\? response\?\.data/)
assert.match(employeeList, /employeesProp:/)
assert.doesNotMatch(employeeList, /empleadosProp:/)
assert.match(contentList, /Array\.isArray\(this\.employees\)/)

for (const orgChart of [orgChartNetwork, orgChartTraditional, orgChartTable]) {
	assert.match(orgChart, /employees\(\)\s*\{[\s\S]*Array\.isArray\(this\.Employee\)/)
}
assert.match(orgChartNetwork, /if \(!container\) return false/)
assert.match(orgChartNetwork, /initializeNetwork\(attempt = 0\)/)

assert.match(request, /hasEmployeeProfile/)
assert.match(request, /currentEmployee\(\)/)

assert.match(calendar, /getLanguage\(\)/)
assert.match(calendar, /fullCalendarLocale/)
assert.match(calendar, /responseArray\(response/)
assert.match(calendar, /v-if="absenceLoading"/)
assert.match(calendar, /finally\s*\{\s*this\.absenceLoading = false/)
assert.equal(/locale:\s*['"]es['"]/.test(calendar), false)
assert.doesNotMatch(calendar, /generateUrl\(generateUrl/)
assert.doesNotMatch(calendar, /An exception has occurred[^\n]+\{err\}/)

const requiredTranslations = [
	'Network',
	'Organization chart',
	'Table',
	'Search by name...',
	'Use before {date} or they expire',
	'You cannot start or end your absence on a holiday',
]

for (const key of requiredTranslations) {
	assert.equal(en[key], key, `missing English translation: ${key}`)
	assert.ok(ru[key] && ru[key] !== key, `missing Russian translation: ${key}`)
}
