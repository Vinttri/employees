<template>
	<NcAppNavigation class="employees-side-navigation" :class="[`employees-side-navigation--${navigationMode}`]">
		<button class="side-toggle-button side-toggle-button--floating"
			type="button"
			:title="navigationModeLabel"
			:aria-label="navigationModeLabel"
			@click="toggleNavigationMode">
			<span class="side-toggle-icon">
				<span />
				<span />
				<span />
			</span>
		</button>

		<div v-show="navigationMode !== 'hidden'" class="side-navigation-content">
			<!-- General -->
			<NcAppNavigationCaption v-if="navigationMode === 'normal'"
				:heading-id="t('employees', 'General')"
				is-heading
				:name="t('employees', 'General')" />

			<NcAppNavigationList :aria-labelledby="t('employees', 'General')">
				<NcAppNavigationItem :name="t('employees', 'Home')" :to="{ name: 'Home' }" exact>
					<template #icon>
						<ViewDashboard :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>

			<!-- Human Resources -->
			<div v-if="canSeeHumanResources">
				<NcAppNavigationCaption v-if="navigationMode === 'normal'"
					:heading-id="t('employees', 'Human Resources')"
					is-heading
					:name="t('employees', 'Human Resources')" />

				<NcAppNavigationList :aria-labelledby="t('employees', 'Human Resources')">
					<NcAppNavigationItem :name="t('employees', 'Employees')" :to="{ name: 'Employees' }">
						<template #icon>
							<BadgeAccountAlert :size="20" />
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem :name="t('employees', 'Areas / Departments')" :to="{ name: 'Areas' }">
						<template #icon>
							<OfficeBuilding :size="20" />
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem :name="t('employees', 'Positions')" :to="{ name: 'Positions' }">
						<template #icon>
							<AccountTieOutline :size="20" />
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem :name="t('employees', 'Teams')" :to="{ name: 'Teams' }">
						<template #icon>
							<AccountGroup :size="20" />
						</template>
					</NcAppNavigationItem>
				</NcAppNavigationList>
			</div>

			<!-- Purchases -->
			<div v-if="canSeePurchases">
				<NcAppNavigationCaption v-if="navigationMode === 'normal'"
					:heading-id="t('employees', 'Purchases')"
					is-heading
					:name="t('employees', 'Purchases')" />

				<NcAppNavigationList :aria-labelledby="t('employees', 'Purchases')">
					<NcAppNavigationItem :name="t('employees', 'Purchase requests')" :to="{ name: 'purchases' }">
						<template #icon>
							<CartOutline :size="20" />
						</template>
					</NcAppNavigationItem>
				</NcAppNavigationList>
			</div>

			<!-- IT Inventory -->
			<div v-if="canSeeInventory">
				<NcAppNavigationCaption v-if="navigationMode === 'normal'"
					:heading-id="t('employees', 'IT Management')"
					is-heading
					:name="t('employees', 'IT Management')" />

				<NcAppNavigationList :aria-labelledby="t('employees', 'IT Management')">
					<NcAppNavigationItem
						:name="t('employees', 'Inventory and support')"
						:to="{ name: 'Inventory' }"
						exact>
						<template #icon>
							<Laptop :size="20" />
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem v-if="canSeeMaintenance"
						:name="t('employees', 'Maintenance calendar')"
						:to="{ name: 'Maintenance' }">
						<template #icon>
							<CalendarMonth :size="20" />
						</template>
					</NcAppNavigationItem>
				</NcAppNavigationList>
			</div>

			<!-- Time Reports -->
			<div v-if="reportTimesEnabled">
				<NcAppNavigationCaption v-if="navigationMode === 'normal'"
					:heading-id="t('employees', 'Time Reports')"
					is-heading
					:name="t('employees', 'Time Reports')" />

				<NcAppNavigationList :aria-labelledby="t('employees', 'Time Reports')">
					<NcAppNavigationItem :name="t('employees', 'My reports')" :to="{ name: 'Reports' }">
						<template #icon>
							<CalendarClock :size="20" />
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem v-if="canSeeAdminReports"
						:name="t('employees', 'Admin reports')"
						:to="{ name: 'AdminReports' }">
						<template #icon>
							<FileChartOutline :size="20" />
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem v-if="canSeeAdminReports"
						:name="t('employees', 'Compliance tracking')"
						:to="{ name: 'report-compliance' }">
						<template #icon>
							<FileChartOutline :size="20" />
						</template>
					</NcAppNavigationItem>
				</NcAppNavigationList>
			</div>

			<!-- Savings -->
			<div v-if="savingsEnabled">
				<NcAppNavigationCaption v-if="navigationMode === 'normal'"
					:heading-id="t('employees', 'Savings')"
					is-heading
					:name="t('employees', 'Savings')" />

				<NcAppNavigationList :aria-labelledby="t('employees', 'Savings')">
					<NcAppNavigationItem :name="t('employees', 'Request')" :to="{ name: 'SavingsRequest' }">
						<template #icon>
							<FileSign :size="20" />
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem v-if="canSeeSavingsAdmin"
						:name="t('employees', 'Admin panel')"
						:to="{ name: 'SavingsPanel' }">
						<template #icon>
							<Bank :size="20" />
						</template>
					</NcAppNavigationItem>
				</NcAppNavigationList>
			</div>

			<!-- Working Time -->
			<div v-if="absencesEnabled">
				<NcAppNavigationCaption v-if="navigationMode === 'normal'"
					:heading-id="t('employees', 'Working time')"
					is-heading
					:name="t('employees', 'Working time')" />

				<NcAppNavigationList :aria-labelledby="t('employees', 'Working time')">
					<NcAppNavigationItem :name="t('employees', 'Calendar')" :to="{ name: 'Calendar' }">
						<template #icon>
							<CalendarBlank :size="20" />
						</template>
					</NcAppNavigationItem>
				</NcAppNavigationList>
			</div>

			<!-- Customers -->
			<div v-if="canSeeCustomers">
				<NcAppNavigationCaption v-if="navigationMode === 'normal'"
					:heading-id="t('employees', 'Customers')"
					is-heading
					:name="t('employees', 'Customers')" />

				<NcAppNavigationList :aria-labelledby="t('employees', 'Customers')">
					<NcAppNavigationItem :name="t('employees', 'Companies / Groups')" :to="{ name: 'CompaniesGroups' }">
						<template #icon>
							<HexagonMultipleOutline :size="20" />
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem :name="t('employees', 'Activities')" :to="{ name: 'Activities' }">
						<template #icon>
							<ViewList :size="20" />
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem v-if="canSeeAdminReports"
						:name="t('employees', 'Costs')"
						:to="{ name: 'Costs' }">
						<template #icon>
							<Cash :size="20" />
						</template>
					</NcAppNavigationItem>
				</NcAppNavigationList>
			</div>
		</div>
	</NcAppNavigation>
