<template>
	<div class="well">
		<div class="empleado-view-switch-wrapper">
			<div class="empleado-view-switch">
				<button
					type="button"
					class="empleado-switch-btn"
					:class="{ active: viewMode === 'information' }"
					:aria-pressed="viewMode === 'information' ? 'true' : 'false'"
					@click="setViewMode('information')">
					{{ t('employees', 'Information') }}
				</button>
				<button
					type="button"
					class="empleado-switch-btn"
					:class="{ active: viewMode === 'onboarding' }"
					:aria-pressed="viewMode === 'onboarding' ? 'true' : 'false'"
					@click="setViewMode('onboarding')">
					{{ t('employees', 'Boarding') }}
				</button>
			</div>
		</div>

		<div class="empleado-content">
			<div v-if="viewMode === 'information'" class="top">
				<div class="main">
					<div class="box1">
						<div>
							<div class="divider">
								<span>{{ t('employees', 'Work information') }}</span>
							</div>
							<div class="flexible">
								<!-- Employee number -->
								<div class="box1Inside">
									<label for="number_employee" class="labeltype">
										<Badgeaccountoutline :size="20" />
										{{ t('employees', 'Employee No.') }}
									</label>
									<input id="number_employee"
										v-model="number_employee"
										type="text"
										:disabled="!show"
										class="inputtype">
								</div>

								<!-- Salary -->
								<div class="box1Inside">
									<label for="salary" class="labeltype">
										<Cash :size="20" />
										{{ t('employees', 'Salary') }}
									</label>
									<input id="salary"
										v-model="salary"
										type="text"
										:disabled="!show"
										class="inputtype">
								</div>

								<!-- Bank account -->
								<div class="box1Inside">
									<label for="number_account" class="labeltype">
										<Bank :size="20" />
										{{ t('employees', 'Bank account') }}
									</label>
									<input id="number_account"
										v-model="number_account"
										type="text"
										:disabled="!show"
										class="inputtype">
								</div>
							</div>
							<div class="flexible top">
								<!-- Start date -->
								<div class="box1Inside">
									<label for="hire_date" class="labeltype">
										<Calendarrange :size="20" />
										{{ t('employees', 'Start date') }}
									</label>
									<input id="hire_date"
										v-model="hire_date"
										type="date"
										:disabled="!show"
										class="inputtype">
								</div>

								<!-- Anniversary -->
								<div class="box1Inside">
									<label for="Aniversario" class="labeltype">
										<PartyPopper :size="20" />
										{{ t('employees', 'Anniversary') }}
									</label>
									<input
										id="Aniversario"
										:value="cargandoPeriodo ? '…' : Aniversario"
										type="text"
										disabled
										class="inputtype">
								</div>

								<!-- Vacation -->
								<div class="box1Inside">
									<label for="Vacaciones" class="labeltype">
										<BagSuitcase :size="20" />
										{{ t('employees', 'Vacation') }}
									</label>
									<div class="stepper-wrapper">
										<div v-if="show" class="stepper-arrows">
											<button type="button"
												class="stepper-btn"
												:disabled="cargandoPeriodo"
												@click="incrementarVacaciones(1)">
												<ChevronUp :size="11" fill-color="currentColor" />
											</button>
											<button type="button"
												class="stepper-btn"
												:disabled="cargandoPeriodo"
												@click="incrementarVacaciones(-1)">
												<ChevronDown :size="11" fill-color="currentColor" />
											</button>
										</div>
										<input id="Vacaciones"
											v-model.number="Vacaciones"
											type="number"
											step="1"
											min="0"
											:disabled="!show || cargandoPeriodo"
											:placeholder="cargandoPeriodo ? '…' : ''"
											class="inputtype stepper-input">
									</div>
								</div>

								<!-- Save vacation days -->
								<div
									v-if="show"
									class="topRefresh MarginRight">
									<NcButton
										type="primary"
										:disabled="guardandoDias || cargandoPeriodo || String(Vacaciones) === String(diasDerechoOriginal)"
										@click="GuardarDiasDerecho()">
										<template #icon>
											<NcLoadingIcon v-if="guardandoDias" :size="20" />
											<ContentSaveOutline v-else :size="20" />
										</template>
										{{ t('employees', 'Save') }}
									</NcButton>
								</div>
							</div>
						</div>

						<div>
							<div class="divider">
								<span>{{ t('employees', 'Savings fund') }}</span>
							</div>
							<div class="flexible">
								<div class="box1Inside">
									<label for="fund_code" class="labeltype">
										<Piggybankoutline :size="20" />
										{{ t('employees', 'Fund key') }}
									</label>
									<input id="fund_code"
										v-model="fund_code"
										type="text"
										:disabled="!show"
										class="inputtype">
								</div>

								<div class="box1Inside">
									<label for="savings_fund" class="labeltype">
										<Piggybankoutline :size="20" />
										{{ t('employees', 'Savings fund') }}
									</label>
									<input id="savings_fund"
										v-model="savings_fund"
										type="text"
										:disabled="!show"
										class="inputtype">
								</div>

								<div class="topRefresh MarginRight">
									<NcCheckboxRadioSwitch
										v-model="state"
										:disabled="!show"
										type="switch">
										{{ state ? t('employees', 'Can request') : t('employees', 'Read-only mode') }}
									</NcCheckboxRadioSwitch>
								</div>
							</div>
						</div>

						<div v-if="inventoryEnabled">
							<div class="divider">
								<span>{{ t('employees', 'Systems') }}</span>
							</div>

							<div class="equipo-asignado-field">
								<label for="Teams_asignados" class="labeltype">
									<Laptopaccount :size="20" />
									{{ t('employees', 'Assigned equipment') }}
								</label>

								<NcLoadingIcon v-if="cargandoTeams" :size="24" />

								<NcSelect v-else-if="show && canModifyInventory"
									id="Teams_asignados"
									v-model="Teams_asignados"
									class="equipo-computo-select"
									:options="inventarioTeams"
									:multiple="true"
									:close-on-select="false"
									:clearable="true"
									:input-label="t('employees', 'Assigned equipment')"
									:label-outside="true"
									:placeholder="t('employees', 'Select assigned equipment')">
									<template #selected-option="option">
										<div class="equipo-selected-option">
											<strong>{{ equipoOptionTitle(option) }}</strong>
										</div>
									</template>

									<template #option="option">
										<div class="equipo-dropdown-option">
											<div class="equipo-dropdown-main">
												<strong>{{ equipoOptionTitle(option) }}</strong>

												<span v-if="option.status"
													class="equipo-status"
													:class="`equipo-status--${String(option.status).toLowerCase()}`">
													{{ inventoryStatusLabel(option.status) }}
												</span>
											</div>

											<div class="equipo-dropdown-subtitle">
												{{ equipoOptionSubtitle(option) }}
											</div>
										</div>
									</template>
								</NcSelect>

								<p v-if="errorTeams" class="assigned-equipment-error">
									{{ errorTeams }}
								</p>

								<div v-if="Teams_asignados.length > 0" class="assigned-equipment-list">
									<component :is="canNavigateAssignedEquipment ? 'button' : 'div'"
										v-for="equipo in Teams_asignados"
										:key="equipo.id_team || equipo.value"
										class="assigned-equipment-card"
										:class="{
											'assigned-equipment-card--interactive': canNavigateAssignedEquipment,
										}"
										:type="canNavigateAssignedEquipment ? 'button' : null"
										:title="canNavigateAssignedEquipment
											? t('employees', 'Open this device in IT Inventory')
											: null"
										:aria-label="canNavigateAssignedEquipment
											? t('employees', 'Open {device} in IT Inventory', {
												device: equipoOptionTitle(equipo),
											})
											: null"
										@click="openAssignedEquipment(equipo)">
										<Laptopaccount :size="32" aria-hidden="true" />

										<div class="assigned-equipment-content">
											<div class="assigned-equipment-heading">
												<strong>{{ equipoOptionTitle(equipo) }}</strong>

												<span v-if="equipo.status"
													class="equipo-status"
													:class="`equipo-status--${String(equipo.status).toLowerCase()}`">
													{{ inventoryStatusLabel(equipo.status) }}
												</span>
											</div>

											<span v-if="equipo.system_name">
												{{ t('employees', 'System name') }}:
												{{ equipo.system_name }}
											</span>

											<span v-if="equipo.serial_number">
												{{ t('employees', 'Serial number') }}:
												{{ equipo.serial_number }}
											</span>

											<span v-if="equipoModel(equipo)">
												{{ t('employees', 'Model') }}:
												{{ equipoModel(equipo) }}
											</span>

											<span v-if="canNavigateAssignedEquipment"
												class="assigned-equipment-link-hint">
												{{ t('employees', 'Open in IT Inventory') }}
											</span>
										</div>
									</component>
								</div>

								<p v-else-if="!cargandoTeams" class="assigned-equipment-empty">
									{{ t('employees', 'No equipment assigned.') }}
								</p>
							</div>
						</div>
					</div>

					<div class="box2">
						<div class="divider">
							<span>{{ t('employees', 'Employment structure') }}</span>
						</div>

						<div>
							<!-- Organization Chart -->
							<div class="box2" :style="show ? { display: 'none' } : {}">
								<div class="box-chart">
									<OrganizationChart :datasource="generateChar(data.uid, gerente, socio)">
										<template slot-scope="{ nodeData }">
											<div class="title">
												{{ nodeData.title }}
											</div>
											<div class="content">
												<div class="center">
													<div class="avatar-chart mini-top">
														<NcAvatar v-if="nodeData.name == '?'"
															display-name="?"
															:size="40" />
														<NcAvatar v-else
															:user="nodeData.name"
															:display-name="nodeData.name"
															:size="40" />
													</div>
													<div class="name-chart">
														{{ nodeData.name }}
													</div>
												</div>
											</div>
										</template>
									</OrganizationChart>
								</div>
							</div>

							<!-- Department and Position -->
							<div class="main">
								<div class="label-input-trabajo">
									<NcSelect id="id_department"
										v-model="area"
										class="container__select"
										:disabled="!show"
										:options="optionsarea"
										:input-label="t('employees','Department')" />
								</div>

								<div class="label-input-trabajo">
									<NcSelect id="id_position"
										v-model="puesto"
										class="container__select_puesto"
										:disabled="!show"
										:options="optionspuesto"
										:input-label="t('employees','Position')" />
								</div>
							</div>

							<!-- Partner and Manager -->
							<div v-if="show" class="main">
								<div class="label-input-trabajo">
									<NcSelect v-model="socio"
										class="select"
										:disabled="!show"
										:options="EmpleadosList"
										:user-select="true"
										:input-label="t('employees','Partner')" />
								</div>

								<div class="label-input-trabajo">
									<NcSelect v-model="gerente"
										class="select"
										:disabled="!show"
										:options="EmpleadosList"
										:user-select="true"
										:input-label="t('employees','Manager')" />
								</div>
							</div>

							<!-- Team -->
							<div v-if="show" class="main">
								<div class="label-input-puesto">
									<NcSelect v-model="Equipo"
										class="select"
										:disabled="!show"
										:options="optionsteams"
										:input-label="t('employees','Team')" />
								</div>
							</div>
							<div v-else class="">
								<div v-if="!Equipo == '' || !Equipo == null">
									<div class="rst-title">
										<div class="title_flex">
											<div class="subtitle_flex">
												<NcAvatar v-if="teamLeaderUid(Equipo)"
													:user="teamLeaderUid(Equipo)"
													:display-name="teamLeaderName(Equipo)"
													:size="20" />
											</div>
											<div>
												<h1> {{ Equipo.label }} </h1>
											</div>
										</div>
									</div>
									<div class="rst">
										<ul class="team-list">
											<NcListItem
												v-for="(item) in peopleEquipo.equipo"
												:key="item.id_employees"
												:name="item.displayname ? item.displayname : item.id_user"
												@click.prevent="showDetails(item)">
												<template #icon>
													<NcAvatar disable-menu
														:size="44"
														:user="item.id_user"
														:display-name="item.id_user" />
												</template>
											</NcListItem>
										</ul>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

			</div>

			<div v-else class="top">
				<div class="OnboardingItem-header">
					<div class="divider OnboardingItem-divider">
						<span>{{ boardingOn === 1 ? t('employees', 'OnBoarding') : t('employees', 'OffBoarding') }}</span>
					</div>

					<div class="OnboardingItem-toggle-wrapper">
						<div class="OnboardingItem-toggle" :class="{ 'OnboardingItem-toggle--off': boardingOn === 0 }">
							<span class="OnboardingItem-toggle-thumb" aria-hidden="true" />
							<button
								type="button"
								class="OnboardingItem-toggle-btn"
								:class="{ active: boardingOn === 1 }"
								:aria-pressed="boardingOn === 1 ? 'true' : 'false'"
								:disabled="!show || boardingLoading"
								@click="setBoardingOn(1)">
								{{ t('employees', 'On') }}
							</button>
							<button
								type="button"
								class="OnboardingItem-toggle-btn"
								:class="{ active: boardingOn === 0 }"
								:aria-pressed="boardingOn === 0 ? 'true' : 'false'"
								:disabled="!show || boardingLoading"
								@click="setBoardingOn(0)">
								{{ t('employees', 'Off') }}
							</button>
						</div>
					</div>
				</div>

				<NcEmptyContent v-if="boardingLoading" :name="t('employees', 'Loading')">
					<template #icon>
						<NcLoadingIcon :size="20" />
					</template>
				</NcEmptyContent>

				<template v-else>
					<ul v-if="boardingItemsFiltered.length" class="onboarding-checklist">
						<li
							v-for="item in boardingItemsFiltered"
							:key="item.id_employee_boarding"
							class="onboarding-item"
							:class="{ 'onboarding-item--done': isChecked(item) }">
							<NcCheckboxRadioSwitch
								:checked="isChecked(item)"
								:disabled="!show || boardingSavingId === item.id_employee_boarding"
								@update:checked="value => toggleItemStatus(item, value)">
								{{ item.name }}
							</NcCheckboxRadioSwitch>
							<NcLoadingIcon v-if="boardingSavingId === item.id_employee_boarding" :size="16" />
						</li>
					</ul>
					<p v-else class="OnboardingItem-empty">
						{{ t('employees', 'No items in this checklist yet.') }}
					</p>
				</template>
			</div>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import OrganizationChart from 'vue-organization-chart'
