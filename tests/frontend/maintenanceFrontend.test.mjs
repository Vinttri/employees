import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const formatterSource = await readFile(new URL('../../src/utils/maintenanceFormatters.js', import.meta.url), 'utf8')
const formatterUrl = `data:text/javascript;base64,${Buffer.from(formatterSource).toString('base64')}`
const { addOneCalendarDay, formatDateRange, formatOptionalTimeRange, isPositiveId, maintenanceCapabilities, maintenanceRecordCapabilities, toApiDate, toggleSelectedEquipment } = await import(formatterUrl)

assert.equal(isPositiveId('15'), true)
assert.equal(isPositiveId('0'), false)
assert.equal(isPositiveId('-2'), false)
assert.equal(isPositiveId('12abc'), false)
assert.equal(toApiDate(new Date(2026, 7, 20, 23, 30)), '2026-08-20')
assert.equal(addOneCalendarDay('2026-08-21'), '2026-08-22')
assert.equal(addOneCalendarDay('2026-12-31'), '2027-01-01')
assert.equal(formatDateRange('2026-08-17', '2026-08-21', 'es-MX').includes('–'), true)
assert.equal(formatOptionalTimeRange('09:00:00', '17:00:00'), '09:00 – 17:00')

const admin = maintenanceCapabilities({ is_admin: true }, { modulo_inventario: 'true' })
assert.equal(admin.canAdministerMaintenance, true)
assert.equal(admin.canViewMaintenance, true)
assert.equal(admin.moduleEnabled, true)

const technician = maintenanceCapabilities({ modules: { inventario: { permissions: { technician: true } } } }, { modulo_inventario: true })
assert.equal(technician.isTechnician, true)
assert.equal(technician.canAdministerMaintenance, false)
assert.equal(technician.canViewMaintenance, true)

const view = maintenanceCapabilities({ modules: { inventario: { view: true } } }, { modulo_inventario: '1' })
assert.equal(view.canViewMaintenance, true)
assert.equal(view.canWorkMaintenance, false)

const adminActions = maintenanceRecordCapabilities({ status: 'pending', technician_uid: 'other' }, admin, 'admin')
assert.equal(adminActions.canStart, true)
assert.equal(adminActions.canSchedule, true)
assert.equal(adminActions.canCancel, true)
const assignedActions = maintenanceRecordCapabilities({ status: 'in_progress', technician_uid: 'tech' }, technician, 'tech')
assert.equal(assignedActions.canEditChecklist, true)
assert.equal(assignedActions.canComplete, true)
assert.equal(assignedActions.canAssignTechnician, false)
const foreignActions = maintenanceRecordCapabilities({ status: 'in_progress', technician_uid: 'other' }, technician, 'tech')
assert.equal(foreignActions.canOperate, false)
assert.equal(foreignActions.canComplete, false)
const viewActions = maintenanceRecordCapabilities({ status: 'in_progress', technician_uid: 'tech' }, view, 'tech')
assert.equal(viewActions.canOperate, false)
const finalActions = maintenanceRecordCapabilities({ status: 'completed', technician_uid: 'tech' }, admin, 'admin')
assert.equal(finalActions.readOnly, true)
assert.equal(finalActions.canStart, false)

let selection = toggleSelectedEquipment([], [{ id: 3 }, { id: 4 }, { id: 3 }], true)
assert.deepEqual(selection, [3, 4])
selection = toggleSelectedEquipment(selection, [{ id: 3 }], false)
assert.deepEqual(selection, [4])
selection = toggleSelectedEquipment(selection, [{ id: 5 }], true)
assert.deepEqual(selection, [4, 5])

