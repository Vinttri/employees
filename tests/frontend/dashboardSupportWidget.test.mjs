import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const entrypoint = await readFile(new URL('../../src/dashboard-support.js', import.meta.url), 'utf8')
const component = await readFile(new URL('../../src/Dashboard/TeamSupportDashboardWidget.vue', import.meta.url), 'utf8')
const form = await readFile(new URL('../../src/components/Inventory/RegisterSupportForm.vue', import.meta.url), 'utf8')
const widgetClass = await readFile(new URL('../../lib/Dashboard/TeamSupportWidget.php', import.meta.url), 'utf8')

const widgetId = 'employees-team-support'

assert.match(widgetClass, new RegExp(`public const ID = '${widgetId}'`))
assert.match(entrypoint, new RegExp(`SUPPORT_WIDGET_ID = '${widgetId}'`))
assert.match(entrypoint, /Dashboard\.register\(SUPPORT_WIDGET_ID, \(el\) =>/)
assert.match(entrypoint, /new View\(\)\.\$mount\(el\)/)

assert.doesNotMatch(component, /Search device|SupportDurationFields|NcTextArea/)
assert.match(component, /v-if="modalOpen"[\s\S]*<RegisterSupportForm/)
assert.match(component, /@click="openModal"/)
assert.match(component, /@cancel="closeModal"/)
assert.match(component, /this\.\$refs\.openButton[\s\S]*focus/)
assert.match(component, /max-height: calc\(100vh/)
assert.doesNotMatch(component, /position:\s*fixed/)

assert.doesNotMatch(form, /\bmounted\s*\(/)
assert.equal((form.match(/inventoryService\.getTeams/g) || []).length, 1)
assert.match(form, /const sequence = \+\+this\.searchSequence/)
assert.match(form, /setTimeout\(\(\) => this\.searchDevices\(query, sequence\), 400\)/)
assert.match(form, /getTeams\(\{ search: query, limit: 8, offset: 0 \}\)/)
assert.match(form, /sequence !== this\.searchSequence/)
assert.match(form, /if \(!this\.formValid \|\| this\.submitting\) return/)
assert.match(form, /this\.submitting = true[\s\S]*await inventoryService\.createSupport/)
assert.match(form, /duration_minutes: this\.form\.duration_minutes/)
assert.doesNotMatch(form, /\bhoras\s*:/)
assert.doesNotMatch(form, /\bminutos\s*:/)
assert.match(form, /Number\.isInteger\(this\.form\.duration_minutes\)/)
assert.match(form, /finally \{ this\.submitting = false \}/)
assert.match(form, /generateUrl\('\/apps\/employees\/'\)[\s\S]*deviceId=/)
assert.match(form, /resetAfterSuccess\(\)/)
assert.match(form, /catch \(error\)[\s\S]*showError/)