</template>

<script>
import HexagonMultipleOutline from 'vue-material-design-icons/HexagonMultipleOutline.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import BadgeAccountAlert from 'vue-material-design-icons/BadgeAccountAlert.vue'
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import AccountTieOutline from 'vue-material-design-icons/AccountTieOutline.vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import FileSign from 'vue-material-design-icons/FileSign.vue'
import ViewList from 'vue-material-design-icons/ViewList.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import FileChartOutline from 'vue-material-design-icons/FileChartOutline.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import CalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import Laptop from 'vue-material-design-icons/Laptop.vue'
import CartOutline from 'vue-material-design-icons/CartOutline.vue'
import Cash from 'vue-material-design-icons/Cash.vue'
import CalendarMonth from 'vue-material-design-icons/CalendarMonth.vue'

import {
	NcAppNavigation,
	NcAppNavigationItem,
	NcAppNavigationList,
	NcAppNavigationCaption,
} from '@nextcloud/vue'

import { translate as t } from '@nextcloud/l10n'
import permissionsMixin from '../../mixins/permissions.js'

const STORAGE_KEY = 'employees.sideNavigationMode'

export default {
	name: 'SideNavigation',
	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationList,
		NcAppNavigationCaption,
		AccountGroup,
		BadgeAccountAlert,
		OfficeBuilding,
		AccountTieOutline,
		ViewDashboard,
		FileSign,
		Bank,
		CalendarBlank,
		HexagonMultipleOutline,
		ViewList,
		FileChartOutline,
		CalendarClock,
		Laptop,
		CartOutline,
		Cash,
		CalendarMonth,
	},

	mixins: [permissionsMixin],

	inject: ['groupuser', 'Settings', 'subordinates'],

	data() {
		return {
			navigationMode: 'normal',
		}
	},

	computed: {
		navigationModeLabel() {
			if (this.navigationMode === 'normal') {
				return t('employees', 'Collapse navigation')
			}

			if (this.navigationMode === 'compact') {
				return t('employees', 'Hide navigation')
			}

			return t('employees', 'Show navigation')
		},

		canSeeHumanResources() {
			return this.canSeeAny([
				'employees.hr',
				'employees.admin',
			])
		},

		canSeeAdminReports() {
			return this.canSee('reporte_tiempos.admin')
				|| this.isTruthy(this.Settings?.CanAdminReports)
		},

		canSeeCustomers() {
			return this.canSee('Client')
		},

		canSeeInventory() {
			return this.canSee('inventario')
				|| this.canSee('soporte')
		},

		canSeeMaintenance() {
			return this.isModuleEnabled('modulo_inventario')
				&& this.canSee('inventario')
		},

		reportTimesEnabled() {
			return this.isModuleEnabled('modulo_reporte_tiempos')
		},

		savingsEnabled() {
			return this.isModuleEnabled('modulo_savings')
		},

		canSeeSavingsAdmin() {
			return this.canSee('savings.admin')
				|| this.canSeeAny([
					'employees.hr',
					'employees.admin',
				])
		},

		absencesEnabled() {
			return this.isModuleEnabled('modulo_ausencias')
		},

		canSeePurchases() {
			return this.canSee('purchases')
		},
	},

	watch: {
		navigationMode(value) {
			this.saveNavigationMode(value)
		},
	},

	mounted() {
		this.navigationMode = this.getSavedNavigationMode()
	},

	methods: {
		t,

		getSavedNavigationMode() {
			if (typeof window === 'undefined') {
				return 'normal'
			}

			try {
				const value = window.localStorage.getItem(STORAGE_KEY)

				return ['normal', 'compact', 'hidden'].includes(value)
					? value
					: 'normal'
			} catch (error) {
				return 'normal'
			}
		},

		saveNavigationMode(value) {
			if (typeof window === 'undefined') {
				return
			}

			try {
				window.localStorage.setItem(STORAGE_KEY, value)
			} catch (error) {
				// localStorage puede fallar en modo private o contextos restringidos.
			}
		},

		toggleNavigationMode() {
			const nextMode = {
				normal: 'compact',
				compact: 'hidden',
				hidden: 'normal',
			}

			this.navigationMode = nextMode[this.navigationMode] || 'normal'
		},

		isModuleEnabled(moduleName) {
			return this.isTruthy(this.Settings?.[moduleName])
		},
	},
}
</script>
<style scoped lang="scss">
.employees-side-navigation {
	position: relative;
	width: 300px !important;
	min-width: 300px !important;
	max-width: 300px !important;
	height: 100%;
	overflow: visible !important;
	transition:
		width 160ms ease,
		min-width 160ms ease,
		max-width 160ms ease;
}