import { generateUrl } from '@nextcloud/router'
import 'vue-nav-tabs/themes/vue-tabs.css'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import permissionsMixin from '../../../../mixins/permissions.js'
import { inventoryStatusLabel } from '../../../../utils/inventoryStatusLabel.js'

// ICONOS
import Badgeaccountoutline from 'vue-material-design-icons/BadgeAccountOutline.vue'
import Piggybankoutline from 'vue-material-design-icons/PiggyBankOutline.vue'
import Calendarrange from 'vue-material-design-icons/CalendarRange.vue'
import Laptopaccount from 'vue-material-design-icons/LaptopAccount.vue'
import BagSuitcase from 'vue-material-design-icons/BagSuitcase.vue'
import PartyPopper from 'vue-material-design-icons/PartyPopper.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import Cash from 'vue-material-design-icons/Cash.vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import AccountArrowRightOutline from 'vue-material-design-icons/AccountArrowRightOutline.vue'
import AccountArrowLeftOutline from 'vue-material-design-icons/AccountArrowLeftOutline.vue'

import {
	NcAvatar,
	NcButton,
	NcSelect,
	NcListItem,
	NcCheckboxRadioSwitch,
	NcLoadingIcon,
	NcEmptyContent,
} from '@nextcloud/vue'

export default {
	name: 'EmployeeTab',

	components: {
		NcAvatar,
		Badgeaccountoutline,
		Calendarrange,
		Bank,
		PartyPopper,
		BagSuitcase,
		Piggybankoutline,
		Laptopaccount,
		Cash,
		ContentSaveOutline,
		ChevronUp,
		ChevronDown,
		AccountArrowRightOutline,
		AccountArrowLeftOutline,
		NcLoadingIcon,
		NcEmptyContent,
		OrganizationChart,
		NcButton,
		NcSelect,
		NcListItem,
		NcCheckboxRadioSwitch,
	},

	mixins: [permissionsMixin],

	inject: {
		Settings: {
			default: () => ({}),
		},
	},

	props: {
		data: { type: Object, required: true },
		show: { type: Boolean, required: true },
		Employee: { type: Array, required: true },
		automaticsave: { type: String, required: true },
	},

	data() {
		return {
			viewMode: 'information',
			area: '',
			puesto: '',
			gerente: null,
			socio: null,
			optionsarea: [],
			optionspuesto: [],
			optionsteams: [],
			number_employee: '',
			hire_date: '',
			fund_code: '',
			savings_fund: '',
			number_account: '',
			team_assigned: null,
			salary: '',
			Equipo: '',
			areaSend: '',
			puestoSend: '',
			EquipoSend: '',
			peopleEquipo: {},
			EmpleadosList: [],
			Aniversario: '',
			Vacaciones: '',
			state: false,
			inventarioTeams: [],
			cargandoTeams: false,
			errorTeams: '',
			diasDerechoOriginal: '',
			guardandoDias: false,
			cargandoPeriodo: false,
			// Checklist de OnBoarding / OffBoarding
			boardingOn: 1, // 1 = OnBoarding, 0 = OffBoarding
			boardingItems: [],
			boardingLoading: false,
			boardingInitialized: false,
			boardingSavingId: null,
			Teams_asignados: [],
		}
	},

	computed: {
		inventoryEnabled() {
			return this.isTruthy(this.Settings?.modulo_inventario)
		},
		canAccessInventory() {
			return this.canSee('inventario')
		},
		canModifyInventory() {
			return this.canSee('inventario.admin') || this.isAdminUser()
		},
		canNavigateAssignedEquipment() {
			return this.inventoryEnabled && this.canAccessInventory
		},
		boardingItemsFiltered() {
			return this.boardingItems.filter(item => Number(item.on) === this.boardingOn)
		},
	},

	watch: {
		// FIX: la firma correcta es (newVal, oldVal)
		async data(news) {
			if (news) {
				this.setAttr(
					news.number_employee,
					news.hire_date,
					news.id_department,
					news.id_position,
					news.id_manager,
					news.id_partner,
					news.fund_code,
					news.savings_fund,
					news.number_account,
					news.id_team,
					news.salary,
					news.days_available,
					news.id_anniversary,
					news.state)

				await this.cargarPeriodoActual(news.id_employees)
				if (this.inventoryEnabled && this.canAccessInventory) {
					await this.getInventoryTeams(news.id_employees)
				}

				// New empleado: el checklist anterior ya no aplica
				this.boardingItems = []
				this.boardingInitialized = false
				if (this.viewMode === 'onboarding') {
					this.boardingInitialized = true
					await this.cargarBoardingChecklist()
				}
			}
		},

		hire_date: {
			handler(nuevaFecha) {
				if (!nuevaFecha || !this.show) return

				const años = this.calcularAniversarioDesdeFecha(nuevaFecha)
				if (años !== null) {
					this.Aniversario = años
				}
			},
		},
	},

	async mounted() {
		const employees = Array.isArray(this.Employee) ? this.Employee : []
		this.EmpleadosList = employees.map(Employee => ({
			id: Employee.id_user,
			displayName: Employee.displayname ? Employee.displayname : Employee.id_user,
			isNoUser: false,
			icon: '',
			user: Employee.id_user,
		}))

		this.setAttr(
			this.data.number_employee,
			this.data.hire_date,
			this.data.id_department,
			this.data.id_position,
			this.data.id_manager,
			this.data.id_partner,
			this.data.fund_code,
			this.data.savings_fund,
			this.data.number_account,
			this.data.id_team,
			this.data.salary,
			this.data.days_available,
			this.data.id_anniversary,
			this.data.state)

		await this.cargarPeriodoActual(this.data.id_employees)
		if (this.inventoryEnabled && this.canAccessInventory) {
			await this.getInventoryTeams(this.data.id_employees)
		}
	},

	methods: {
		t,
		inventoryStatusLabel,

		setViewMode(viewMode) {
			this.viewMode = viewMode
			if (viewMode === 'onboarding' && !this.boardingInitialized) {
				this.boardingInitialized = true
				this.cargarBoardingChecklist()
			}
		},

		setBoardingOn(on) {
			this.boardingOn = on
		},

		isChecked(item) {
			return Number(item.status) === 1
		},

		async cargarBoardingChecklist() {
			const idEmployee = this.data?.id_employees
			if (!idEmployee) return

			this.boardingLoading = true
			try {
				// Asegura que existan los registros del checklist a partir del catálogo (alta y baja)
				await Promise.all([
					axios.post(generateUrl('/apps/employees/generarChecklistEmpleado'), { id_employee: idEmployee, on: 1 }),
					axios.post(generateUrl('/apps/employees/generarChecklistEmpleado'), { id_employee: idEmployee, on: 0 }),
				])

				const response = await axios.post(generateUrl('/apps/employees/getChecklistEmpleado'), {
					id_employee: idEmployee,
				})
				const data = response?.data?.ocs?.data
				this.boardingItems = Array.isArray(data) ? data : []
			} catch (err) {
				showError(t('employees', 'No se pudo cargar el checklist [{error}]', { error: String(err), close: true }))
			} finally {
				this.boardingLoading = false
			}
		},

		async toggleItemStatus(item, checked) {
			const nuevoStatus = checked ? 1 : 0
			this.boardingSavingId = item.id_employee_boarding
			try {
				await axios.post(generateUrl('/apps/employees/marcarStatusBoarding'), {
					id_employee_boarding: item.id_employee_boarding,
					status: nuevoStatus,
				})
				item.status = nuevoStatus
			} catch (err) {
				showError(t('employees', 'No se pudo actualizar el ítem [{error}]', { error: String(err), close: true }))
			} finally {
				this.boardingSavingId = null
			}
		},

		openAssignedEquipment(equipo) {
			if (!this.canNavigateAssignedEquipment) return
			this.$router.push({
				name: 'Inventory',
				query: { deviceId: String(equipo.id_team) },
			})
		},

		setAttr(NumeroEmpleado, hireDate, Area, Puesto, Gerente, Socio, FondoClave, FondoAhorro, NumeroCuenta, Equipo, salary, Vacaciones, Aniversario, state) {
			this.number_employee = this.checknull(NumeroEmpleado)
			this.hire_date = this.checknull(hireDate)
			this.area = Area
			this.puesto = Puesto
			this.gerente = this.checknull(Gerente)
			this.socio = this.checknull(Socio)
			this.fund_code = this.checknull(FondoClave)
			this.savings_fund = this.checknull(FondoAhorro)
			this.number_account = this.checknull(NumeroCuenta)
			this.Equipo = this.checknull(Equipo)
			this.salary = this.checknull(salary)
			this.Vacaciones = this.checknull(Vacaciones)
			this.Aniversario = this.checknull(Aniversario)

			// `1` means the employee may submit savings requests.
			this.state = Number(state) === 1

			this.getAreas(this.area)
			this.getPositions(this.puesto)
			this.getTeams(this.Equipo)
		},

		async getAreas(Area) {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetAreasFix'))
				this.optionsarea = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []
				if (Area !== '' && Area !== null && Area !== undefined) {
					this.area = this.optionsarea.find(area => String(area.value) === String(Area)) || null
				} else {
					this.area = null
				}
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepción [01] [{error}]', { error: String(err), close: true }))
			}
		},

		async getPositions(Puesto) {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetPositionsFix'))
				this.optionspuesto = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []
				if (Puesto !== '' && Puesto !== null && Puesto !== undefined) {
					this.puesto = this.optionspuesto.find(position => String(position.value) === String(Puesto)) || null
				} else {
					this.puesto = null
				}
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepción [01] [{error}]', { error: String(err), close: true }))
			}
		},

		async getTeams(Equipo) {
			this.loading = false
			this.GetAllEquipo(Equipo)
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetTeamsList'))
				const payload = response?.data?.ocs?.data ?? response?.data ?? []
				const data = Array.isArray(payload) ? payload : []
				this.optionsteams = data.map(equipo => ({
					value: equipo.id_team,
					label: equipo.name,
					team_leader_id: equipo.team_leader_id,
					leader_uid: this.employeeUidById(equipo.team_leader_id)
						|| this.teamLeaderUidFromValue(equipo.team_leader_id),
					leader_name: this.teamLeaderNameFromValue(equipo.team_leader_id),
				}))
				if (Equipo !== '' && Equipo !== null && Equipo !== undefined) {
					this.Equipo = this.optionsteams.find(role => String(role.value) === String(Equipo)) || null
				} else {
					this.Equipo = null
				}
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepción [01] [{error}]', { error: String(err), close: true }))
			}
		},

		employeeById(employeeId) {
			const employees = Array.isArray(this.Employee) ? this.Employee : []
			return employees.find(employee => String(employee.id_employees) === String(employeeId))
		},

		employeeUidById(employeeId) {
			return String(this.employeeById(employeeId)?.id_user || '')
		},

		employeeNameById(employeeId) {
			const employee = this.employeeById(employeeId)
			return String(employee?.displayname || employee?.id_user || '')
		},

		teamLeaderUid(team) {
			return String(team?.leader_uid || this.teamLeaderUidFromValue(team?.team_leader_id) || '')
		},

		teamLeaderName(team) {
			return String(team?.leader_name || this.teamLeaderNameFromValue(team?.team_leader_id) || this.teamLeaderUid(team))
		},

		teamLeaderUidFromValue(value) {
			const raw = String(value ?? '').trim()
			if (!raw) return ''
			if (!/^\d+$/.test(raw)) return raw
			return this.employeeUidById(raw)
		},

		teamLeaderNameFromValue(value) {
			const uid = this.teamLeaderUidFromValue(value)
			if (!uid) return ''
			const employee = (Array.isArray(this.Employee) ? this.Employee : [])
				.find(item => String(item.id_user) === uid)
			return String(employee?.displayname || uid)
		},

		async GetAllEquipo(equipo) {
			try {
				if (equipo !== '' && equipo !== null && equipo !== undefined) {
					const response = await axios.get(generateUrl('/apps/employees/GetEmpleadosEquipo/' + equipo))
					const data = response?.data?.ocs?.data
					this.peopleEquipo = data
				}
			} catch (err) {
				// eslint-disable-next-line no-console
				console.log(err)
			}
		},

		generateChar(user, gerente, socio) {
			if (!gerente) gerente = '?'
			if (!socio) socio = '?'
			return {
				id: 'nodo-oculto',
				children: [
					{ id: '1', name: socio, title: t('employees', 'Boss') },
					{ id: '2', name: gerente, title: t('employees', 'Manager') },
					{ id: '3', name: user, title: t('employees', 'Employee') },
				],
			}
		},

		calcularAniversarioDesdeFecha(fechaStr) {
			if (!fechaStr) return null

			const partes = String(fechaStr).split('-')
			if (partes.length !== 3) return null

			// Construir en hora LOCAL (año, mes 0-indexado, día) — evita el shift de UTC
			const hireDate = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]))
			if (Number.isNaN(hireDate.getTime())) return null

			const hoy = new Date()
			let años = hoy.getFullYear() - hireDate.getFullYear()
			const diffMeses = hoy.getMonth() - hireDate.getMonth()

			if (diffMeses < 0 || (diffMeses === 0 && hoy.getDate() < hireDate.getDate())) {
				años--
			}

			return Math.max(0, años)
		},

		checknull(value) {
			return value ?? ''
		},

		normalizeEmployeeForeignKey(value) {
			const raw = value?.id ?? value?.value ?? value
			if (raw === '' || raw === null || raw === undefined) return null
			if (/^\d+$/.test(String(raw))) return Number(raw)
			const employee = (Array.isArray(this.Employee) ? this.Employee : [])
				.find(item => String(item.id_user) === String(raw))
			return employee ? Number(employee.id_employees) : null
		},

		async saveFromEmployeeToolbar() {
			return this.CambiosEmpleado(false)
		},

		async CambiosEmpleado(closeEditing = true) {
			try {
				this.areaSend = this.area?.value
				this.puestoSend = this.puesto?.value

				if (!this.area?.value) {
					this.areaSend = this.optionsarea.find(role => role.label === this.area)?.value || ''
				}

				if (!this.puesto?.value) {
					this.puestoSend = this.optionspuesto.find(role => role.label === this.puesto)?.value || ''
				}

				const partnerId = this.normalizeEmployeeForeignKey(this.socio)
				const managerId = this.normalizeEmployeeForeignKey(this.gerente)
				const teamId = this.Equipo?.value ?? this.Equipo ?? null

				await axios.post(generateUrl('/apps/employees/CambiosEmpleado'), {
					id_employees: this.data.id_employees,
					numberEmployee: this.checknull(this.number_employee),
					hireDate: this.checknull(this.hire_date),
					area: this.checknull(this.areaSend),
					puesto: this.checknull(this.puestoSend),
					socio: partnerId,
					gerente: managerId,
					fundCode: this.checknull(this.fund_code),
					savingsFund: this.checknull(this.savings_fund),
					accountNumber: this.checknull(this.number_account),
					assignedTeam: '',
					equipo: teamId === '' ? null : teamId,
					salary: this.checknull(this.salary),
					id_anniversary: this.checknull(this.Aniversario),
					days_available: this.checknull(this.Vacaciones),
				})
				if (this.inventoryEnabled && this.canModifyInventory) {
					const response = await axios.put(generateUrl(`/apps/employees/inventario/employees/${this.data.id_employees}/Team`), {
						Team: this.Teams_asignados.map(equipoAsignado => equipoAsignado.id_team),
					})
					this.Teams_asignados = this.normalizeInventoryTeamsResponse(response, 'Team')
					showSuccess(t('employees', 'Equipment assigned successfully'), { close: true })
				}
				await this.cambioEstado(this.state ? '1' : '0')
				await this.GetAllEquipo(teamId)
				if (closeEditing) {
					this.$bus.emit('getall')
					this.$bus.emit('show', false)
					showSuccess(t('employees', 'Data updated'), { close: true })
				}
				return true
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepción [03] [{error}]', { error: String(err), close: true }))
				return false
			}
		},

		async cambioEstado(state) {
			try {
				await axios.post(generateUrl('/apps/employees/ActualizarEstadoAhorro'), {
					id_savings: this.data.id_savings,
					state,
				})
			} catch (err) {
				showError(err)
			}
		},
		showDetails(data) {
			// eslint-disable-next-line no-console
			console.log(data)
			this.$bus.emit('send-data', data)
			this.$bus.emit('show', false)
		},
		async getInventoryTeams(idEmployee) {
			if (!this.inventoryEnabled || !this.canAccessInventory) {
				return
			}

			this.cargandoTeams = true
			this.errorTeams = ''

			try {
				const [optionsResponse, assignedResponse] = await Promise.all([
					axios.get(
						generateUrl('/apps/employees/GetInventoryTeamsSelect'),
						{
							params: {
								employee: idEmployee,
								onlyAvailable: true,
							},
						},
					),

					axios.get(
						generateUrl(
							`/apps/employees/inventario/employees/${idEmployee}/Team`,
						),
					),
				])

				const availableData = this.normalizeInventoryTeamsResponse(
					optionsResponse,
				)

				const assignedData = this.normalizeInventoryTeamsResponse(
					assignedResponse,
					'Team',
				)

				const availableTeams = availableData
					.map(equipo => this.normalizeInventoryEquipo(equipo))
					.filter(Boolean)

				const assignedTeams = assignedData
					.map(equipo => this.normalizeInventoryEquipo(equipo))
					.filter(Boolean)

				/*
				 * Incluye:
				 * - Team disponibles;
				 * - Team que ya pertenecen al empleado.
				 *
				 * Esto evita que una asignación existente desaparezca del select
				 * cuando onlyAvailable excluye Team ocupados.
				 */
				const teamsMap = new Map()

				availableTeams.forEach((equipo) => {
					teamsMap.set(String(equipo.id_team), equipo)
				})

				assignedTeams.forEach((equipo) => {
					teamsMap.set(String(equipo.id_team), {
						...teamsMap.get(String(equipo.id_team)),
						...equipo,
					})
				})

				this.inventarioTeams = Array.from(teamsMap.values())

				this.Teams_asignados = assignedTeams.map((equipo) => {
					return this.findInventoryEquipo(equipo.id_team) || equipo
				})
			} catch (err) {
				this.Teams_asignados = []
				this.errorTeams = t(
					'employees',
					'Could not load assigned equipment',
				)

				showError(this.errorTeams)
			} finally {
				this.cargandoTeams = false
			}
		},

		normalizeInventoryEquipo(equipo) {
			if (!equipo || typeof equipo !== 'object') {
				return null
			}

			const id = equipo.id_team ?? equipo.value

			if (id === null || id === undefined || id === '') {
				return null
			}

			const normalized = {
				...equipo,
				value: id,
				id_team: id,
				device_name: equipo.device_name || '',
				system_name: equipo.system_name || '',
				serial_number: equipo.serial_number || '',
				status: equipo.status || '',
				brand: equipo.brand || '',
				model: equipo.model || '',
				empleado_id: equipo.empleado_id ?? null,
				employee_uid: equipo.employee_uid ?? null,
			}

			normalized.label = equipo.label
				|| this.inventarioEquipoLabel(normalized)
				|| `${t('employees', 'Equipment')} #${id}`

			return normalized
		},

		normalizeInventoryTeamsResponse(response, key = 'data') {
			const payload = response?.data?.ocs?.data || response?.data || response

			if (Array.isArray(payload)) {
				return payload
			}

			if (Array.isArray(payload?.[key])) {
				return payload[key]
			}

			if (Array.isArray(payload?.ocs?.data)) {
				return payload.ocs.data
			}

			if (Array.isArray(payload?.ocs?.data?.data)) {
				return payload.ocs.data.data
			}

			return []
		},

		inventarioEquipoLabel(equipo) {
			return [
				equipo.device_name,
				equipo.system_name,
				equipo.serial_number,
				equipo.brand && equipo.model ? `${equipo.brand} ${equipo.model}` : '',
			]
				.filter(Boolean)
				.join(' - ')
		},

		findInventoryEquipo(value) {
			const id = typeof value === 'object' ? value.value : value

			return this.inventarioTeams.find(equipo => {
				return String(equipo.value) === String(id)
			|| String(equipo.label) === String(id)
			}) || ''
		},
		equipoModel(equipo) {
			return [equipo.brand, equipo.model].filter(Boolean).join(' ')
		},
		equipoOptionTitle(option) {
			if (!option) {
				return ''
			}

			return option.device_name
		|| option.system_name
		|| option.label
		|| `${t('employees', 'Equipment')} #${option.value || option.id_team || ''}`
		},

		equipoOptionSubtitle(option) {
			if (!option) {
				return ''
			}

			return [
				option.system_name && option.system_name !== option.device_name
					? option.system_name
					: '',
				option.serial_number ? `${t('employees', 'Serial')}: ${option.serial_number}` : '',
				option.brand || option.model
					? [option.brand, option.model].filter(Boolean).join(' ')
					: '',
			]
				.filter(Boolean)
				.join(' · ')
		},

		async GuardarDiasDerecho() {
			if (String(this.Vacaciones) === String(this.diasDerechoOriginal)) return

			this.guardandoDias = true
			try {
				await axios.post(generateUrl('/apps/employees/AsignarDiasDerecho'), {
					id_employee: this.data.id_employees,
					days_available: this.checknull(this.Vacaciones),
				})
				this.diasDerechoOriginal = this.Vacaciones
				showSuccess(t('employees', 'Días de vacaciones asignados'), { close: true })
			} catch (err) {
				showError(t('employees', 'No se pudieron asignar los días [{error}]', { error: String(err), close: true }))
			} finally {
				this.guardandoDias = false
			}
		},

		async cargarPeriodoActual(idEmployee) {
			if (!idEmployee) return

			this.cargandoPeriodo = true
			this.Aniversario = ''
			this.Vacaciones = ''

			try {
				const response = await axios.post(generateUrl('/apps/employees/GetAusenciasByUser'), {
					id: idEmployee,
				})
				const periodo = response?.data?.ocs?.data?.[0]

				if (periodo) {
					this.Aniversario = this.checknull(periodo.id_anniversary)
					this.Vacaciones = this.checknull(periodo.days_available)
					this.diasDerechoOriginal = this.Vacaciones
				}
			} catch (err) {
				showError(t('employees', 'No se pudo cargar el periodo de vacaciones [{error}]', { error: String(err), close: true }))
			} finally {
				this.cargandoPeriodo = false
			}
		},

		incrementarVacaciones(delta) {
			const actual = Number(this.Vacaciones) || 0
			this.Vacaciones = Math.max(0, actual + delta)
		},
	},
}
</script>

