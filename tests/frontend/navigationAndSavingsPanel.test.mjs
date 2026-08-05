import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const router = await readFile(new URL('../../src/router/index.js', import.meta.url), 'utf8')
const navigation = await readFile(new URL('../../src/views/navigator/SideNavigation.vue', import.meta.url), 'utf8')
const dashboard = await readFile(new URL('../../src/views/components/Dashboard/Dashboard.vue', import.meta.url), 'utf8')
const savingsPanel = await readFile(new URL('../../src/views/components/savings/SavingsPanel.vue', import.meta.url), 'utf8')

const routeNames = [...router.matchAll(/\bname:\s*'([^']+)'/g)].map((match) => match[1])
const navigationTargets = [...navigation.matchAll(/:to="\{ name: '([^']+)' \}"/g)].map((match) => match[1])
const dashboardTargets = [...dashboard.matchAll(/(?:@click="go|route:)\(?['"]([^'"]+)['"]/g)].map((match) => match[1])

for (const target of [...navigationTargets, ...dashboardTargets]) {
	assert.ok(routeNames.includes(target), `unknown frontend route name: ${target}`)
}

assert.match(savingsPanel, /response\?\.data\?\.ocs\?\.data \?\? response\?\.data/)
assert.match(savingsPanel, /if \(!Array\.isArray\(payload\)\)/)
assert.match(savingsPanel, /generateUrl\('\/apps\/employees\/GetHistoryPanel\//)
assert.doesNotMatch(savingsPanel, /window\.location\.href\s*=\s*['"]\/apps\/employees\/#\//)
assert.doesNotMatch(savingsPanel, /showError\(response\?\.data\?\.ocs\?\.meta\?\.message\)/)
