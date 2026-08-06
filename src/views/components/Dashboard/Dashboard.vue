<template>
	<NcAppContent :name="t('employees', 'Employees dashboard')">
		<div class="dashboard">
			<section class="hero">
				<div class="hero-main">
					<p class="kicker">
						{{ t('employees', 'ERP for Nextcloud') }}
					</p>

					<h1>{{ greeting }}</h1>

					<p class="hero-text">
						{{ t('employees', 'Manage employees, teams, departments, time reports, absences, savings and IT assets from one workspace.') }}
					</p>

					<div class="hero-actions">
						<NcButton
							v-if="isAdmin"
							type="primary"
							@click="go('Employees')">
							<template #icon>
								<BadgeAccountAlert :size="20" />
							</template>
							{{ t('employees', 'Manage employees') }}
						</NcButton>

						<NcButton
							v-if="timeReportsEnabled"
							@click="go('Reports')">
							<template #icon>
								<CalendarClock :size="20" />
							</template>
							{{ t('employees', 'Report time') }}
						</NcButton>

						<NcButton
							v-if="inventoryEnabled && isAdmin"
							@click="go('Inventory')">
							<template #icon>
								<Laptop :size="20" />
							</template>
							{{ t('employees', 'IT Inventory') }}
						</NcButton>
					</div>
				</div>

				<div class="hero-side">
					<div class="date-card">
						<span>{{ t('employees', 'Today') }}</span>
						<strong>{{ currentDateLabel }}</strong>
					</div>

					<div class="workspace-card">
						<div class="workspace-icon">
							<AccountGroup :size="36" />
						</div>

						<div>
							<span>{{ t('employees', 'Workspace') }}</span>
							<strong>{{ t('employees', 'Human Resources') }}</strong>
						</div>
					</div>

					<div class="hero-stats">
						<div>
							<span>{{ t('employees', 'Modules') }}</span>
							<strong>{{ enabledModules }}</strong>
						</div>

						<div>
							<span>{{ t('employees', 'Role') }}</span>
							<strong>{{ isAdmin ? t('employees', 'Admin') : t('employees', 'User') }}</strong>
						</div>
					</div>
				</div>
			</section>

			<section class="kpi-grid">
				<div
					v-for="item in kpis"
					:key="item.key"
					class="kpi-card">
					<div class="kpi-icon">
						<component :is="item.icon" :size="24" />
					</div>

					<div class="kpi-body">
						<span>{{ item.label }}</span>
						<strong>{{ loading ? '...' : item.value }}</strong>
						<small>{{ item.description }}</small>
					</div>
				</div>
			</section>

			<NcNoteCard
				v-if="!isAdmin"
				type="info"
				class="notice">
				{{ t('employees', 'This dashboard shows the options available for your user. Administrative metrics are only available for administrators or Human Resources users.') }}
			</NcNoteCard>

			<section class="layout">
				<div class="panel apps-panel">
					<div class="panel-header">
						<div>
							<p class="section-label">
								{{ t('employees', 'Applications') }}
							</p>
							<h2>{{ t('employees', 'Business apps') }}</h2>
						</div>

						<NcButton
							v-if="isAdmin"
							:aria-label="t('employees', 'Refresh')"
							@click="loadData">
							<template #icon>
								<Reload :size="20" />
							</template>
						</NcButton>
					</div>

					<div class="app-grid">
						<button
							v-for="action in quickActions"
							:key="action.route"
							type="button"
							class="app-tile"
							@click="go(action.route)">
							<span class="app-icon">
								<component :is="action.icon" :size="30" />
							</span>

							<span class="app-title">{{ action.title }}</span>
							<span class="app-description">{{ action.description }}</span>
						</button>
					</div>
				</div>

				<div class="right-column">
					<TeamSupportDashboardWidget v-if="showEquipmentSupportWidget" class="panel" />

					<div
						v-if="timeReportsEnabled"
						class="panel today-panel"
						:class="todayReportClass">
						<div class="panel-header compact">
							<div>
								<p class="section-label">
									{{ t('employees', 'Today') }}
								</p>
								<h2>{{ t('employees', 'Time report') }}</h2>
							</div>
						</div>

						<div class="today-status">
							<div class="today-icon">
								<CalendarClock :size="26" />
							</div>

							<div>
								<strong>{{ todayReportLabel }}</strong>
								<span>{{ todayReportDescription }}</span>
							</div>
						</div>

						<div class="hours-box">
							<span>{{ t('employees', 'Reported hours') }}</span>
							<strong>{{ todayHours }} h</strong>
						</div>

						<NcButton
							wide
							type="primary"
							@click="go('Reports')">
							{{ t('employees', 'Open reports') }}
						</NcButton>
					</div>

					<div class="panel status-panel">
						<div class="panel-header compact">
							<div>
								<p class="section-label">
									{{ t('employees', 'Overview') }}
								</p>
								<h2>{{ t('employees', 'Organization') }}</h2>
							</div>
						</div>

						<div v-if="loading" class="empty-state">
							<NcLoadingIcon :size="32" />
							<span>{{ t('employees', 'Loading') }}</span>
						</div>

						<div v-else-if="isAdmin" class="status-list">
							<div
								v-for="item in structureItems"
								:key="item.label"
								class="status-row">
								<div>
									<strong>{{ item.label }}</strong>
									<span>{{ item.description }}</span>
								</div>

								<b>{{ item.value }}</b>
							</div>
						</div>

						<div v-else class="empty-state">
							<span>{{ t('employees', 'No administrative metrics available for this profile.') }}</span>
						</div>
					</div>
				</div>
			</section>

			<section class="panel modules-panel">
				<div class="panel-header">
					<div>
						<p class="section-label">
							{{ t('employees', 'Modules') }}
						</p>
						<h2>{{ t('employees', 'Installed modules') }}</h2>
					</div>
				</div>

				<div class="module-grid">
					<div
						v-for="module in modules"
						:key="module.key"
						class="module-card"
						:class="{ disabled: !module.enabled }">
						<div class="module-icon">
							<component :is="module.icon" :size="24" />
						</div>

						<div class="module-info">
							<strong>{{ module.title }}</strong>
							<p>{{ module.description }}</p>
						</div>

						<span class="module-state" :class="{ enabled: module.enabled }">
							{{ module.enabled ? t('employees', 'Enabled') : t('employees', 'Disabled') }}
						</span>
					</div>
				</div>
			</section>
		</div>
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