<style>
.orgchart td {
	background-color: transparent;
}
.top {
	margin-top: 14px;
}

.main {
	display: flex;
	flex-wrap: wrap;
	gap: 18px;
	align-items: flex-start;
}

.box {
	display: flex;
}

.box1 {
	flex: 3 1 620px;
	min-width: 0;
	padding: 0 20px 0 0;
}

.box2 {
	flex: 2 1 360px;
	min-width: 320px;
}

.box1Inside {
	flex: 1 1 210px;
	min-width: 180px;
}

.flexible {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-start;
	gap: 14px;
}

.MarginRight {
	padding-right: 5px;
}

.labeltype {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	min-height: 24px;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.labeltype .material-design-icon {
	color: var(--color-primary-element);
}

.inputtype {
	width: 100%;
	min-height: 40px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 14px;
	transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
}

.inputtype:focus {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element-light);
	outline: none;
}

.inputtype:disabled {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	cursor: not-allowed;
	opacity: 1;
}

.inputtype:hover:not(:disabled) {
	border-color: var(--color-primary-element-light);
}

.divider {
	position: relative;
	margin: 22px 0 14px;
	text-align: left;
}

.divider::before {
	content: "";
	position: absolute;
	top: 50%;
	left: 0;
	width: 100%;
	height: 1px;
	background: var(--color-border);
	z-index: 0;
}

