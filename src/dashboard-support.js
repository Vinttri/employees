import Vue from 'vue'
import TeamSupportDashboardWidget from './Dashboard/TeamSupportDashboardWidget.vue'

export const SUPPORT_WIDGET_ID = 'employees-team-support'

const registerWidget = () => {
	if (typeof window.OCA?.Dashboard?.register !== 'function') {
		console.error('[Employee] La API del Dashboard de Nextcloud no está disponible.')
		return
	}

	window.OCA.Dashboard.register(SUPPORT_WIDGET_ID, (el) => {
		const View = Vue.extend(TeamSupportDashboardWidget)
		new View().$mount(el)
	})
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', registerWidget, { once: true })
} else {
	registerWidget()
}