import {
	NcAppContent,
	NcButton,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import AccountTieOutline from 'vue-material-design-icons/AccountTieOutline.vue'
import BadgeAccountAlert from 'vue-material-design-icons/BadgeAccountAlert.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import CalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import Laptop from 'vue-material-design-icons/Laptop.vue'
import ViewList from 'vue-material-design-icons/ViewList.vue'

import inventoryService from '../../../services/inventoryService.js'
import permissionsMixin from '../../../mixins/permissions.js'
import TeamSupportDashboardWidget from '../../../Dashboard/TeamSupportDashboardWidget.vue'
import { nextcloudLocale } from '../../../utils/nextcloudLocale.js'

export default {
	name: 'Dashboard',

	components: {
		NcAppContent,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		AccountGroup,
		AccountTieOutline,
		BadgeAccountAlert,
		Bank,
		CalendarBlank,
		CalendarClock,
		OfficeBuilding,
		Reload,
		Laptop,
		TeamSupportDashboardWidget,
		ViewList,
	},

	mixins: [permissionsMixin],

	inject: {
		groupuser: { default: () => ({}) },
		Settings: { default: () => ({}) },
		employee: { default: () => [] },
		subordinates: { default: () => [] },
	},

	data() {
		return {
			loading: false,
			loadingToday: false,
			Employee: [],
			areas: [],
			Position: [],
			Team: [],
			inventoryTotal: 0,
			estadoHoy: null,
		}
	},

	computed: {
		isAdmin() {
			return this.hasGroup('admin') || this.hasGroup('hr')
		},

		currentEmployee() {
			if (Array.isArray(this.employee)) {
				return this.employee[0] || {}
			}

			return this.employee || {}
		},

		employees() {
			return this.extractPlainArray(this.Employee)
		},

		greeting() {
			const name = this.currentEmployee?.displayname
				|| this.currentEmployee?.id_user
				|| this.currentEmployee?.uid
				|| ''

			if (name) {
				return t('employees', 'Welcome, {name}', { name })
			}

			return t('employees', 'Employees workspace')
		},

		currentDateLabel() {
			return new Intl.DateTimeFormat(nextcloudLocale(), {
				weekday: 'long',
				day: '2-digit',
				month: 'short',
			}).format(new Date())
		},

		timeReportsEnabled() {
			return this.isTruthy(this.Settings?.modulo_reporte_tiempos)
		},

		absencesEnabled() {
			return this.isTruthy(this.Settings?.modulo_ausencias)
		},

		savingsEnabled() {
			return this.isTruthy(this.Settings?.modulo_savings)
		},

		customersEnabled() {
			return this.isTruthy(this.Settings?.modulo_clients)
		},

		inventoryEnabled() {
			return this.isTruthy(this.Settings?.modulo_inventario)
				|| this.isTruthy(this.Settings?.modulo_soporte)
		},

		showEquipmentSupportWidget() {
			return this.isTruthy(this.Settings?.modulo_inventario)
				&& this.canSeeAny(['inventario', 'soporte'])
		},

		enabledModules() {
			return this.modules.filter((module) => module.enabled).length
		},

		todayHours() {
			return Number(this.estadoHoy?.horas_reportadas || 0).toFixed(2)
		},

		todayReportLabel() {
			const status = this.estadoHoy?.status

			if (this.loadingToday) {
				return t('employees', 'Loading...')
			}

			if (status === 'reportado') {
				return t('employees', 'Reported')
			}

			if (status === 'sin_empleado') {
				return t('employees', 'No employee profile')
			}

			return t('employees', 'Pending')
		},

		todayReportDescription() {
			const status = this.estadoHoy?.status

			if (status === 'reportado') {
				return t('employees', 'Your time report for today is complete.')
			}

			if (status === 'sin_empleado') {
				return t('employees', 'Your user is not linked to an employee profile.')
			}

			return t('employees', 'You still have pending time to report today.')
		},

		todayReportClass() {
			const status = this.estadoHoy?.status

			if (status === 'reportado') {
				return 'is-ok'
			}

			if (status === 'sin_empleado') {
				return 'is-warning'
			}

			return 'is-pending'
		},

		kpis() {
			const base = [
				{
					key: 'employees',
					label: t('employees', 'Employees'),
					value: this.isAdmin ? this.employees.length : '-',
					description: t('employees', 'Registered profiles'),
					icon: BadgeAccountAlert,
				},
				{
					key: 'departments',
					label: t('employees', 'Departments'),
					value: this.isAdmin ? this.areas.length : '-',
					description: t('employees', 'Company areas'),
					icon: OfficeBuilding,
				},
				{
					key: 'teams',
					label: t('employees', 'Teams'),
					value: this.isAdmin ? this.Team.length : '-',
					description: t('employees', 'Work groups'),
					icon: AccountGroup,
				},
				{
					key: 'positions',
					label: t('employees', 'Positions'),
					value: this.isAdmin ? this.Position.length : '-',
					description: t('employees', 'Defined roles'),
					icon: AccountTieOutline,
				},
			]

			if (this.showEquipmentSupportWidget && this.isAdmin) {
				base.push({
					key: 'devices',
					label: t('employees', 'Devices'),
					value: this.inventoryTotal,
					description: t('employees', 'IT assets'),
					icon: Laptop,
				})
			}

			return base
		},

		quickActions() {
			const actions = []

			if (this.isAdmin) {
				actions.push(
					{
						route: 'Employees',
						title: t('employees', 'Employees'),
						description: t('employees', 'Manage employee files'),
						icon: BadgeAccountAlert,
					},
					{
						route: 'Areas',
						title: t('employees', 'Departments'),
						description: t('employees', 'Manage company areas'),
						icon: OfficeBuilding,
					},
					{
						route: 'Positions',
						title: t('employees', 'Positions'),
						description: t('employees', 'Manage job positions'),
						icon: AccountTieOutline,
					},
					{
						route: 'Teams',
						title: t('employees', 'Teams'),
						description: t('employees', 'Manage work teams'),
						icon: AccountGroup,
					},
				)
			}

			if (this.inventoryEnabled && this.isAdmin) {
				actions.push({
					route: 'Inventory',
					title: t('employees', 'IT Inventory'),
					description: t('employees', 'Devices, models and support'),
					icon: Laptop,
				})
			}

			if (this.absencesEnabled) {
				actions.push({
					route: 'Calendar',
					title: t('employees', 'Calendar'),
					description: t('employees', 'Vacations and absences'),
					icon: CalendarBlank,
				})
			}

			if (this.timeReportsEnabled) {
				actions.push({
					route: 'Reports',
					title: t('employees', 'Time Reports'),
					description: t('employees', 'Register work time'),
					icon: CalendarClock,
				})
			}

			if (this.savingsEnabled) {
				actions.push({
					route: 'SavingsRequest',
					title: t('employees', 'Savings'),
					description: t('employees', 'Savings requests'),
					icon: Bank,
				})
			}

			if (this.customersEnabled && this.isAdmin) {
				actions.push({
					route: 'CompaniesGroups',
					title: t('employees', 'Customers'),
					description: t('employees', 'Companies and groups'),
					icon: ViewList,
				})
			}

			return actions
		},

		structureItems() {
			return [
				{
					label: t('employees', 'Registered employees'),
					value: this.employees.length,
					description: t('employees', 'Employee profiles available in the module'),
				},
				{
					label: t('employees', 'Departments with records'),
					value: this.countWithEmployees(this.areas),
					description: t('employees', 'Departments currently linked to employees'),
				},
				{
					label: t('employees', 'Teams with members'),
					value: this.countWithEmployees(this.Team),
					description: t('employees', 'Teams currently linked to employees'),
				},
				{
					label: t('employees', 'IT devices'),
					value: this.showEquipmentSupportWidget ? this.inventoryTotal : '-',
					description: t('employees', 'Registered company devices'),
				},
			]
		},

		modules() {
			return [
				{
					key: 'human-resources',
					title: t('employees', 'Human Resources'),
					description: t('employees', 'Employees, departments, positions and teams.'),
					icon: BadgeAccountAlert,
					enabled: true,
				},
				{
					key: 'time-reports',
					title: t('employees', 'Time Reports'),
					description: t('employees', 'Work time reports by client and activity.'),
					icon: CalendarClock,
					enabled: this.timeReportsEnabled,
				},
				{
					key: 'absences',
					title: t('employees', 'Vacations and Absences'),
					description: t('employees', 'Vacation calendar and absence control.'),
					icon: CalendarBlank,
					enabled: this.absencesEnabled,
				},
				{
					key: 'savings',
					title: t('employees', 'Savings'),
					description: t('employees', 'Employee savings requests and admin panel.'),
					icon: Bank,
					enabled: this.savingsEnabled,
				},
				{
					key: 'customers',
					title: t('employees', 'Customers'),
					description: t('employees', 'Companies, groups and activities.'),
					icon: ViewList,
					enabled: this.customersEnabled,
				},
				{
					key: 'inventory',
					title: t('employees', 'IT Inventory'),
					description: t('employees', 'Computer equipment, models and support history.'),
					icon: Laptop,
					enabled: this.inventoryEnabled,
				},
			]
		},
	},

	mounted() {
		this.loadData()

		if (this.timeReportsEnabled) {
			this.loadTodayReport()
		}
	},

	methods: {
		t,

		go(routeName) {
			this.$router.push({ name: routeName })
		},

		hasGroup(groupName) {
			if (!groupName || !this.groupuser) {
				return false
			}

			if (Array.isArray(this.groupuser)) {
				return this.groupuser.includes(groupName)
					|| this.groupuser.some((group) => {
						return group?.id === groupName
							|| group?.gid === groupName
							|| group?.name === groupName
					})
			}

			if (typeof this.groupuser === 'object') {
				return Object.prototype.hasOwnProperty.call(this.groupuser, groupName)
					|| this.groupuser[groupName] === true
					|| Object.values(this.groupuser).includes(groupName)
			}

			return false
		},

		isTruthy(value) {
			return value === true
				|| value === 'true'
				|| value === 1
				|| value === '1'
		},

		async loadData() {
			if (!this.isAdmin) {
				return
			}

			this.loading = true

			try {
				const [Employee, areas, Position, Team] = await Promise.all([
					axios.get(generateUrl('/apps/employees/GetEmpleadosList')),
					axios.get(generateUrl('/apps/employees/GetAreasList')),
					axios.get(generateUrl('/apps/employees/GetPositionsList')),
					axios.get(generateUrl('/apps/employees/GetTeamsList')),
				])

				this.Employee = this.extractArray(Employee, 'Empleados')
				this.areas = this.extractArray(areas)
				this.Position = this.extractArray(Position)
				this.Team = this.extractArray(Team)

				if (this.showEquipmentSupportWidget) {
					await this.loadInventorySummary()
				}
			} catch (err) {
				this.resetAdminData()
			} finally {
				this.loading = false
			}
		},

		async loadInventorySummary() {
			if (!this.showEquipmentSupportWidget) return
			try {
				const response = await inventoryService.getTeams({ limit: 1, offset: 0 })
				this.inventoryTotal = Number(response?.total || 0)
			} catch (err) {
				this.inventoryTotal = 0
			}
		},

		async loadTodayReport() {
			this.loadingToday = true

			try {
				const response = await axios.get(generateUrl('/apps/employees/estadoReporteHoy'))
				this.estadoHoy = response?.data?.ocs?.data ?? response?.data ?? null
			} catch (err) {
				this.estadoHoy = null
			} finally {
				this.loadingToday = false
			}
		},

		extractArray(response, key = null) {
			const data = response?.data?.ocs?.data ?? response?.data ?? []

			if (key && Array.isArray(data?.[key])) {
				return data[key]
			}

			if (Array.isArray(data?.data)) {
				return data.data
			}

			if (Array.isArray(data)) {
				return data
			}

			if (data && typeof data === 'object') {
				return Object.values(data)
			}

			return []
		},

		countWithEmployees(items) {
			return this.extractPlainArray(items).filter((item) => {
				return Number(item.employee_count || item.total_empleados || 0) > 0
			}).length
		},

		extractPlainArray(data) {
			if (Array.isArray(data)) {
				return data
			}

			if (data && typeof data === 'object') {
				return Object.values(data)
			}

			return []
		},

		resetAdminData() {
			this.Employee = []
			this.areas = []
			this.Position = []
			this.Team = []
			this.inventoryTotal = 0
		},
	},
}
</script>

<style scoped>
.dashboard {
	--erp-primary: var(--color-primary-element);
	--erp-primary-dark: var(--color-primary-element-hover);
	--erp-secondary: var(--color-primary-element);
	--erp-soft: var(--color-primary-element-light);

	display: flex;
	flex-direction: column;
	gap: 18px;
	width: 100%;
	min-height: 100%;
	padding: 24px 32px 40px;
	background: var(--color-main-background);
}

.hero {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 340px;
	gap: 18px;
	padding: 28px;
	border: 1px solid var(--color-border);
	border-radius: 26px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	box-shadow: none;
}

.hero-main {
	display: flex;
	flex-direction: column;
	justify-content: center;
	min-width: 0;
}

.kicker {
	margin: 0 0 8px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 800;
	letter-spacing: .08em;
	text-transform: uppercase;
}

.hero h1 {
	margin: 0;
	font-size: clamp(30px, 4vw, 46px);
	font-weight: 850;
	line-height: 1.05;
}

.hero-text {
	max-width: 800px;
	margin: 12px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 15px;
	line-height: 1.55;
}

.hero-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	margin-top: 22px;
}