.divider span {
	position: relative;
	z-index: 1;
	display: inline-flex;
	padding: 0 12px 0 0;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 15px;
	font-weight: 700;
}

.label-input-trabajo,
.label-input-puesto {
	display: grid;
	align-items: center;
	width: 100%;
	min-width: 0;
}

.label-input-puesto {
	margin-top: 2px;
}

.box-chart {
	margin: 2px 0 16px;
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.box-chart .orgchart-container {
	display: block;
	width: 100%;
	height: auto;
	overflow: visible;
	border: 0;
	background: transparent;
}

.box-chart .orgchart {
	min-height: 10px;
	background: transparent;
	background-image: none;
}

.box-chart .orgchart .node .title {
	box-sizing: border-box;
	width: 100%;
	height: auto;
	min-height: 32px;
	padding: 6px 10px;
	overflow: hidden;
	border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 12px;
	font-weight: 700;
	line-height: 20px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.box-chart .orgchart .node .content {
	box-sizing: border-box;
	width: 100%;
	height: auto;
	min-height: 82px;
	padding: 10px 12px;
	overflow: visible;
	border-color: var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	line-height: normal;
	white-space: normal;
}

.center {
	text-align: center;
}

.avatar-chart {
	display: flex;
	justify-content: center;
}

.name-chart {
	max-width: 150px;
	margin-top: 6px;
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 13px;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.topRefresh {
	display: flex;
	align-items: center;
	min-height: 40px;
	margin-top: 30px;
}

.rst-title {
	width: auto;
	margin-top: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
	background: var(--color-background-hover);
}

.title_flex {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	min-height: 48px;
	padding: 8px 12px;
}

.title_flex h1 {
	margin: 0;
	font-size: 16px;
	font-weight: 700;
	line-height: 1.3;
}

.subtitle_flex {
	display: flex;
	align-items: center;
	padding-top: 0;
	margin-right: 0;
}

.rst {
	padding: 4px 0;
	border: 1px solid var(--color-border);
	border-top: 0;
	border-radius: 0 0 var(--border-radius-large) var(--border-radius-large);
	background: var(--color-main-background);
}

.team-list {
	max-height: calc(30vh - 4rem);
	padding: 0;
	margin: 0;
	overflow-y: auto;
}

.div-center {
	display: flex;
	justify-content: center;
	margin: 22px 0 4px;
}

.wrapper {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	align-items: flex-end;
}

.external-label {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-top: 2px;
}

.labelEmpleado {
	display: inline-flex;
	align-items: center;
	min-width: 150px;
	gap: 5px;
	font-weight: bold;
}

.item {
	width: 100px;
	margin: 10px;
	border-radius: 8px;
	box-shadow: 0 2px 10px rgba(0, 41, 0, 0.12);
}

.float,
.inline-b {
	max-width: 1200px;
	margin: 0 auto;
}

.float:after {
	display: block;
	height: 0;
	clear: both;
	visibility: hidden;
	content: ".";
}

.float-item {
	float: left;
}

.inline-b-item {
	display: inline-block;
}

#nodo-oculto {
	display: none;
	height: 0;
	padding: 0;
	margin: 0;
}

@media (max-width: 768px) {
	.top {
		margin-top: 8px;
	}

	.main {
		gap: 12px;
	}

	.box1,
	.box2 {
		flex: 1 1 100%;
		min-width: 0;
		padding-right: 0;
	}

	.box1Inside {
		flex-basis: 100%;
		min-width: 0;
	}

	.topRefresh {
		width: 100%;
		margin-top: 4px;
	}
	.equipo-asignado-field {
		grid-template-columns: 1fr;
		max-width: none;
		min-width: 0;
	}

	.equipo-asignado-field .labeltype {
		margin-bottom: 6px;
	}

}
.equipo-asignado-field {
	display: grid;
	grid-template-columns: 190px minmax(320px, 1fr);
	align-items: center;
	column-gap: 18px;
	row-gap: 8px;
	flex: 1 1 100%;
	max-width: 720px;
	min-width: 320px;
}

.equipo-asignado-field .labeltype {
	margin-bottom: 0;
	justify-content: flex-start;
}

.assigned-equipment-card {
	display: flex;
	width: 100%;
	margin-top: 12px;
	padding: 16px;
	gap: 14px;
	align-items: flex-start;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	text-align: left;
	grid-column: 2;
}

.assigned-equipment-card--interactive {
	cursor: pointer;
	transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
}

.assigned-equipment-card--interactive:hover {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.assigned-equipment-card--interactive:focus-visible {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element);
	outline: none;
}

.assigned-equipment-content {
	display: flex;
	min-width: 0;
	flex: 1;
	flex-direction: column;
	gap: 4px;
}

.assigned-equipment-heading {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
}

.assigned-equipment-heading strong {
	overflow-wrap: anywhere;
	font-size: 15px;
}

.assigned-equipment-link-hint,
.assigned-equipment-note,
.assigned-equipment-empty {
	margin: 8px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.assigned-equipment-empty {
	grid-column: 2;
}

.assigned-equipment-link-hint {
	color: var(--color-primary-element);
	font-weight: 600;
}

.equipo-computo-select .vs__dropdown-toggle {
	min-height: 44px;
	padding: 4px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
}

.equipo-computo-select.vs--open .vs__dropdown-toggle,
.equipo-computo-select .vs__dropdown-toggle:focus-within {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element-light);
}

.equipo-computo-select.vs--disabled .vs__dropdown-toggle {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	cursor: not-allowed;
	opacity: 1;
}

.equipo-computo-select .vs__selected-options {
	min-width: 0;
	padding: 0;
}

.equipo-computo-select .vs__selected {
	display: flex;
	align-items: center;
	max-width: 100%;
	min-width: 0;
	margin: 0;
	padding: 0;
	color: var(--color-main-text);
}

.equipo-computo-select .vs__search {
	min-width: 0;
	margin: 0;
	padding: 0 4px;
	color: var(--color-main-text);
}

.equipo-computo-select .vs__actions {
	padding: 0 2px 0 8px;
}

.equipo-computo-select .vs__dropdown-menu {
	width: 100%;
	min-width: 420px;
	max-height: 320px;
	padding: 6px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
}

.equipo-computo-select .vs__dropdown-option {
	padding: 0;
	border-radius: var(--border-radius-large);
	color: var(--color-main-text);
}

.equipo-computo-select .vs__dropdown-option--highlight {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}
.equipo-selected-option {
	display: flex;
	flex-direction: column;
	justify-content: center;
	min-width: 0;
	line-height: 1.25;
}

.equipo-selected-option strong {
	display: block;
	max-width: 100%;
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.equipo-selected-option span {
	display: block;
	max-width: 100%;
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.equipo-dropdown-option {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 10px 12px;
}

.equipo-dropdown-main {
	display: flex;
	gap: 8px;
	align-items: center;
	justify-content: space-between;
	min-width: 0;
}

.equipo-dropdown-main strong {
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.equipo-dropdown-subtitle {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.equipo-status {
	flex-shrink: 0;
	padding: 2px 8px;
	border-radius: 999px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 700;
	text-transform: capitalize;
}

.equipo-status--active,
.equipo-status--asignado {
	background: var(--color-success);
	color: var(--color-primary-element-text);
}

.equipo-status--mantenimiento {
	background: var(--color-warning);
	color: var(--color-main-text);
}

.equipo-status--baja,
.equipo-status--inactivo {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.stepper-wrapper {
	display: flex;
	align-items: stretch;
	gap: 6px;
}

.stepper-input {
	padding-right: 12px;
	-moz-appearance: textfield;
}

.stepper-input::-webkit-outer-spin-button,
.stepper-input::-webkit-inner-spin-button {
	margin: 0;
	-webkit-appearance: none;
}

.stepper-arrows {
	display: flex;
	flex-direction: column;
	justify-content: center;
	gap: 2px;
	flex-shrink: 0;
}

/* Selector reforzado + !important para ganarle al botón default de Nextcloud */
.stepper-wrapper .stepper-arrows button.stepper-btn {
	display: flex !important;
	width: 20px !important;
	height: 16px !important;
	min-width: 0 !important;
	min-height: 0 !important;
	align-items: center;
	justify-content: center;
	padding: 0 !important;
	margin: 0 !important;
	border: 1px solid var(--color-border) !important;
	border-radius: 5px !important;
	background: var(--color-background-hover) !important;
	box-shadow: none !important;
	color: var(--color-text-maxcontrast);
	line-height: 0;
	cursor: pointer;
	transition: background-color 100ms ease, color 100ms ease, border-color 100ms ease;
}

.stepper-wrapper .stepper-arrows button.stepper-btn:disabled {
	cursor: not-allowed;
	opacity: 0.35;
}

.stepper-wrapper .stepper-arrows button.stepper-btn:hover:not(:disabled) {
	background: var(--color-primary-element-light) !important;
	border-color: var(--color-primary-element) !important;
	color: var(--color-primary-element);
}

.stepper-wrapper .stepper-arrows button.stepper-btn :deep(svg) {
	width: 11px !important;
	height: 11px !important;
	margin: 0 !important;
}

@media (max-width: 768px) {
	.assigned-equipment-card,
	.assigned-equipment-empty {
		grid-column: 1;
	}
}

.empleado-content {
	/* ya no necesita ser relative/flotante: el switch va arriba, centrado */
}

/* Switch Information / OnBoarding — arriba, centrado */
.empleado-view-switch-wrapper {
	display: flex;
	justify-content: center;
	margin-bottom: 8px;
}

.empleado-view-switch {
	display: inline-flex;
	gap: 2px;
	padding: 4px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 999px;
	box-shadow: 0 6px 20px rgba(15, 23, 42, 0.14);
}

.empleado-switch-btn,
.empleado-switch-btn:hover,
.empleado-switch-btn:focus,
.empleado-switch-btn:focus-visible,
.empleado-switch-btn:active {
	all: unset;
	box-sizing: border-box;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 7px 18px;
	border-radius: 999px;
	font-size: 12.5px;
	font-weight: 600;
	letter-spacing: 0.02em;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}

.empleado-switch-btn:hover:not(.active) {
	color: var(--color-main-text);
}

.empleado-switch-btn.active,
.empleado-switch-btn.active:hover,
.empleado-switch-btn.active:focus,
.empleado-switch-btn.active:active {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text, #fff);
	box-shadow: 0 2px 10px rgba(52, 120, 246, 0.35);
}

@media (max-width: 600px) {
	.empleado-view-switch {
		width: 100%;
	}

	.empleado-switch-btn {
		flex: 1;
		padding: 7px 8px;
	}
}

/* Encabezado del checklist: título a la izquierda, toggle a la derecha */
.OnboardingItem-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: nowrap;
}

.OnboardingItem-divider {
	flex: 1 1 auto;
	min-width: 0;
	margin: 22px 0 14px;
}

.OnboardingItem-toggle-wrapper {
	display: flex;
	flex-shrink: 0;
	padding-top: 34px;
}

/* Toggle deslizante, minimalista — todo forzado con !important porque
   los estilos globales de botón de Nextcloud (incluyendo :hover/:focus/:active)
   traen su propio background/box-shadow que si no, se cuela encima */
.OnboardingItem-toggle {
	position: relative;
	display: inline-flex !important;
	flex-shrink: 0;
	width: 76px !important;
	height: 20px !important;
	padding: 2px !important;
	margin: 0 !important;
	background: var(--color-background-darker, var(--color-background-hover)) !important;
	border: 1px solid var(--color-border) !important;
	border-radius: 999px !important;
	box-sizing: border-box;
}

.OnboardingItem-toggle-thumb {
	position: absolute;
	top: 2px;
	left: 2px;
	width: calc(50% - 2px);
	height: calc(100% - 4px);
	border-radius: 999px;
	background: var(--color-primary-element);
	transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	pointer-events: none;
}

.OnboardingItem-toggle--off .OnboardingItem-toggle-thumb {
	transform: translateX(100%);
}

.OnboardingItem-toggle-btn,
.OnboardingItem-toggle-btn:hover,
.OnboardingItem-toggle-btn:focus,
.OnboardingItem-toggle-btn:focus-visible,
.OnboardingItem-toggle-btn:active {
	all: unset;
	position: relative;
	z-index: 1;
	box-sizing: border-box;
	display: flex !important;
	flex: 1 1 0;
	align-items: center;
	justify-content: center;
	height: 100% !important;
	min-height: 0 !important;
	padding: 0 !important;
	margin: 0 !important;
	background: transparent !important;
	border: 0 !important;
	border-radius: 999px !important;
	outline: 0 !important;
	box-shadow: none !important;
	font-size: 9.5px !important;
	font-weight: 700 !important;
	line-height: 1 !important;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	-webkit-appearance: none;
	appearance: none;
	transition: color 0.18s ease;
}

.OnboardingItem-toggle-btn:disabled {
	cursor: not-allowed;
	opacity: 0.6;
}

.OnboardingItem-toggle-btn:hover:not(:disabled):not(.active) {
	color: var(--color-main-text) !important;
}

.OnboardingItem-toggle-btn.active,
.OnboardingItem-toggle-btn.active:hover,
.OnboardingItem-toggle-btn.active:focus {
	color: var(--color-primary-element-text, #fff) !important;
}

/* Checklist funcional */
.onboarding-checklist {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 0;
	margin: 4px 0 0;
	list-style: none;
}

.onboarding-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	padding: 4px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 14px;
	transition: background-color 120ms ease, border-color 120ms ease;
}

.onboarding-item--done {
	background: var(--color-background-dark);
	border-color: var(--color-border);
}

.onboarding-item--done :deep(.checkbox-radio-switch__label) {
	color: var(--color-text-maxcontrast);
	text-decoration: line-through;
}

.OnboardingItem-empty {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

@media (max-width: 600px) {
	.OnboardingItem-header {
		flex-direction: column;
		align-items: stretch;
	}

	.OnboardingItem-toggle-wrapper {
		align-self: flex-end;
		padding-top: 0;
		margin-top: 8px;
	}
}

.assigned-equipment-list {
	display: grid;
	grid-column: 2;
	width: 100%;
	gap: 10px;
	margin-top: 12px;
}

.assigned-equipment-list .assigned-equipment-card {
	grid-column: auto;
	margin-top: 0;
}
@media (max-width: 768px) {
	.assigned-equipment-list {
		grid-column: 1;
	}
}
</style>
