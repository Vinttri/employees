import Vue from 'vue'
import DashboardReportsWidget from './Dashboard/DashboardReportsWidget.vue'

const registerWidget = () => {
	if (!window.OCA || !window.OCA.Dashboard) {
		return
	}

	window.OCA.Dashboard.register('empleados_reportes', (el) => {
		const View = Vue.extend(DashboardReportsWidget)
		new View().$mount(el)
	})
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', registerWidget)
} else {
	registerWidget()
}