.hero-side {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.date-card,
.workspace-card,
.hero-stats > div {
	border: 1px solid var(--color-border);
	border-radius: 20px;
	background: var(--color-background-hover);
}

.date-card {
	padding: 14px 16px;
}

.workspace-card {
	display: flex;
	align-items: center;
	gap: 14px;
	padding: 16px;
}

.workspace-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 58px;
	height: 58px;
	border-radius: 18px;
	background: var(--color-primary-element-light);
}

.date-card span,
.workspace-card span,
.hero-stats span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
}

.date-card strong,
.workspace-card strong,
.hero-stats strong {
	display: block;
	margin-top: 4px;
	color: var(--color-main-text);
	font-size: 18px;
	font-weight: 850;
}

.hero-stats {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
}

.hero-stats > div {
	padding: 14px;
}

.kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: 14px;
}

.kpi-card,
.panel {
	border: 1px solid var(--color-border);
	border-radius: 22px;
	background: var(--color-main-background);
	box-shadow: none;
}

.kpi-card {
	display: flex;
	align-items: center;
	gap: 14px;
	min-width: 0;
	padding: 16px;
}

.kpi-icon,
.app-icon,
.module-icon,
.today-icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	width: 48px;
	height: 48px;
	border-radius: 17px;
	background: var(--erp-soft);
	color: var(--erp-primary);
}

