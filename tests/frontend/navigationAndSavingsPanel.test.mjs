import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const router = await readFile(new URL('../../src/router/index.js', import.meta.url), 'utf8')
const navigation = await readFile(new URL('../../src/views/navigator/SideNavigation.vue', import.meta.url), 'utf8')
const sharedStyles = await readFile(new URL('../../src/styles/employees.scss', import.meta.url), 'utf8')
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

// The navigation toggle must stay inside every visible navigation rail. Only
// the zero-width hidden mode may place it beside the rail, in which case the
// shared app-content gutter reserves the same space for routed page headings.
assert.match(navigation, /\.side-toggle-button--floating\s*\{[^}]*right:\s*12px/s)
assert.match(navigation, /\.employees-side-navigation--compact \.side-toggle-button--floating\s*\{[^}]*right:\s*16px/s)
assert.match(navigation, /\.employees-side-navigation--hidden \.side-toggle-button--floating\s*\{[^}]*right:\s*-52px/s)
assert.match(navigation, /\.side-navigation-content\s*\{[^}]*padding-top:\s*54px/s)
assert.match(sharedStyles, /\.employees-side-navigation--hidden \+ \.app-content\s*\{[^}]*padding-inline-start:\s*56px/s)
