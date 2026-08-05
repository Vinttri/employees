<template>
	<NcAppContent :name="t('employees', 'Costs')">
		<div
			class="costs-tabs"
			role="tablist"
			:aria-label="t('employees', 'Cost module sections')">
			<button
				v-for="(tab, index) in tabs"
				:id="`costs-tab-${tab.id}`"
				ref="tabButtons"
				:key="tab.id"
				type="button"
				role="tab"
				:aria-selected="activeTab === tab.id ? 'true' : 'false'"
				:aria-controls="`costs-panel-${tab.id}`"
				:tabindex="activeTab === tab.id ? 0 : -1"
				:class="{ active: activeTab === tab.id }"
				@click="setActiveTab(tab.id)"
				@keydown="onTabKeydown($event, index)">
				{{ tab.label }}
			</button>
		</div>

		<section
			v-if="activeTab === 'resumen'"
			id="costs-panel-resumen"
			role="tabpanel"
			aria-labelledby="costs-tab-resumen">
			<List
				:loading="loading"
				:listas="leaderList"
				:select="selection"
				:defaultbuttons="false"
				:custom="true">
				<template #custombuttons>
					<div class="period-action">
						<NcButton
							:disabled="loading"
							:aria-label="t('employees', 'Configure period')"
							@click="periodModal = true">
							<template #icon>
								<CalendarRange :size="20" />
							</template>
							{{ t('employees', 'Period') }}
						</NcButton>
					</div>
				</template>

				<template #custom>
					<CostSummary
						:kpis="kpis"
						:leaders="participants"
						:employees="employeesAvailability"
						:period-label="periodLabel" />
				</template>

				<template #details>
					<CostDetails v-if="selectedLeader" :leader="selectedLeader" />
				</template>
			</List>
		</section>

		<section
			v-else-if="activeTab === 'planificacion'"
			id="costs-panel-planificacion"
			class="costs-panel"
			role="tabpanel"
			aria-labelledby="costs-tab-planificacion">
			<CostPlanning
				:scenario="activeScenario"
				:companies="companies"
				:activities="activities"
				:leaders="leaders"
				@update-scenario="updateScenario"
				@go-to-quote="goToQuote" />
		</section>

		<section
			v-else
			id="costs-panel-cotizacion"
			class="costs-panel"
			role="tabpanel"
			aria-labelledby="costs-tab-cotizacion">
			<CostQuote
				:scenarios="scenarios"
				:active-scenario-id="activeScenarioId"
				@update-scenario="updateScenario"
				@rename-scenario="renameScenario"
				@select-scenario="selectScenario"
				@duplicate-scenario="duplicateScenario"
				@delete-scenario="deleteScenario" />
		</section>

		<NcModal
			v-if="activeTab === 'resumen' && periodModal"
			:name="t('employees', 'Report configuration')"
			@close="periodModal = false">
			<div class="period-modal">
				<h2>{{ t('employees', 'Configure period') }}</h2>
				<div class="period-fields">
					<NcSelect
						v-model="draftStart"
						:options="months"
						label="label"
						:reduce="month => month.value"
						:input-label="t('employees', 'Start month')" />
					<NcSelect
						v-model="draftEnd"
						:options="months"
						label="label"
						:reduce="month => month.value"
						:input-label="t('employees', 'End month')" />
					<NcSelect
						v-model="draftYear"
						:options="years"
						:reduce="year => year"
						:input-label="t('employees', 'Year')" />
				</div>
				<NcButton type="primary" :disabled="loading" @click="applyPeriod">
					{{ t('employees', 'Apply changes') }}
				</NcButton>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import CalendarRange from 'vue-material-design-icons/CalendarRange.vue'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import {
	NcAppContent,
	NcButton,
	NcModal,
	NcSelect,
} from '@nextcloud/vue'

import List from '../Helpers/Lists/List.vue'
import CostQuote from './CostQuote.vue'
import CostDetails from './CostDetails.vue'
import CostPlanning from './CostPlanning.vue'
import CostSummary from './CostSummary.vue'

const START_KEY = 'nextcloud_empleados_costos_mes_inicio'
const END_KEY = 'nextcloud_empleados_costos_mes_fin'
const YEAR_KEY = 'nextcloud_empleados_costos_anio'
const MAX_SCENARIOS = 3

