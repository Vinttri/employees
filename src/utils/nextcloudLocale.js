import { getLanguage } from '@nextcloud/l10n'

/**
 * Return the active Nextcloud language in the BCP 47 form expected by Intl.
 *
 * @return {string} Locale selected in the current Nextcloud profile
 */
export function nextcloudLocale() {
	return String(getLanguage() || 'en').replaceAll('_', '-')
}
