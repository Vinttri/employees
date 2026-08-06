import { translate as t } from '@nextcloud/l10n'

/**
 * Localize canonical/system absence data without changing the value stored in
 * the database. User-defined labels safely fall back to their original text.
 *
 * @param {unknown} value Raw absence label or description
 * @return {string} Localized display value
 */
export function localizeAbsenceText(value) {
	const text = String(value ?? '').trim()
	return text ? t('employees', text) : ''
}
