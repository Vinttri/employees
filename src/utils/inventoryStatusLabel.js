import { translate as t } from '@nextcloud/l10n'

const STATUS_LABELS = {
	active: 'Active',
	inactive: 'Inactive',
	inactivo: 'Inactive',
	maintenance: 'Maintenance',
	mantenimiento: 'Maintenance',
	repair: 'Repair',
	reparacion: 'Repair',
	stock: 'Stock',
	assigned: 'Assigned',
	asignado: 'Assigned',
	unassigned: 'Unassigned',
	support: 'Support',
	soporte: 'Support',
	retired: 'Retired',
	baja: 'Retired',
}

/**
 * Translate canonical inventory states while preserving unknown/custom values.
 *
 * @param {unknown} value Raw state stored by the inventory module
 * @return {string} Localized state for display
 */
export function inventoryStatusLabel(value) {
	const raw = String(value ?? '').trim()
	if (!raw) return ''
	const key = STATUS_LABELS[raw.toLowerCase()]
	return key ? t('employees', key) : raw
}