.kpi-body {
	min-width: 0;
}

.kpi-body span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
}

.kpi-body strong {
	display: block;
	margin-top: 2px;
	color: var(--color-main-text);
	font-size: 29px;
	font-weight: 850;
	line-height: 1;
}

.kpi-body small {
	display: block;
	margin-top: 5px;
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.notice {
	margin: 0;
}

.layout {
	display: grid;
	grid-template-columns: minmax(0, 1.4fr) minmax(340px, .6fr);
	gap: 18px;
}

.panel {
	padding: 18px;
}

.right-column {
	display: flex;
	flex-direction: column;
	gap: 18px;
}

.panel-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 14px;
	margin-bottom: 16px;
}

.panel-header.compact {
	margin-bottom: 12px;
}

.section-label {
	margin: 0 0 4px;
	color: var(--erp-secondary);
	font-size: 12px;
	font-weight: 850;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.panel h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 20px;
	font-weight: 850;
}

.app-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
	gap: 14px;
}

.app-tile {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 10px;
	min-height: 148px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: 20px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
	text-align: left;
	transition:
		transform 140ms ease,
		box-shadow 140ms ease,
		border-color 140ms ease,
		background 140ms ease;
}

.app-tile:hover,
.app-tile:focus {
	border-color: rgb(from var(--color-primary-element) r g b / 0.45);
	background: linear-gradient(180deg, var(--color-main-background), var(--erp-soft));
	box-shadow: 0 12px 26px rgb(from var(--color-box-shadow) r g b / 0.11);
	transform: translateY(-2px);
	outline: none;
}

