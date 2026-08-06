import { resolveFieldHelp } from './fieldHelpCatalog.js'
import '../styles/contextualFieldHelp.scss'

const eligibleSelector = [
	'input:not([type="hidden"]):not([type="button"]):not([type="submit"]):not([type="reset"])',
	'textarea',
	'select',
	'[role="combobox"]',
].join(', ')

let fieldHelpId = 0

const cleanText = (element) => {
	if (!element) return ''
	const clone = element.cloneNode(true)
	clone.querySelectorAll('.help-hint, .employees-field-help, .visually-hidden').forEach(item => item.remove())
	return String(clone.textContent || '').replace(/\s+/g, ' ').trim()
}

const labelFor = (control, root) => {
	const ariaLabel = control.getAttribute('aria-label')
	if (ariaLabel) return ariaLabel

	const id = control.getAttribute('id')
	if (id) {
		const matchingLabel = [...root.querySelectorAll('label[for]')]
			.find(label => label.getAttribute('for') === id)
		const text = cleanText(matchingLabel)
		if (text) return text
	}

	const wrappingLabel = control.closest('label')
	const wrappingText = cleanText(wrappingLabel)
	if (wrappingText) return wrappingText

	const field = control.closest('.input-field, .form-field, .field, .form-group, .setting-item, td')
	const fieldLabel = field?.querySelector('label, legend, .input-field__label, .label, th')
	const fieldText = cleanText(fieldLabel)
	if (fieldText) return fieldText

	const placeholder = control.getAttribute('placeholder')
	if (placeholder) return placeholder

	return String(control.getAttribute('name') || control.getAttribute('id') || '')
		.replace(/[_-]+/g, ' ')
		.trim()
}

const representative = (control) => {
	const select = control.closest('.v-select, .multiselect')
	if (select) return select
	return control
}

const insertionAnchor = (control, marker) => {
	if (marker !== control) return marker
	const component = control.closest('.input-field, .textarea, .checkbox-radio-switch, .radio-switch')
	if (component) return component
	const label = control.closest('label')
	if (label) return label
	return control
}

const addDescription = (control, id) => {
	const ids = new Set(String(control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean))
	ids.add(id)
	control.setAttribute('aria-describedby', [...ids].join(' '))
}

const positionTooltip = (button, tooltip) => {
	const viewportPadding = 12
	const gap = 6
	const buttonRect = button.getBoundingClientRect()
	const maxWidth = Math.min(320, window.innerWidth - (viewportPadding * 2))

	tooltip.style.width = `${maxWidth}px`
	tooltip.style.left = `${viewportPadding}px`
	tooltip.style.top = `${viewportPadding}px`
	const tooltipRect = tooltip.getBoundingClientRect()
	const above = buttonRect.top - tooltipRect.height - gap
	const below = buttonRect.bottom + gap
	const top = above >= viewportPadding ? above : below
	const centeredLeft = buttonRect.left + (buttonRect.width / 2) - (tooltipRect.width / 2)
	const left = Math.min(
		Math.max(viewportPadding, centeredLeft),
		window.innerWidth - tooltipRect.width - viewportPadding,
	)

	tooltip.style.left = `${left}px`
	tooltip.style.top = `${Math.min(top, window.innerHeight - tooltipRect.height - viewportPadding)}px`
}

const createHelp = (control, marker, root) => {
	if (marker.dataset.employeesFieldHelp === 'true') return
	if (marker.closest('[data-employees-field-help-ignore="true"]')) return
	if (control.closest('.employees-field-help')) return

	const anchor = insertionAnchor(control, marker)
	if (!anchor?.parentElement) return
	const label = labelFor(control, root)
	const content = resolveFieldHelp(label, control)
	fieldHelpId += 1
	const shortId = `employees-field-help-short-${fieldHelpId}`
	const tooltipId = `employees-field-help-tooltip-${fieldHelpId}`
	const row = document.createElement('span')
	row.className = 'employees-field-help'
	row.dataset.employeesFieldHelpGenerated = 'true'

	const short = document.createElement('span')
	short.id = shortId
	short.className = 'employees-field-help__short'
	short.textContent = content.short
	row.append(short)
	addDescription(control, shortId)

	const nearbyScope = anchor.closest('label, .form-field, .field, .form-group, td') || anchor.parentElement
	const existingHint = nearbyScope?.querySelector('.help-hint')
	if (existingHint) {
		const existingTooltip = existingHint.querySelector('.help-hint__tooltip')
		if (existingTooltip && existingTooltip.dataset.employeesExampleAdded !== 'true') {
			existingTooltip.append(document.createTextNode(` ${content.exampleText}`))
			existingTooltip.dataset.employeesExampleAdded = 'true'
		}
	} else {
		const button = document.createElement('button')
		button.type = 'button'
		button.className = 'employees-field-help__trigger'
		button.textContent = '?'
		button.setAttribute('aria-label', content.accessibleLabel)
		button.setAttribute('aria-expanded', 'false')
		button.setAttribute('aria-controls', tooltipId)

		const tooltip = document.createElement('span')
		tooltip.id = tooltipId
		tooltip.className = 'employees-field-help__tooltip'
		tooltip.setAttribute('role', 'tooltip')
		tooltip.textContent = content.detail
		tooltip.hidden = true
		row.append(button, tooltip)

		let pinned = false
		let focused = false
		const open = () => {
			tooltip.hidden = false
			button.setAttribute('aria-expanded', 'true')
			window.requestAnimationFrame(() => positionTooltip(button, tooltip))
		}
		const close = () => {
			tooltip.hidden = true
			button.setAttribute('aria-expanded', 'false')
		}

		row.addEventListener('mouseenter', open)
		row.addEventListener('mouseleave', () => {
			if (!pinned && !focused) close()
		})
		button.addEventListener('focus', () => {
			focused = true
			open()
		})
		button.addEventListener('blur', () => {
			focused = false
			if (!pinned) close()
		})
		button.addEventListener('click', () => {
			pinned = !pinned
			if (pinned) open()
			else close()
		})
		button.addEventListener('keydown', event => {
			if (event.key === 'Escape') {
				pinned = false
				close()
				button.focus()
			}
		})
	}

	anchor.insertAdjacentElement('afterend', row)
	marker.dataset.employeesFieldHelp = 'true'
}

const enhance = (root) => {
	root.querySelectorAll(eligibleSelector).forEach(control => {
		const marker = representative(control)
		createHelp(control, marker, root)
	})
}

export const startContextualFieldHelp = (rootSelector) => {
	const root = typeof rootSelector === 'string' ? document.querySelector(rootSelector) : rootSelector
	if (!root) return () => {}

	let frame = null
	const schedule = () => {
		if (frame !== null) return
		frame = window.requestAnimationFrame(() => {
			frame = null
			enhance(root)
		})
	}
	const observer = new MutationObserver(schedule)
	observer.observe(root, { childList: true, subtree: true })
	schedule()

	return () => {
		observer.disconnect()
		if (frame !== null) window.cancelAnimationFrame(frame)
	}
}

export { eligibleSelector }