const service = await readFile(new URL('../../src/services/maintenanceService.js', import.meta.url), 'utf8')
for (const endpoint of [
	'/inventario/mantenimientos/grupos',
	'/inventario/mantenimientos/Department/${departmentId}/Team',
	'/inventario/mantenimientos/atrasados',
	'/inventario/mantenimientos/duplicados',
	'/inventario/mantenimientos/${id}',
]) assert.ok(service.includes(endpoint), `Missing endpoint ${endpoint}`)
assert.ok(service.includes('return body?.success === true ? body.data : body'))
assert.ok(service.includes('normalized.code = detail.code'))
assert.ok(service.includes('normalized.conflicts = Array.isArray(detail.conflicts)'))
assert.ok(service.includes('normalized.status = status'))
assert.ok(service.includes("'/inventario/mantenimientos/tecnicos'"))
assert.ok(service.includes('let technicianCache = null'))
assert.ok(service.includes('let technicianRequest = null'))
assert.ok(!service.includes("appUrl('/GetEmpleadosList')"))
const normalizeSource = service.match(/export function normalizeTechnicians\(items\) \{[\s\S]*?\n\}/)?.[0]
assert.ok(normalizeSource)
const normalizeUrl = `data:text/javascript;base64,${Buffer.from(normalizeSource).toString('base64')}`
const { normalizeTechnicians } = await import(normalizeUrl)
assert.deepEqual(normalizeTechnicians([
	{ uid: 'zeta', displayName: 'Zeta' },
	{ value: 'alpha', label: 'Alpha' },
	{ id_user: 'legacy', name: 'Legacy' },
]), [
	{ uid: 'alpha', displayName: 'Alpha' },
	{ uid: 'legacy', displayName: 'Legacy' },
	{ uid: 'zeta', displayName: 'Zeta' },
])

const form = await readFile(new URL('../../src/views/components/Inventory/Maintenance/MaintenanceGroupForm.vue', import.meta.url), 'utf8')
assert.ok(form.includes('equipmentIds: [...new Set(this.selectedIds)]'))
assert.ok(form.includes('periodStart: this.form.periodStart'))
assert.ok(form.includes('periodEnd: this.form.periodEnd'))
assert.ok(!form.includes('form.scheduledDate'))
assert.ok(form.includes('The end date cannot be earlier than the start date.'))
assert.ok(form.includes('allowPotentialDuplicates'))
assert.ok(!form.includes('actorUid'))
assert.ok(!form.includes('actorName'))
assert.ok(!form.includes('technicianName:'))
assert.ok(form.includes("if (this.busy) return"))
assert.ok(form.includes("error.code === 'maintenance_duplicate_conflict'"))

const mainView = await readFile(new URL('../../src/views/components/Inventory/Maintenance/MaintenanceView.vue', import.meta.url), 'utf8')
assert.ok(mainView.includes('canAdministerMaintenance'))
assert.ok(mainView.includes('requestFilters(range)'))
assert.ok(mainView.includes('activeController?.abort()'))

const calendar = await readFile(new URL('../../src/views/components/Inventory/Maintenance/MaintenanceCalendar.vue', import.meta.url), 'utf8')
assert.ok(calendar.includes('start: periodStart'))
assert.ok(calendar.includes('end: addOneCalendarDay(periodEnd)'))

const groupView = await readFile(new URL('../../src/views/components/Inventory/Maintenance/MaintenanceGroupDetails.vue', import.meta.url), 'utf8')
assert.ok(groupView.includes('data.progress'))
assert.ok(groupView.includes('cancelGroup'))
assert.ok(groupView.includes('assignGroupTechnician'))
assert.ok(groupView.includes("'Campaign period'"))
assert.ok(groupView.includes('selectedTechnicianUid'))
assert.ok(!groupView.includes('Enter the technician UID'))
assert.ok(!groupView.includes('window.prompt(t(\'Employee\', \'Enter the technician'))

const detailView = await readFile(new URL('../../src/views/components/Inventory/Maintenance/MaintenanceDetail.vue', import.meta.url), 'utf8')
assert.ok(detailView.includes("typeof value === 'object'"))
assert.ok(detailView.includes('startMaintenance('))
assert.ok(detailView.includes('updateChecklist('))
assert.ok(detailView.includes('completeMaintenance('))
assert.ok(detailView.includes('beforeRouteLeave'))
assert.ok(detailView.includes("error.status === 409"))
assert.ok(!detailView.includes('actorUid'))
assert.ok(!detailView.includes('actorName'))
assert.ok(!detailView.includes("status:"))
assert.ok(detailView.includes("'No specific day assigned'"))
assert.ok(detailView.includes(':value="item.uid"'))

const checklistView = await readFile(new URL('../../src/views/components/Inventory/Maintenance/MaintenanceChecklist.vue', import.meta.url), 'utf8')
assert.ok(checklistView.includes("value: 'attention'"))
assert.ok(checklistView.includes("item.result === 'attention'"))
assert.ok(checklistView.includes('dirtyKeys'))
assert.ok(!checklistView.includes('item.code ='))

const equipmentTable = await readFile(new URL('../../src/views/components/Inventory/Maintenance/MaintenanceTeamsTable.vue', import.meta.url), 'utf8')
assert.ok(equipmentTable.includes("'No day assigned'"))
