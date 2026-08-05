import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const component = await readFile(new URL('../../src/views/components/reports/ReportCompliance.vue', import.meta.url), 'utf8')
const controller = await readFile(new URL('../../lib/Controller/TimeReportsController.php', import.meta.url), 'utf8')

assert.match(controller, /'employees'\s*=>\s*\$data/)
assert.match(component, /employees:\s*\[\]/)
assert.match(component, /v-for="employee in employees"/)
assert.match(component, /Array\.isArray\(data\.employees\)/)
assert.match(component, /\? data\.employees/)
assert.doesNotMatch(component, /(?:this\.Employee|data\.Employee|in Employee|Employee:\s*\[)/)
assert.equal((component.match(/<NcDateTimePicker/g) || []).length, 1)
assert.match(component, /formatDate\(value\)/)
assert.match(component, /date\.getFullYear\(\)/)
assert.match(component, /date\.getMonth\(\) \+ 1/)
assert.match(component, /date\.getDate\(\)/)
assert.doesNotMatch(component, /toISOString\(\)\.slice\(0, 10\)/)

console.log('REPORT_COMPLIANCE_FRONTEND_OK employees_contract=1 date_picker=1')