.app-title {
	display: block;
	font-size: 15px;
	font-weight: 850;
}

.app-description {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
}

.today-panel {
	position: relative;
	overflow: hidden;
}

.today-panel::before {
	position: absolute;
	top: 0;
	left: 0;
	width: 5px;
	height: 100%;
	content: "";
	background: var(--erp-primary);
}

.today-panel.is-ok::before {
	background: var(--color-success);
}

.today-panel.is-pending::before {
	background: var(--color-error);
}

.today-panel.is-warning::before {
	background: var(--color-warning);
}

.today-status {
	display: flex;
	gap: 12px;
	align-items: flex-start;
	margin-bottom: 14px;
}

.today-status strong {
	display: block;
	color: var(--color-main-text);
	font-size: 16px;
	font-weight: 850;
}

.today-status span {
	display: block;
	margin-top: 4px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
}

.hours-box {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px;
	margin-bottom: 14px;
	border-radius: 16px;
	background: var(--color-background-hover);
}

.hours-box span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
}

.hours-box strong {
	color: var(--color-main-text);
	font-size: 20px;
	font-weight: 850;
}

.status-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.status-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 14px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: 18px;
	background: var(--color-background-hover);
}

.status-row strong {
	display: block;
	color: var(--color-main-text);
	font-size: 14px;
}