/* Oculta el toggle interno de Nextcloud para evitar doble botón */
.employees-side-navigation :deep(.app-navigation-toggle),
.employees-side-navigation :deep(.app-navigation__toggle),
.employees-side-navigation :deep(.app-navigation-toggle-wrapper),
.employees-side-navigation :deep(button.app-navigation-toggle) {
	display: none !important;
}

.side-toggle-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	padding: 0;
	border: 0;
	border-radius: 10px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
}

.side-toggle-button:hover,
.side-toggle-button:focus {
	background: var(--color-background-hover);
}

.side-toggle-button--floating {
	position: absolute;
	z-index: 50;
	top: 6px;
	right: -50px;
}

.side-toggle-icon {
	display: flex;
	flex-direction: column;
	gap: 4px;
	width: 18px;
}

.side-toggle-icon span {
	display: block;
	width: 18px;
	height: 2px;
	border-radius: 999px;
	background: currentColor;
}

.side-navigation-content {
	width: 100%;
	box-sizing: border-box;
}

/* ---------------- NORMAL ---------------- */
/* ya cubierto por .employees-side-navigation */

/* ---------------- COMPACT ---------------- */
.employees-side-navigation--compact {
	width: 72px !important;
	min-width: 72px !important;
	max-width: 72px !important;
}

.employees-side-navigation--compact .side-navigation-content {
	display: flex;
	flex-direction: column;
	align-items: center;
	width: 100%;
	padding-top: 5px;
}

.employees-side-navigation--compact :deep(.app-navigation-caption) {
	display: none !important;
}

.employees-side-navigation--compact :deep(.app-navigation-list) {
	width: 100%;
}

.employees-side-navigation--compact :deep(.app-navigation-entry) {
	width: 40px !important;
	min-width: auto !important;
	max-width: auto !important;
	margin-right: auto !important;
	margin-left: auto !important;
}

.employees-side-navigation--compact :deep(.app-navigation-entry__link) {
	justify-content: center !important;
	width: 48px !important;
	min-width: 48px !important;
	padding-right: 0 !important;
	padding-left: 0 !important;
}

.employees-side-navigation--compact :deep(.app-navigation-entry__icon) {
	margin: 0 !important;
}

.employees-side-navigation--compact :deep(.app-navigation-entry__utils),
.employees-side-navigation--compact :deep(.app-navigation-entry__counter),
.employees-side-navigation--compact :deep(.app-navigation-entry__title),
.employees-side-navigation--compact :deep(.app-navigation-entry__name),
.employees-side-navigation--compact :deep(.app-navigation-entry__text),
.employees-side-navigation--compact :deep(.app-navigation-entry__children),
.employees-side-navigation--compact :deep(.app-navigation-entry__caption) {
	display: none !important;
}

/* ---------------- HIDDEN ---------------- */
/* Aquí sí queda sin ocupar espacio */
.employees-side-navigation--hidden {
	width: 0 !important;
	min-width: 0 !important;
	max-width: 0 !important;
	border: 0 !important;
	background: transparent !important;
	overflow: visible !important;
}

.employees-side-navigation--hidden .side-navigation-content {
	display: none !important;
}

</style>
