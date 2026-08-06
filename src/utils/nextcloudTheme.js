const ROOT = () => document.documentElement

export function getNextcloudThemeColor(variable) {
	return getComputedStyle(ROOT()).getPropertyValue(variable).trim()
}

export function getNextcloudThemeColorWithAlpha(variable, alpha) {
	const rgbVariable = `${variable}-rgb`
	const rgb = getNextcloudThemeColor(rgbVariable)
	if (rgb) {
		return ['rgb(', rgb, ' / ', alpha, ')'].join('')
	}

	const probe = document.createElement('span')
	probe.style.color = `rgb(from var(${variable}) r g b / ${alpha})`
	ROOT().appendChild(probe)
	const resolved = getComputedStyle(probe).color
	probe.remove()
	return resolved
}