.status-row span {
	display: block;
	margin-top: 3px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
}

.status-row b {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	min-width: 42px;
	height: 42px;
	border-radius: 14px;
	background: var(--erp-soft);
	color: var(--erp-primary);
	font-size: 20px;
}

.empty-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 180px;
	gap: 10px;
	color: var(--color-text-maxcontrast);
	font-weight: 700;
	text-align: center;
}

.module-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
	gap: 12px;
}

.module-card {
	position: relative;
	display: flex;
	align-items: flex-start;
	gap: 12px;
	min-width: 0;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: 18px;
	background: var(--color-background-hover);
}

.module-card.disabled {
	opacity: .64;
}

.module-info {
	min-width: 0;
	padding-right: 86px;
}

.module-info strong {
	display: block;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 850;
}

.module-info p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
}

.module-state {
	position: absolute;
	top: 12px;
	right: 12px;
	padding: 4px 9px;
	border-radius: 999px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 850;
}

.module-state.enabled {
	background: rgb(from var(--color-primary-element-light) r g b / 0.12);
	color: var(--erp-secondary);
}

@media (max-width: 1180px) {
	.hero,
	.layout {
		grid-template-columns: 1fr;
	}

	.hero-side {
		display: grid;
		grid-template-columns: 1fr 1fr;
	}

	.date-card {
		grid-column: 1 / -1;
	}
}

@media (max-width: 768px) {
	.dashboard {
		padding: 14px;
	}

	.hero {
		padding: 20px;
		border-radius: 20px;
	}

	.hero-side {
		grid-template-columns: 1fr;
	}

	.date-card {
		grid-column: auto;
	}

	.hero-stats {
		grid-template-columns: 1fr;
	}

	.app-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 520px) {
	.kpi-grid,
	.app-grid,
	.module-grid {
		grid-template-columns: 1fr;
	}
}
</style>