export default {
	name: 'Costs',
	components: {
		CalendarRange,
		CostQuote,
		CostDetails,
		CostPlanning,
		CostSummary,
		List,
		NcAppContent,
		NcButton,
		NcModal,
		NcSelect,
	},
	data() {
		const now = new Date()
		const currentMonth = now.getMonth() + 1
		const currentYear = now.getFullYear()
		const initialScenarioId = 'cost-scenario-1'

		return {
			activeTab: 'resumen',
			loading: true,
			leaders: [],
			participants: [],
			companiesSummary: [],
			employeesAvailability: [],
			activities: [],
			kpis: {},
			selection: [],
			selectedLeaderId: null,
			periodModal: false,
			periodStart: this.storedNumber(START_KEY, currentMonth),
			periodEnd: this.storedNumber(END_KEY, currentMonth),
			periodYear: this.storedNumber(YEAR_KEY, currentYear),
			draftStart: null,
			draftEnd: null,
			draftYear: null,
			scenarioSequence: 2,
			activeScenarioId: initialScenarioId,
			scenarios: [{
				id: initialScenarioId,
				name: t('employees', 'Scenario {letter}', { letter: 'A' }),
				companyId: null,
				company: null,
				leaderId: null,
				leader: null,
				startDate: '',
				endDate: '',
				activities: [],
				price: 0,
				contingency: 0,
				team: [],
				analysis: null,
			}],
			months: [
				{ label: t('employees', 'January'), value: 1 },
				{ label: t('employees', 'February'), value: 2 },
				{ label: t('employees', 'March'), value: 3 },
				{ label: t('employees', 'April'), value: 4 },
				{ label: t('employees', 'May'), value: 5 },
				{ label: t('employees', 'June'), value: 6 },
				{ label: t('employees', 'July'), value: 7 },
				{ label: t('employees', 'August'), value: 8 },
				{ label: t('employees', 'September'), value: 9 },
				{ label: t('employees', 'October'), value: 10 },
				{ label: t('employees', 'November'), value: 11 },
				{ label: t('employees', 'December'), value: 12 },
			],
			years: Array.from({ length: 11 }, (_, index) => currentYear - 5 + index),
		}
	},
	computed: {
		tabs() {
			return [
				{ id: 'resumen', label: t('employees', 'Summary') },
				{ id: 'planificacion', label: t('employees', 'Planning') },
				{ id: 'cotizacion', label: t('employees', 'Quotation') },
			]
		},
		normalizedPeriod() {
			let start = Math.max(1, Math.min(12, Number(this.periodStart)))
			let end = Math.max(1, Math.min(12, Number(this.periodEnd)))

			if (start > end) {
				[start, end] = [end, start]
			}

			return {
				period_start: start,
				period_end: end,
				anio: Number(this.periodYear),
			}
		},
		leaderList() {
			return this.participants.map(leader => ({
				id: leader.id_employee,
				name: leader.displayname || leader.uid,
				image: leader.uid,
				subname: [
					leader.uid,
					t('employees', '{count} companies', { count: leader.empresas_count }),
				].filter(Boolean).join(' · '),
				count: Number(Number(leader.horas_cargables || 0).toFixed(2)),
			}))
		},
		selectedLeader() {
			return this.participants.find(
				participant => Number(participant.id_employee) === Number(this.selectedLeaderId),
			) || null
		},
		periodLabel() {
			const period = this.normalizedPeriod
			const start = this.months.find(month => month.value === period.period_start)?.label || '-'
			const end = this.months.find(month => month.value === period.period_end)?.label || '-'

			return `${start} - ${end} (${period.anio})`
		},
		companies() {
			const companies = new Map()

			this.companiesSummary.forEach(company => {
				const id = Number(company.id_client ?? company.id ?? 0)
				const active = company.status === true || Number(company.status) === 1

				if (!id || !active) {
					return
				}

				const name = company.client_name || company.name || ''
				companies.set(id, {
					...company,
					id,
					id_client: id,
					name,
					client_name: name,
				})
			})

			this.participants.forEach(participant => {
				const participantCompanies = Array.isArray(participant.empresas)
					? participant.empresas
					: []

				participantCompanies.forEach(company => {
					const id = Number(company.id_client ?? company.id ?? 0)
					const active = company.status === true || Number(company.status) === 1

					if (!id || !active || companies.has(id)) {
						return
					}

					const name = company.client_name || company.name || ''
					companies.set(id, {
						...company,
						id,
						id_client: id,
						name,
						client_name: name,
					})
				})
			})

			return Array.from(companies.values())
				.sort((left, right) => left.name.localeCompare(right.name, 'es'))
		},
		activeScenario() {
			return this.scenarios.find(scenario => this.sameId(scenario.id, this.activeScenarioId))
				|| this.scenarios[0]
				|| null
		},
	},
	watch: {
		periodModal(open) {
			if (open) {
				this.openPeriodDraft()
			}
		},
	},
	mounted() {
		this._onDetails = id => {
			if (this.activeTab === 'resumen') {
				this.selectLeader(id)
			}
		}
		this.$root.$on('details', this._onDetails)
		this.loadCosts()
		this.loadActivities()
	},
	beforeDestroy() {
		this.$root.$off('details', this._onDetails)
	},
	methods: {
		t,
		storedNumber(key, fallback) {
			const value = Number(localStorage.getItem(key))
			return Number.isFinite(value) && value > 0 ? value : fallback
		},
		sameId(first, second) {
			return String(first) === String(second)
		},
		setActiveTab(tabId) {
			if (!this.tabs.some(tab => tab.id === tabId)) {
				return
			}

			this.activeTab = tabId

			if (tabId !== 'resumen') {
				this.periodModal = false
			}
		},
		onTabKeydown(event, currentIndex) {
			const lastIndex = this.tabs.length - 1
			let nextIndex = currentIndex

			switch (event.key) {
			case 'ArrowRight':
				nextIndex = currentIndex === lastIndex ? 0 : currentIndex + 1
				break
			case 'ArrowLeft':
				nextIndex = currentIndex === 0 ? lastIndex : currentIndex - 1
				break
			case 'Home':
				nextIndex = 0
				break
			case 'End':
				nextIndex = lastIndex
				break
			default:
				return
			}

			event.preventDefault()
			this.setActiveTab(this.tabs[nextIndex].id)
			this.$nextTick(() => {
				this.$refs.tabButtons?.[nextIndex]?.focus()
			})
		},
		selectLeader(id) {
			if (this.activeTab !== 'resumen') {
				return
			}

			this.selectedLeaderId = id
			this.selection = [id]
		},
		openPeriodDraft() {
			this.draftStart = this.periodStart
			this.draftEnd = this.periodEnd
			this.draftYear = this.periodYear
		},
		async applyPeriod() {
			let start = Number(this.draftStart)
			let end = Number(this.draftEnd)

			if (start > end) {
				[start, end] = [end, start]
			}

			this.periodStart = start
			this.periodEnd = end
			this.periodYear = Number(this.draftYear)
			localStorage.setItem(START_KEY, String(start))
			localStorage.setItem(END_KEY, String(end))
			localStorage.setItem(YEAR_KEY, String(this.periodYear))
			this.periodModal = false
			this.selection = []
			this.selectedLeaderId = null
			await this.loadCosts()
		},
		async loadCosts() {
			if (this.loading && this.participants.length) {
				return
			}

			this.loading = true

			try {
				const response = await axios.post(
					generateUrl('/apps/employees/GetCostosLideres'),
					this.normalizedPeriod,
				)

				if (response?.data?.ocs?.meta?.status !== 'ok') {
					throw new Error(response?.data?.ocs?.meta?.message || t('employees', 'Could not load costs'))
				}

				const data = response?.data?.ocs?.data || {}
				this.participants = Array.isArray(data.Employee)
					? data.Employee
					: (Array.isArray(data.lideres) ? data.lideres : [])
				this.leaders = Array.isArray(data.lideres) ? data.lideres : []
				this.companiesSummary = Array.isArray(data.empresas) ? data.empresas : []
				this.employeesAvailability = Array.isArray(data.empleados_disponibilidad)
					? data.empleados_disponibilidad
					: []
				this.kpis = data.kpis || {}
				this.ensureSelectedCompanyIsVisible()
			} catch (error) {
				this.leaders = []
				this.participants = []
				this.companiesSummary = []
				this.employeesAvailability = []
				this.kpis = {}
				this.selection = []
				this.selectedLeaderId = null
				this.ensureSelectedCompanyIsVisible()
				showError(t('employees', 'Could not load costs: {error}', { error: String(error) }))
			} finally {
				this.loading = false
			}
		},
		async loadActivities() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetCostosActivities'))

				if (response?.data?.ocs?.meta?.status !== 'ok') {
					throw new Error(response?.data?.ocs?.meta?.message || t('employees', 'Could not load activities'))
				}

				const activities = response?.data?.ocs?.data
				this.activities = (Array.isArray(activities) ? activities : [])
					.filter(activity => Number(activity.id_activity ?? activity.id ?? 0) !== 99999)
			} catch (error) {
				this.activities = []
				showError(t('employees', 'Could not load activities: {error}', { error: String(error) }))
			}
		},
		ensureSelectedCompanyIsVisible() {
			const visibleIds = new Set(this.companies.map(company => String(company.id)))
			const visibleEmployeeIds = new Set(
				this.employeesAvailability.map(employee => String(employee.id_employee)),
			)
			const visibleLeaderIds = new Set(
				this.leaders.map(leader => String(leader.id_employee)),
			)

			this.scenarios = this.scenarios.map(scenario => {
				const companyVisible = scenario.companyId === null
					|| visibleIds.has(String(scenario.companyId))
				const leaderVisible = scenario.leaderId === null
					|| scenario.leaderId === undefined
					|| visibleLeaderIds.has(String(scenario.leaderId))
				const team = (Array.isArray(scenario.team) ? scenario.team : [])
					.filter(member => visibleEmployeeIds.has(String(member.id_employee)))

				return {
					...scenario,
					companyId: companyVisible ? scenario.companyId : null,
					company: companyVisible ? scenario.company : null,
					leaderId: leaderVisible ? scenario.leaderId : null,
					leader: leaderVisible ? scenario.leader : null,
					team,
					analysis: null,
				}
			})
		},
		updateScenario(payload) {
			const id = payload?.id
			const changes = payload?.changes

			if (!id || !changes || typeof changes !== 'object' || Array.isArray(changes)) {
				return
			}

			const index = this.scenarios.findIndex(scenario => this.sameId(scenario.id, id))

			if (index < 0) {
				return
			}

			const normalizedChanges = this.normalizedScenarioChanges(changes)
			this.$set(this.scenarios, index, {
				...this.scenarios[index],
				...normalizedChanges,
				id: this.scenarios[index].id,
			})
		},
		normalizedScenarioChanges(changes) {
			const normalized = { ...changes }
			const aliases = [
				['precio_propuesto', 'price'],
				['precio', 'price'],
				['contingencia_porcentaje', 'contingency'],
				['contingencia', 'contingency'],
				['equipo', 'team'],
				['Activity', 'activities'],
				['date_start', 'startDate'],
				['date_end', 'endDate'],
				['id_client', 'companyId'],
			]

			aliases.forEach(([alias, canonical]) => {
				if (Object.prototype.hasOwnProperty.call(normalized, alias)) {
					normalized[canonical] = normalized[alias]
					delete normalized[alias]
				}
			})

			return normalized
		},
		renameScenario(payload) {
			const id = payload?.id
			const name = String(payload?.name || '').trim()
			const index = this.scenarios.findIndex(scenario => this.sameId(scenario.id, id))

			if (!name || index < 0) {
				return
			}

			this.$set(this.scenarios, index, {
				...this.scenarios[index],
				name,
			})
		},
		selectScenario(id) {
			if (this.scenarios.some(scenario => this.sameId(scenario.id, id))) {
				this.activeScenarioId = id
			}
		},
		duplicateScenario(id) {
			if (this.scenarios.length >= MAX_SCENARIOS) {
				return
			}

			const source = this.scenarios.find(scenario => this.sameId(scenario.id, id))

			if (!source) {
				return
			}

			const duplicate = JSON.parse(JSON.stringify(source))
			duplicate.id = `cost-scenario-${this.scenarioSequence}`
			duplicate.name = this.nextScenarioName()
			this.scenarioSequence += 1
			this.scenarios.push(duplicate)
			this.activeScenarioId = duplicate.id
		},
		deleteScenario(id) {
			if (this.scenarios.length <= 1) {
				return
			}

			const index = this.scenarios.findIndex(scenario => this.sameId(scenario.id, id))

			if (index < 0) {
				return
			}

			const wasActive = this.sameId(this.activeScenarioId, id)
			this.scenarios.splice(index, 1)

			if (wasActive) {
				const fallbackIndex = Math.min(index, this.scenarios.length - 1)
				this.activeScenarioId = this.scenarios[fallbackIndex].id
			}
		},
		nextScenarioName() {
			const names = new Set(this.scenarios.map(scenario => scenario.name))

			for (let index = 0; index < MAX_SCENARIOS; index += 1) {
				const name = t('employees', 'Scenario {letter}', {
					letter: String.fromCharCode(65 + index),
				})

				if (!names.has(name)) {
					return name
				}
			}

			return t('employees', 'Scenario copy')
		},
		goToQuote() {
			this.setActiveTab('cotizacion')
		},
	},
}
</script>

<style scoped lang="scss">
.costs-tabs {
	display: flex;
	gap: 4px;
	overflow-x: auto;
	padding: 0 24px;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-main-background);
}

.costs-tabs button {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	min-height: 44px;
	margin: 0;
	padding: 0 14px;
	border: 0;
	border-bottom: 2px solid transparent;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	font-weight: 600;
}

.costs-tabs button:hover,
.costs-tabs button:focus-visible {
	background: var(--color-background-hover);
}

.costs-tabs button:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.costs-tabs button.active {
	border-bottom-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.costs-panel {
	padding: 24px;
}

.period-action {
	margin-right: 8px;
}

.period-modal {
	display: flex;
	min-width: min(560px, calc(100vw - 48px));
	flex-direction: column;
	gap: 20px;
	padding: 28px;
}

.period-fields {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
}

@media (max-width: 700px) {
	.costs-tabs,
	.costs-panel {
		padding-right: 16px;
		padding-left: 16px;
	}

	.period-fields {
		grid-template-columns: 1fr;
	}
}
</style>
