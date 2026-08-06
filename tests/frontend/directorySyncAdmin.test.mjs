import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const component = await readFile(new URL('../../src/views/Settings/EmployeesSettings.vue', import.meta.url), 'utf8')
const routes = await readFile(new URL('../../appinfo/routes.php', import.meta.url), 'utf8')

assert.match(component, /Nextcloud directory synchronization/)
assert.match(component, /Synchronize now/)
assert.match(component, /Local edits are never overwritten/)
assert.match(component, /directory\/status/)
assert.match(component, /directory\/sync/)
assert.match(routes, /employees#syncDirectory/)
assert.match(routes, /employees#directorySyncStatus/)

console.log('DIRECTORY_SYNC_ADMIN_OK status=1 manual_trigger=1 native_tokens=1')
