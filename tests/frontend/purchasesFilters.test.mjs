import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const source = await readFile(new URL('../../src/utils/purchasesFilters.js', import.meta.url), 'utf8')
const moduleUrl = `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
const { buildPurchaseListParams } = await import(moduleUrl)

assert.deepEqual(buildPurchaseListParams({
	showOnlyMine: true,
	canViewAll: true,
	status: 'todos',
	page: 1,
	pageSize: 20,
}), { todas: 0, limit: 20, offset: 0 })

assert.deepEqual(buildPurchaseListParams({
	showOnlyMine: false,
	canViewAll: true,
	status: 'pendiente_autorizacion',
	page: 3,
	pageSize: 20,
}), {
	todas: 1,
	limit: 20,
	offset: 40,
	status: 'pendiente_autorizacion',
})

assert.equal(buildPurchaseListParams({
	showOnlyMine: false,
	canViewAll: false,
	status: 'borrador',
	page: 1,
	pageSize: 20,
}).todas, 0)
