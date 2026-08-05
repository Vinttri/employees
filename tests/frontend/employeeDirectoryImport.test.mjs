import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const component = await readFile(new URL('../../src/views/Settings/EmployeesSettings.vue', import.meta.url), 'utf8')
const contentList = await readFile(new URL('../../src/views/components/EmployeeList/ContentList.vue', import.meta.url), 'utf8')
const routes = await readFile(new URL('../../appinfo/routes.php', import.meta.url), 'utf8')
const controller = await readFile(new URL('../../lib/Controller/EmployeesController.php', import.meta.url), 'utf8')

assert.match(component, /Create employee record/)
assert.match(component, /Import selected from Nextcloud/)
assert.match(component, /Preview Contacts organization data/)
assert.match(component, /selectedPendingUsers/)
assert.match(component, /contactsPreview/)
assert.match(routes, /employees#createEmployeesFromNextcloud/)
assert.match(routes, /employees#previewContactsOrganization/)
assert.match(routes, /employees#importContactsOrganization/)
assert.match(controller, /function createEmployeesFromNextcloud/)
assert.match(controller, /function previewContactsOrganization/)
assert.match(controller, /function importContactsOrganization/)
assert.match(contentList, /Add or import employees/)
assert.match(contentList, /\/settings\/admin\/employees/)

console.log('EMPLOYEE_DIRECTORY_IMPORT_FRONTEND_OK bulk_users=1 contacts_preview=1')
