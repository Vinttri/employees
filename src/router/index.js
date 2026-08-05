import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

import Employees from '../views/components/EmployeeList/Employees.vue'
import Calendar from '../views/components/TimeOff/TimeOff.vue'
import Teams from '../views/components/Teams/Teams.vue'
import Positions from '../views/components/positions/Positions.vue'
import Areas from '../views/components/areas/Areas.vue'
import SavingsRequest from '../views/components/savings/Request.vue'
import SavingsPanel from '../views/components/savings/SavingsPanel.vue'
import Dashboard from '../views/components/Dashboard/Dashboard.vue'
import CompaniesGroups from '../views/components/clients/CompaniesGroups.vue'
import Activities from '../views/components/clients/Activities.vue'
import Costs from '../views/components/costs/Costs.vue'
import Reports from '../views/components/reports/Reports.vue'
import AdminReports from '../views/components/reports/admin/AdminReports.vue'
import Example from '../views/components/example/Example.vue'
import QuickReport from '../views/components/reports/QuickReport.vue'
import ReportCompliance from '../views/components/reports/ReportCompliance.vue'
import Inventory from '../views/components/Inventory/Inventory.vue'
import MyRequestsPurchases from '../views/components/Purchases/MyRequests.vue'

Vue.use(Router)

export default new Router({
	mode: 'hash',
	linkActiveClass: 'active',
	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	base: generateUrl('/apps/employees', ''),
	routes: [
		{
			path: '/',
			component: Dashboard,
			name: 'Home',
		},
		{
			path: '/employees',
			component: Employees,
			name: 'Employees',
		},
		{
			path: '/Positions',
			component: Positions,
			name: 'Positions',
		},
		{
			path: '/Areas',
			component: Areas,
			name: 'Areas',
		},
		{
			path: '/Teams',
			component: Teams,
			name: 'Teams',
		},
		{
			path: '/calendar',
			component: Calendar,
			name: 'Calendar',
		},
		{
			path: '/Request',
			component: SavingsRequest,
			name: 'SavingsRequest',
		},
		{
			path: '/SavingsPanel',
			component: SavingsPanel,
			name: 'SavingsPanel',
		},
		{
			path: '/Activities',
			component: Activities,
			name: 'Activities',
		},
		{
			path: '/CompaniesGroups',
			component: CompaniesGroups,
			name: 'CompaniesGroups',
		},
		{
			path: '/Costs',
			component: Costs,
			name: 'Costs',
		},
		{
			path: '/Reports',
			component: Reports,
			name: 'Reports',
		},
		{
			path: '/AdminReports',
			component: AdminReports,
			name: 'AdminReports',
		},
		{
			path: '/example',
			component: Example,
			name: 'Example',
		},
		{
			path: '/quick-report',
			name: 'quick-report',
			component: QuickReport,
		},
		{
			path: '/report-compliance',
			name: 'report-compliance',
			component: ReportCompliance,
		},
		{
			path: '/Inventory',
			component: Inventory,
			name: 'Inventory',
		},
		{
			path: '/Inventory/Maintenance',
			name: 'Maintenance',
			component: () => import('../views/components/Inventory/Maintenance/MaintenanceView.vue'),
		},
		{
			path: '/Inventory/Maintenance/Grupos/:id',
			name: 'MaintenanceGroup',
			component: () => import('../views/components/Inventory/Maintenance/MaintenanceGroupDetails.vue'),
		},
		{
			path: '/Inventory/Maintenance/:id',
			name: 'MaintenanceDetalle',
			component: () => import('../views/components/Inventory/Maintenance/MaintenanceDetail.vue'),
		},
		{
			path: '/purchases',
			name: 'purchases',
			component: MyRequestsPurchases,
		},
	],
})
