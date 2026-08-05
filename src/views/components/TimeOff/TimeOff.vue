<template>
	<NcAppContent name="Loading">
		<div class="">
			<div class="text-center section-calendar">
				<div v-if="Settings.modulo_ausencias_readonly === 'true'">
					<br>
					<NcNoteCard type="error"
						:heading="t('employees', 'Attention!!!')"
						:text="t('employees', 'The module is in read-only mode')" />
					<br>
				</div>
				<section class="layout">
					<div class="grow2">
						<div ref="calendarViewport" class="text-center sectionPicker">
							<FullCalendar
								ref="fullCalendar"
								:options="calendarOptions"
								class="my-calendar" />
						</div>
					</div>
					<div class="grow1">
						<div ref="sidebar" class="cards">
							<div class="headers">
								<div class="header-content">
									<h2 class="h2-white">
										{{ t('employees', 'Vacation') }}
									</h2>
									<div class="vacations">
										<div
											class="vacations-grid"
											:class="{ 'vacations-grid--single': !tieneVacacionesAcumuladas }">
											<!-- Periodo actual -->
											<div class="vacation-card">
												<span class="vacation-card__title">
													{{ t('employees', 'Current period') }}
												</span>

												<NcLoadingIcon
													v-if="absenceLoading"
													:size="22" />

												<template v-else-if="hasEmployeeProfile">
													<strong class="vacation-card__value">
														{{ Ausencias.days_available ?? 0 }}
													</strong>

													<span class="vacation-card__subtitle">
														{{ t('employees', 'Days available') }}
													</span>
												</template>

												<template v-else>
													<strong class="vacation-card__value">—</strong>
													<span class="vacation-card__subtitle">
														{{ t('employees', 'Your user is not linked to an employee profile.') }}
													</span>
												</template>
											</div>

											<!-- Periodo anterior acumulado -->
											<div
												v-if="tieneVacacionesAcumuladas"
												class="vacation-card vacation-card--accumulated">
												<span class="vacation-card__title">
													{{ t('employees', 'Previous period') }}
												</span>

												<strong class="vacation-card__value">
													{{ Ausencias.accrued_days }}
												</strong>

												<span class="vacation-card__subtitle">
													{{ t('employees', 'Accumulated days') }}
												</span>

												<div class="vacation-card__warning">
													<AlertOutline :size="13" />

													<span>
														{{ t('employees', 'Use before {date} or they expire', {
															date: new Date(
																Ausencias.accrued_expiration_date,
															).toLocaleDateString('es-MX'),
														}) }}
													</span>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="infos">
								<!-- Notificaciones pendientes -->
								<div
									v-if="notificaciones"
									class="acordeon-item acordeon-item--warning">
									<button
										type="button"
										class="acordeon-notification"
										@click="togglePendingNotifications">
										<div class="noti-wrapper">
											<BellOutline
												class="bell-icon"
												:class="{ 'bell-shake': isShaking }" />

											<NcCounterBubble
												:count="notifications_counter"
												class="noti-badge" />
										</div>

										<span class="noti-text">
											{{ t('employees', 'Pending') }}
										</span>

										<span class="arrow">
											{{ accordeon[0].abierto ? '−' : '+' }}
										</span>
									</button>

									<div
										:class="[
											'acordeon-contenido',
											{ abierto: accordeon[0].abierto },
										]">
										<ul class="pending-list">
											<li
												v-for="item in notifications_result"
												:key="item.absence_history_id"
												class="pending-list__item">
												<button
													type="button"
													class="pending-card"
													@click="abrirDetalleDesdeNotificacion(item)">
													<NcAvatar
														disable-menu
														class="pending-card__avatar"
														:size="38"
														:user="item.id_user"
														:display-name="notificationEmployeeName(item)" />

													<span class="pending-card__content">
														<strong class="pending-card__name">
															{{ notificationEmployeeName(item) }}
														</strong>

														<span class="pending-card__type">
															{{ notificationAbsenceType(item) }}
														</span>
													</span>

													<span
														v-if="notificationRequestedDays(item) !== null"
														class="pending-card__days"
														:title="formatearDias(notificationRequestedDays(item))">

														{{ notificationRequestedDays(item) }}
													</span>
												</button>
											</li>
										</ul>
									</div>
								</div>

								<!-- Administración -->
								<div
									v-if="isAdmin()"
									class="acordeon-item acordeon-item--admin">
									<button
										type="button"
										class="acordeon-title"
										@click="toggle(2)">
										<span class="accordion-title">
											<span class="accordion-title__label">
												{{ t('employees', 'Administrative') }}
											</span>

											<span class="accordion-title__description">
												{{ t('employees', 'Reports and employee filters') }}
											</span>
										</span>

										<span class="arrow">
											{{ accordeon[2].abierto ? '−' : '+' }}
										</span>
									</button>

									<div
										:class="[
											'acordeon-contenido',
											{ abierto: accordeon[2].abierto },
										]">
										<div class="accordion-menu accordion-menu--select">
											<NcButton
												class="accordion-action-button"
												variant="secondary"
												wide
												@click="mostrarReporte = true">
												{{ t('employees', 'Show report') }}
											</NcButton>

											<div class="accordion-field">
												<span class="accordion-field__label">
													{{ t('employees', 'Filter by employee') }}
												</span>

												<NcSelect
													v-bind="propsEmployees"
													v-model="employees"
													@update:model-value="onEmployeesChange" />
												<NcButton
													class="accordion-action-button accordion-action-button--all"
													variant="secondary"
													wide
													@click="mostrarTodosAdministrativo">
													{{ t('employees', 'Show all employees') }}
												</NcButton>
											</div>
										</div>
									</div>
								</div>

								<!-- Filtrar por equipo -->
								<div
									v-if="Object.keys(Equipo).length"
									class="acordeon-item">
									<button
										type="button"
										class="acordeon-title"
										@click="toggle(1)">
										<span class="accordion-title">
											<span class="accordion-title__label">
												{{ t('employees', 'Filter by team') }}
											</span>

											<span class="accordion-title__description">
												{{ Equipo.name }}
											</span>
										</span>

										<span class="arrow">
											{{ accordeon[1].abierto ? '−' : '+' }}
										</span>
									</button>

									<div
										:class="[
											'acordeon-contenido',
											{ abierto: accordeon[1].abierto },
										]">
										<div class="accordion-menu">
											<button
												type="button"
												class="accordion-option accordion-option--group"
												@click="
													typePetition = 'all';
													$refs.fullCalendar.getApi().refetchEvents()
												">
												<NcAvatar
													:user="Equipo.team_leader_id"
													:display-name="Equipo.team_leader_id"
													:size="34" />

												<span class="accordion-option__text">
													<strong>{{ Equipo.name }}</strong>

													<small>
														{{ t('employees', 'Show the entire team') }}
													</small>
												</span>

												<AccountGroup
													:size="21"
													class="accordion-option__icon" />
											</button>

											<ul class="accordion-user-list">
												<li
													v-for="item in peopleEquipo.equipo"
													:key="item.id_employees">
													<button
														type="button"
														class="accordion-option"
														@click="
															employees = [];
															typePetition = 'employee';
															selected_user = item;
															$refs.fullCalendar.getApi().refetchEvents()
														">
														<NcAvatar
															disable-menu
															:size="36"
															:user="item.id_user"
															:display-name="item.id_user" />

														<span class="accordion-option__text">
															<strong>
																{{ item.displayname || item.id_user }}
															</strong>

															<small>{{ item.id_user }}</small>
														</span>
													</button>
												</li>
											</ul>
										</div>
									</div>
								</div>

								<!-- Mis Employee -->
								<div
									v-if="subordinates.length > 0"
									class="acordeon-item">
									<button
										type="button"
										class="acordeon-title"
										@click="toggle(3)">
										<span class="accordion-title">
											<span class="accordion-title__label">
												{{ t('employees', 'My subordinates') }}
											</span>

											<span class="accordion-title__description">
												{{ subordinates.length }}
												{{ t('employees', 'employees') }}
											</span>
										</span>

										<span class="arrow">
											{{ accordeon[3].abierto ? '−' : '+' }}
										</span>
									</button>

									<div
										:class="[
											'acordeon-contenido',
											{ abierto: accordeon[3].abierto },
										]">
										<div class="accordion-menu">
											<button
												type="button"
												class="accordion-option accordion-option--group"
												@click="
													typePetition = 'all-employees';
													$refs.fullCalendar.getApi().refetchEvents()
												">
												<AccountGroup :size="34" />

												<span class="accordion-option__text">
													<strong>
														{{ t('employees', 'My subordinates') }}
													</strong>

													<small>
														{{ t('employees', 'Show all my subordinates') }}
													</small>
												</span>

												<AccountGroup
													:size="21"
													class="accordion-option__icon" />
											</button>

											<ul class="accordion-user-list">
												<li
													v-for="item in subordinates"
													:key="item.id_employees">
													<button
														type="button"
														class="accordion-option"
														@click="
															employees = [];
															typePetition = 'employee';
															selected_user = item;
															$refs.fullCalendar.getApi().refetchEvents()
														">
														<NcAvatar
															disable-menu
															:size="36"
															:user="item.id_user"
															:display-name="item.id_user" />

														<span class="accordion-option__text">
															<strong>
																{{ item.displayname || item.id_user }}
															</strong>

															<small>{{ item.id_user }}</small>
														</span>
													</button>
												</li>
											</ul>
										</div>
									</div>
								</div>

								<!-- Restablecer vista -->
								<div class="sidebar-reset">
									<NcButton
										class="sidebar-button"
										variant="secondary"
										wide
										@click="
											typePetition = null;
											selected_user = null;
											employees = [];
											$refs.fullCalendar.getApi().refetchEvents()
										">
										{{ t('employees', 'Show my absences') }}
									</NcButton>
								</div>
							</div>

							<div class="footers">
								<p>
									🔎 {{ vista_actual }}
								</p>
							</div>
						</div>
					</div>
				</section>
			</div>
		</div>

		<!-- ABSENCE REPORT MODAL -->
		<NcModal
			v-if="mostrarReporte"
			size="full"
			:can-close="false"
			:name="t('employees', 'Absence report')"
			@close="mostrarReporte = false">
			<AbsenceReport @close="mostrarReporte = false" />
		</NcModal>
		<!-- END ABSENCE REPORT MODAL -->
		<!-- EVENT DETAILS MODAL -->
		<NcModal
			v-if="modalEvento"
			ref="modalEventoRef"
			size="normal"
			:name="t('employees', 'Absence details')"
			@close="closeModalEvento">
			<AbsenceDetails
				v-if="selectedEventId"
				:id-historial="selectedEventId"
				:is-admin="isAdmin()"
				@cancelled="onAbsenceCancelled"
				@approved="onAbsenceCancelled"
				@rejected="onAbsenceCancelled"
				@edit="onAbsenceEdit" />
		</NcModal>
		<!-- END EVENT DETAILS MODAL -->

		<!-- ABSENCE REQUEST MODAL -->
		<NcModal v-if="modal"
			size="large"
			:name="t('employees', 'Absence form')"
			@close="closeModal">
			<NewRequest v-if="modal"
				ref="modalRef"
				:date="date"
				:days-solicitados="diasSolicitados"
				:days-disponibles="Ausencias.days_available"
				:days-acumulados="Ausencias.accrued_days"
				:date-expiracion-acumulados="Ausencias.accrued_expiration_date"
				:date-limite-periodo-actual="Ausencias.fecha_limite_periodo_actual"
				:prima="Ausencias.bonus_vacation"
				:employees="propsEmployees.options"
				:admin="isAdmin()"
				@close="closeModal" />
		</NcModal>
		<!-- END ABSENCE REQUEST MODAL -->

		<!-- EDIT ABSENCE MODAL -->
		<NcModal
			v-if="modalEditar"
			size="large"
			:name="t('employees', 'Edit absence')"
			@close="closeModalEditar">
			<EditAbsence
				v-if="modalEditar && ausenciaEditar"
				:ausencia="ausenciaEditar"
				:username-empleado="usuarioAusenciaSeleccionada"
				:days-disponibles="Ausencias.days_available"
				:date-limite-periodo-actual="Ausencias.fecha_limite_periodo_actual"
				:prima="Ausencias.bonus_vacation"
				:employees="propsEmployees.options"
				:admin="isAdmin()"
				@saved="onAbsenceEditSaved"
				@close="closeModalEditar" />
		</NcModal>
		<!-- END EDIT ABSENCE MODAL -->

		<!-- ANNIVERSARIES INFO MODAL -->
		<NcModal v-if="ModalAniversario"
			ref="modalRef"
			size="large"
			:name="t('employees', 'Anniversary table')"
			@close="closeModalAniversario">
			<div class="table_component" role="region" tabindex="0">
				<div class="modal__content">
					<div class="layout">
						<div class="grow3">
							<AnniversaryAwards :info="Ausencias" :acumular="Settings.acumular_vacaciones" />
							<br>
							<table>
								<caption>
									<span class="caption-title">{{ t('employees', 'Anniversary table') }}</span>
								</caption>
								<thead>
									<tr>
										<th>{{ t('employees', 'Anniversary(ies)') }}</th>
										<th>{{ t('employees', 'Days off') }}</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="(grupo, index) in AniversariosAgrupados" :key="index">
										<td>
											<span v-if="grupo.desde === grupo.hasta">
												{{ grupo.desde }}
											</span>
											<span v-else>
												{{ t('employees', '{from} to {to}', {
													from: grupo.desde, to: grupo.hasta
												}) }}
											</span>
										</td>
										<td>{{ grupo.days }}</td>
									</tr>
								</tbody>
							</table>
						</div>
						<div class="grow4">
							<AnniversariesMessage :info="Ausencias" :acumular="Settings.acumular_vacaciones" />
						</div>
					</div>
				</div>
			</div>
		</NcModal>
		<div class="floating-help-button">
			<NcActions>
				<NcActionButton @click="showAniversarioModal">
					<template #icon>
						<CalendarQuestionOutline :size="24" />
					</template>

					{{ t('employees', 'My information') }}
				</NcActionButton>
			</NcActions>
		</div>
		<!-- END ANNIVERSARIES INFO MODAL -->
	</NcAppContent>
</template>

<script>
import AnniversariesMessage from './AnniversariesMessage.vue'
import AnniversaryAwards from './AnniversaryAwards.vue'
import NewRequest from './Modal/NewRequest.vue'
import AbsenceDetails from './Modal/AbsenceDetails.vue'
import EditAbsence from './Modal/EditAbsence.vue'
import AbsenceReport from './AbsenceReport.vue'

import FullCalendar from '@fullcalendar/vue'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'
import enGbLocale from '@fullcalendar/core/locales/en-gb'
import ruLocale from '@fullcalendar/core/locales/ru'

import { ref } from 'vue'

import usernameToColor from '@nextcloud/vue/functions/usernameToColor'
import { showError, showInfo } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { getLanguage, translate as t } from '@nextcloud/l10n'

import BellOutline from 'vue-material-design-icons/BellOutline.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import CalendarQuestionOutline from 'vue-material-design-icons/CalendarQuestionOutline.vue'

import {
	NcAppContent,
	NcModal,
	NcActions,
	NcActionButton,
	NcAvatar,
	NcButton,
	NcSelect,
	NcCounterBubble,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

const fullCalendarLocale = String(getLanguage() || 'en').toLowerCase().startsWith('ru')
	? ruLocale
	: enGbLocale

export default {
	name: 'TimeOff',

	components: {
		AnniversariesMessage,
		AnniversaryAwards,
		NewRequest,
		AbsenceDetails,
		EditAbsence,
		NcAppContent,
		NcModal,
		NcActions,
		NcActionButton,
		AccountGroup,
		CalendarQuestionOutline,
		FullCalendar,
		NcAvatar,
		NcButton,
		NcSelect,
		NcCounterBubble,
		BellOutline,
		NcLoadingIcon,
		NcNoteCard,
		AbsenceReport,
	},

	inject: {
		employee: { default: () => [] },
		Settings: { default: () => ({}) },
		groupuser: { default: () => ({}) },
		subordinates: { default: () => [] },
	},

	data() {
		return {
			modalRef: ref(null),
			attributes: [],
			FechaInitial: null,
			FechaMaxima: null,
			modal: false,
			modalEvento: false,
			modalEditar: false,
			ModalAniversario: false,
			Ausencias: [],
			Aniversarios: [],
			Festivos: [],
			diasSolicitados: 0,
			date: ref({
				start: new Date(),
				end: null,
			}),
			range: null,
			calendarOptions: {
				headerToolbar: {
					left: '',
					center: 'title',
					right: 'multiMonthYear,dayGridMonth,today,prev,next',
				},
				initialView: 'dayGridMonth',
				locale: fullCalendarLocale,
				plugins: [dayGridPlugin, interactionPlugin, multiMonthPlugin],
				events: this.fetchEvents,
				dateClick: this.onDateClick,
				eventClick: this.OnClickEvent,
				select: this.onDateRangeSelect,
				dayCellDidMount: this.onDayCellDidMount,
				selectable: true,
				fixedWeekCount: false,
				dayMaxEvents: true,
				dayMaxEventRows: 10,
				multiMonthMaxColumns: 4,
				multiMonthMinWidth: 225,
				moreLinkClick: 'popover',
				eventContent(arg) {
					const nombreEmpleado = arg.event.extendedProps.employee_name || 'Unknown employee'
					const imgUrl = `/avatar/${nombreEmpleado}/64`
					return {
						html: `
							<div style="display:flex;align-items:center;">
							<img src="${imgUrl}" style="width:16px;height:16px;border-radius:50%;margin-right:4px;">
							<span>${arg.event.title}</span>
							</div>
						`,
					}
				},
			},
			peopleEquipo: {},
			Equipo: {},
			typePetition: null,
			selected_user: null,
			propsEmployees: {
				inputLabel: t('employees', 'All employees'),
				userSelect: true,
				multiple: true,
				closeOnSelect: false,
				options: [],
			},
			employees: [],
			infoSelected: null,
			vista_actual: t('employees', 'My absences'),
			accordeon: [
				{ abierto: false },
				{ abierto: false },
				{ abierto: false },
				{ abierto: false },
			],
			notificaciones: false,
			notifications_counter: 0,
			loading: false,
			absenceLoading: true,
			notifications_result: [],
			isShaking: false,
			selectedEventId: null,
			ausenciaEditar: null,
			mostrarReporte: false,
			usuarioAusenciaSeleccionada: null,
		}
	},

	computed: {
		currentEmployee() {
			if (Array.isArray(this.employee)) {
				return this.employee[0] || null
			}
			return this.employee || null
		},

		hasEmployeeProfile() {
			return Boolean(this.currentEmployee?.id_employees)
		},

		AniversariosAgrupados() {
			const agrupados = []
			let inicio = null
			let fin = null
			let diasActual = null

			this.Aniversarios.forEach((item, index) => {
				const diasNumero = Number(item.days)
				if (diasActual === null) {
					inicio = item.number_anniversary
					fin = item.number_anniversary
					diasActual = diasNumero
				} else if (diasNumero === diasActual) {
					fin = item.number_anniversary
				} else {
					agrupados.push({ desde: inicio, hasta: fin, days: diasActual })
					inicio = item.number_anniversary
					fin = item.number_anniversary
					diasActual = diasNumero
				}
				if (index === this.Aniversarios.length - 1) {
					agrupados.push({ desde: inicio, hasta: fin, days: diasActual })
				}
			})

			return agrupados
		},
		tieneVacacionesAcumuladas() {
			return Number(this.Ausencias?.accrued_days ?? 0) > 0
				&& Boolean(this.Ausencias?.accrued_expiration_date)
		},

		/**
		 * Diccionario 'MM-DD' -> name del festivo, para lookup O(1)
		 * al pintar cada celda del calendar.
		 */
		festivosPorFecha() {
			const mapa = {}
			this.Festivos.forEach(item => {
				if (item?.date && item?.name) {
					mapa[item.date] = item.name
				}
			})
			return mapa
		},
	},

	mounted() {
		this.$bus.on('close-solicitud', () => {
			this.GetAusencias()

			this.$nextTick(() => {
				this.$refs.fullCalendar?.getApi()?.refetchEvents()
			})

			this.closeModal()
		})
		this.GetAusencias()
		if (this.isAdmin()) {
			this.updateList()
		}
		if (this.currentEmployee) {
			this.getTeams()
			this.GetAllEquipo()
		}
		this.getFestivosCalendario()
		this.checkNotifications()
		this.$nextTick(() => {
			this.ajustarAlturaCalendario()
			window.addEventListener('resize', this.ajustarAlturaCalendario)
		})
	},

	beforeDestroy() {
		window.removeEventListener('resize', this.ajustarAlturaCalendario)
	},

	methods: {
		t,

		responsePayload(response) {
			return response?.data?.ocs?.data ?? response?.data ?? null
		},

		responseArray(response, ...keys) {
			const payload = this.responsePayload(response)
			const candidates = [payload, payload?.data, payload?.message]
			keys.forEach(key => candidates.push(payload?.[key]))
			return candidates.find(Array.isArray) || []
		},

		reportLoadError(error) {
			console.error(error)
			showError(t('employees', 'Could not fetch your information'))
		},

		/**
		 * Trae la lista de Holiday (date en formato MM-DD, se repite cada
		 * año) para pintarlos en el calendar. Como fullCalendar ya montó
		 * las celdas antes de que esta llamada regrese, forzamos un
		 * re-render con .render() para que dayCellDidMount se vuelva a
		 * ejecutar con festivosPorFecha ya lleno.
		 */
		async getFestivosCalendario() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/getFestivos'))
				this.Festivos = this.responseArray(response)
				this.$nextTick(() => {
					this.$refs.fullCalendar?.getApi()?.render()
				})
			} catch (err) {
				// No bloqueamos el calendar si esto falla, solo no se pintan los Holiday.
				console.error('No se pudieron cargar los Holiday:', err)
			}
		},

		/**
		 * Convierte un Date a 'MM-DD' (mismo formato que usa la tabla de Holiday).
		 * @param date
		 */
		formatMesDia(date) {
			const mes = String(date.getMonth() + 1).padStart(2, '0')
			const dia = String(date.getDate()).padStart(2, '0')
			return `${mes}-${dia}`
		},

		/**
		 * Indica si una date determinada corresponde a un día festivo
		 * registrado (usa el mismo diccionario 'MM-DD' -> name).
		 * @param date
		 */
		esFestivo(date) {
			return Boolean(this.festivosPorFecha[this.formatMesDia(date)])
		},

		/**
		 * Hook de FullCalendar: pinta en verde pastel la celda del día si
		 * corresponde a un festivo, y agrega una label elegante con el
		 * name debajo del número de día.
		 *
		 * El color se aplica como estilo en línea con priority "important"
		 * porque FullCalendar también usa reglas !important para "hoy" y
		 * para la selección, y sin esto a veces esas reglas ganaban y el
		 * verde desaparecía. Como excepción: si la celda es "hoy", dejamos
		 * que se vea el resaltado amarillo propio de FullCalendar aunque
		 * el día sea festivo (solo se mantienen la label y el bloqueo).
		 * @param arg
		 */
		onDayCellDidMount(arg) {
			const mesDia = this.formatMesDia(arg.date)
			const nombreFestivo = this.festivosPorFecha[mesDia]

			if (!nombreFestivo) {
				return
			}

			arg.el.classList.add('fc-day-festivo')
			arg.el.setAttribute('title', nombreFestivo)

			const esHoy = arg.el.classList.contains('fc-day-today')

			if (!esHoy) {
				const colorBase = '#e3f5e6'
				const colorHover = '#d9f0dd'
				arg.el.style.setProperty('background-color', colorBase, 'important')
				arg.el.addEventListener('mouseenter', () => {
					arg.el.style.setProperty('background-color', colorHover, 'important')
				})
				arg.el.addEventListener('mouseleave', () => {
					arg.el.style.setProperty('background-color', colorBase, 'important')
				})
			}

			// La label con el name solo se ve bien en vista de mes;
			// en multi-mes las celdas son muy pequeñas para texto legible.
			if (arg.view.type !== 'dayGridMonth') {
				return
			}

			const frame = arg.el.querySelector('.fc-daygrid-day-frame') || arg.el

			const label = document.createElement('div')
			label.className = 'fc-festivo-label'
			label.textContent = nombreFestivo
			// Tooltip nativo con el name completo por si el texto se corta.
			label.title = nombreFestivo
			frame.appendChild(label)
		},

		abrirDetalleDesdeNotificacion(item) {
			this.selectedEventId = item.absence_history_id
			this.usuarioAusenciaSeleccionada = item.id_user || null
			this.modalEvento = true
		},

		async checkNotifications() {
			if (this.subordinates.length === 0 && !this.isAdmin()) {
				return
			}

			try {
				const response = await axios.get(
					generateUrl(
						'/apps/employees/GetNotificationsSubordinates',
					),
				)

				const data = this.responseArray(response)

				if (Array.isArray(data) && data.length > 0) {
					this.notificaciones = true
					this.notifications_counter = data.length
					this.notifications_result = data
					this.startShaking()
				} else {
					this.notificaciones = false
					this.notifications_counter = 0
					this.notifications_result = []
				}
			} catch (err) {
				this.reportLoadError(err)
			}
		},

		startShaking() {
			setInterval(() => {
				this.isShaking = true
				setTimeout(() => { this.isShaking = false }, 900)
			}, 2000)
		},

		toggle(index) {
			this.accordeon = this.accordeon.map((item, i) => ({
				...item,
				abierto: i === index ? !item.abierto : false,
			}))
		},

		showAniversarioModal() { this.getAniversarios() },
		closeModal() { this.modal = false },
		closeModalAniversario() { this.ModalAniversario = false },

		closeModalEvento() {
			this.modalEvento = false
			this.selectedEventId = null
		},

		closeModalEditar() {
			this.modalEditar = false
			this.ausenciaEditar = null
		},

		async GetAusencias() {
			this.absenceLoading = true
			if (!this.hasEmployeeProfile) {
				this.Ausencias = {}
				this.absenceLoading = false
				return
			}

			try {
				const response = await axios.post(generateUrl('/apps/employees/GetAusenciasByUser'), {
					id: this.currentEmployee.id_employees,
				})
				this.Ausencias = {
					days_available: 0,
					...(this.responseArray(response)[0] || {}),
				}
			} catch (err) {
				this.Ausencias = {}
				this.reportLoadError(err)
			} finally {
				this.absenceLoading = false
			}
		},

		async getAniversarios() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/Getaniversarios'))
				this.Aniversarios = this.responseArray(response)
				this.ModalAniversario = true
			} catch (err) {
				this.reportLoadError(err)
			}
		},

		formatearDias(days) {
			const entero = Math.floor(days)
			const decimal = days % 1
			if (decimal === 0) {
				return `${entero} ${entero === 1 ? t('employees', 'day') : t('employees', 'days')}`
			} else if (decimal === 0.5) {
				return `${entero} ${entero === 1 ? t('employees', 'day') : t('employees', 'days')} ${t('employees', 'and a half')}`
			} else {
				return `${days} ${t('employees', 'days')}`
			}
		},

		onDatesSet() {
			this.$refs.fullCalendar.getApi().refetchEvents()
		},

		fetchEvents(fetchInfo, success, failure) {
			switch (this.typePetition) {
			case 'pending':
				this.getPendingAusencias(fetchInfo, success, failure)
				this.vista_actual = t('employees', 'Pending absences')
				break

			case 'all':
				this.getAllAusencias(fetchInfo, success, failure)
				this.vista_actual = t('employees', 'All my team')
				break

			case 'all-admin':
				this.getEmployeeAusencias(fetchInfo, success, failure)
				this.vista_actual = t('employees', 'All employees')
				break

			case 'employee':
				this.getEmployeeAusencias(fetchInfo, success, failure)
				this.vista_actual = t('employees', 'Selected employee')
				break

			case 'all-employees':
				this.GetAusenciasMyWorkers(fetchInfo, success, failure)
				this.vista_actual = t('employees', 'All my subordinates')
				break

			default:
				this.accordeon = this.accordeon.map(item => ({
					...item,
					abierto: false,
				}))

				this.employees = []
				this.getMyAusencias(fetchInfo, success, failure)
				this.vista_actual = t('employees', 'My absences')
			}
		},

		estiloEventoAusencia(item, fallbackUsername) {
			const roles = [item.is_manager, item.is_partner, item.can_access_human_resources]
				.filter(v => v !== undefined && v !== null)
				.map(Number)

			const isCancelled = roles.includes(3)
			const isRejected = roles.includes(2)
			const isApproved = roles.length > 0 && roles.every(v => v === 1)
			const esTemprana = Number(item.es_temprana) === 1

			if (isCancelled) {
				return { classNames: ['event-cancelled'] }
			}
			if (isRejected) {
				return { classNames: ['event-rejected'] }
			}
			if (isApproved) {
				return { classNames: ['event-approved'] }
			}
			if (esTemprana) {
				return { classNames: ['event-pending-anticipada'] }
			}
			return { classNames: ['event-pending'] }
		},

		getMyAusencias(fetchInfo, success, failure) {
			if (!this.currentEmployee?.id_user) {
				success([])
				return
			}

			axios.post(generateUrl('/apps/employees/GetAusenciasHistory'), {
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = this.responseArray(r, 'message')
					const events = data.map(item => {
						const startDate = new Date(item.date_from)
						const fechaHasta = new Date(item.date_until)
						fechaHasta.setDate(fechaHasta.getDate() + 1)
						const estilo = this.estiloEventoAusencia(item, this.currentEmployee.id_user)
						return {
							id: item.absence_history_id,
							title: item.type_name,
							start: startDate.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							classNames: estilo.classNames,
							employee_name: this.currentEmployee.id_user,
						}
					})
					success(events)
				})
				.catch(error => { console.error(error); failure(error) })
		},

		GetAusenciasMyWorkers(fetchInfo, success, failure) {
			axios.post(generateUrl('/apps/employees/GetAusenciasMyWorkers'), {
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = this.responseArray(r, 'message')
					const events = data.map(item => {
						const startDate = new Date(item.date_from)
						const fechaHasta = new Date(item.date_until)
						fechaHasta.setDate(fechaHasta.getDate() + 1)
						const estilo = this.estiloEventoAusencia(item, item.employee_name)
						return {
							id: item.absence_history_id,
							title: item.employee_name + ' - ' + item.type_name,
							start: startDate.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							classNames: estilo.classNames,
							employee_name: item.employee_name,
						}
					})
					success(events)
				})
				.catch(error => { console.error(error); failure(error) })
		},

		getAllAusencias(fetchInfo, success, failure) {
			axios.post(generateUrl('/apps/employees/GetAusenciasHistoryAll'), {
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = this.responseArray(r, 'message')
					const events = data.map(item => {
						const startDate = new Date(item.date_from)
						const fechaHasta = new Date(item.date_until)
						fechaHasta.setDate(fechaHasta.getDate() + 1)
						const estilo = this.estiloEventoAusencia(item, item.employee_name)
						return {
							id: item.absence_history_id,
							title: item.employee_name + ' - ' + item.type_name,
							start: startDate.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							classNames: estilo.classNames,
							employee_name: item.employee_name,
						}
					})
					success(events)
				})
				.catch(error => { console.error(error); failure(error) })
		},

		getEmployeeAusencias(fetchInfo, success, failure) {
			const usuarios = Array.isArray(this.selected_user)
				? this.selected_user
				: [this.selected_user]

			axios.post(generateUrl('/apps/employees/GetAusenciasEmployeeHistory'), {
				id_employee: usuarios,
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = this.responseArray(r, 'message')
					const events = data.map(item => {
						const startDate = new Date(item.date_from)
						const fechaHasta = new Date(item.date_until)
						fechaHasta.setDate(fechaHasta.getDate() + 1)
						const estilo = this.estiloEventoAusencia(item, item.employee_name)
						return {
							id: item.absence_history_id,
							title: `${item.employee_name} - ${item.type_name}`,
							start: startDate.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							classNames: estilo.classNames,
							employee_name: item.employee_name,
						}
					})
					success(events)
				})
				.catch(error => {
					console.error(error)
					this.selected_user = null
					failure(error)
				})
		},

		onDateClick(arg) {
			if (this.Settings.modulo_ausencias_readonly === 'true') {
				showInfo(t('employees', 'This module is in read-only mode'))
			}
		},

		OnClickEvent(info) {
			this.selectedEventId = info.event.id
			this.usuarioAusenciaSeleccionada = info.event.extendedProps.employee_name || null // ← NUEVO
			this.modalEvento = true
		},

		async onAbsenceCancelled() {
			this.closeModalEvento()

			await this.GetAusencias()
			await this.checkNotifications()

			/*
	 * Si estamos viendo pendientes y ya no queda ninguna,
	 * regresar a la vista personal.
	 */
			if (
				this.typePetition === 'pending'
		&& this.notifications_result.length === 0
			) {
				this.typePetition = null

				this.accordeon = this.accordeon.map((item, index) => ({
					...item,
					abierto: index === 0 ? false : item.abierto,
				}))
			}

			this.$nextTick(() => {
				this.$refs.fullCalendar
					?.getApi()
					?.refetchEvents()
			})
		},

		onAbsenceEdit(ausencia) {
			this.ausenciaEditar = ausencia
			this.closeModalEvento()
			this.$nextTick(() => {
				this.modalEditar = true
			})
		},

		async onAbsenceEditSaved() {
			this.closeModalEditar()

			await this.GetAusencias()
			await this.checkNotifications()

			if (
				this.typePetition === 'pending'
		&& this.notifications_result.length === 0
			) {
				this.typePetition = null

				this.accordeon = this.accordeon.map((item, index) => ({
					...item,
					abierto: index === 0 ? false : item.abierto,
				}))
			}

			this.$nextTick(() => {
				this.$refs.fullCalendar
					?.getApi()
					?.refetchEvents()
			})
		},

		color(username) {
			const { r, g, b } = usernameToColor(username)
			return `rgb(${r}, ${g}, ${b})`
		},

		isAdmin() {
			return 'admin' in this.groupuser || 'recursos_humanos' in this.groupuser
		},

		async updateList() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetEmpleadosList'))
				const employees = this.responseArray(response, 'Employees', 'Empleados')
				this.propsEmployees.options = employees.map(user => ({
					id_employees: user.id_employees,
					displayName: user.name || user.id_user,
					isNoUser: false,
					icon: '',
					user: user.id_user,
					preloadedUserStatus: {
						icon: '',
						status: user.Estatus === 'active' ? 'online' : 'offline',
						message: user.Estatus === 'active' ? t('employees', 'Active') : t('employees', 'Inactive'),
					},
				}))
			} catch (err) {
				this.reportLoadError(err)
			}
		},

		onDateRangeSelect(selection) {
			if (!this.isAdmin()) {
				if (this.Settings.modulo_ausencias_readonly === 'true') return
			}
			const nDate = new Date()
			this.range = selection
			if (!this.range || !this.range.start || !this.range.end) return

			const startDate = new Date(this.range.start)
			const endDate = new Date(this.range.end)
			endDate.setDate(endDate.getDate() - 1)

			if (!this.isAdmin()) {
				if (
					new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate())
					< new Date(nDate.getFullYear(), nDate.getMonth(), nDate.getDate())
				) {
					showError(t('employees', 'You cannot request absences on past dates'))
					return
				}
			}

			const dia = endDate.getDay()
			const diaInicio = startDate.getDay()
			if (dia === 0 || dia === 6 || diaInicio === 0 || diaInicio === 6) {
				showError(t('employees', 'You cannot start or end your absence on a weekend'))
				return
			}

			// No se permite iniciar ni terminar la ausencia en un día festivo.
			if (this.esFestivo(startDate) || this.esFestivo(endDate)) {
				showError(t('employees', 'You cannot start or end your absence on a holiday'))
				return
			}

			let date = new Date(startDate)
			let diasHabiles = 0
			while (date <= endDate) {
				const diaSemana = date.getDay()
				const esFestivoDia = this.esFestivo(date)
				if (diaSemana !== 0 && diaSemana !== 6 && !esFestivoDia) diasHabiles++
				date = new Date(date.getFullYear(), date.getMonth(), date.getDate() + 1)
			}

			this.diasSolicitados = diasHabiles
			this.date = { start: startDate, end: endDate }
			this.modal = true
		},

		async GetAllEquipo() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetMyEquipo'))
				this.peopleEquipo = this.responseArray(response)
			} catch (err) {
				this.reportLoadError(err)
			}
		},

		async getTeams() {
			if (!this.currentEmployee?.id_team) {
				this.Equipo = {}
				return
			}
			try {
				const response = await axios.post(generateUrl('/apps/employees/GetEquipoJefe'), {
					id: this.currentEmployee.id_team,
				})
				this.Equipo = this.responseArray(response)[0] || {}
			} catch (error) {
				this.reportLoadError(error)
			}
		},

		onEmployeesChange(news) {
			if (news !== null && news.length > 0) {
				this.selected_user = news
				this.typePetition = 'employee'
			} else {
				this.selected_user = null
				this.typePetition = null
			}
			this.$nextTick(() => {
				this.$refs.fullCalendar?.getApi()?.refetchEvents()
			})
		},
		ajustarAlturaCalendario() {
			this.$nextTick(() => {
				const calendarContainer = this.$refs.calendarViewport
				const sidebar = this.$refs.sidebar
				const calendar = this.$refs.fullCalendar?.getApi()

				if (!calendarContainer || !calendar) return

				const top = calendarContainer.getBoundingClientRect().top
				const margenInferior = 30

				const alturaDisponible = Math.max(
					300,
					Math.floor(window.innerHeight - top - margenInferior),
				)

				calendar.setOption('height', alturaDisponible)
				calendar.updateSize()

				if (sidebar) {
					sidebar.style.height = `${alturaDisponible}px`
					sidebar.style.maxHeight = `${alturaDisponible}px`
				}
			})
		},
		mostrarTodosAdministrativo() {
			const todosLosEmpleados = this.propsEmployees.options ?? []

			if (todosLosEmpleados.length === 0) {
				showInfo(t('employees', 'No employees were found'))
				return
			}

			/*
	 * No llenamos `employees` para evitar que NcSelect muestre
	 * decenas de etiquetas seleccionadas.
	 */
			this.employees = []
			this.selected_user = [...todosLosEmpleados]
			this.typePetition = 'all-admin'

			this.$nextTick(() => {
				this.$refs.fullCalendar?.getApi()?.refetchEvents()
			})
		},
		notificationEmployeeName(item) {
			return item.displayname
		|| item.employee_name
		|| item.name
		|| item.id_user
		|| t('employees', 'Unknown employee')
		},

		notificationAbsenceType(item) {
			return item.type_name
		|| item.absence_types
		|| item.nombre_tipo
		|| item.Tipo
		|| t('employees', 'Absence request')
		},

		notificationRequestedDays(item) {
			const days = item.days_requested
		?? item.total_dias
		?? item.days
		?? null

			if (days === null || days === '') {
				return null
			}

			const parsedDays = Number(days)

			return Number.isFinite(parsedDays)
				? parsedDays
				: null
		},
		togglePendingNotifications() {
			const abrirPendientes = !this.accordeon[0].abierto

			this.toggle(0)

			if (abrirPendientes) {
				/*
		 * Limpiamos cualquier filtro anterior para que el calendar
		 * muestre exclusivamente las solicitudes pendientes.
		 */
				this.employees = []
				this.selected_user = null
				this.typePetition = 'pending'
			} else {
				/*
		 * Al cerrar Pendientes regresamos a la vista personal.
		 */
				this.employees = []
				this.selected_user = null
				this.typePetition = null
			}

			this.$nextTick(() => {
				this.$refs.fullCalendar?.getApi()?.refetchEvents()
			})
		},
		getPendingAusencias(fetchInfo, success, failure) {
			try {
				const events = (this.notifications_result || [])
					.map(item => {
						const fechaInicialRaw = item.date_from
					?? item.date_start
					?? item.desde
					?? null

						const fechaFinalRaw = item.date_until
					?? item.date_end
					?? item.hasta
					?? fechaInicialRaw

						if (!fechaInicialRaw) {
							return null
						}

						const fechaInicioTexto = String(fechaInicialRaw).slice(0, 10)
						const fechaFinalTexto = String(fechaFinalRaw).slice(0, 10)

						const startDate = new Date(
							`${fechaInicioTexto}T00:00:00`,
						)

						const fechaHasta = new Date(
							`${fechaFinalTexto}T00:00:00`,
						)

						if (
							Number.isNaN(startDate.getTime())
					|| Number.isNaN(fechaHasta.getTime())
						) {
							return null
						}
						fechaHasta.setDate(fechaHasta.getDate() + 1)

						const name = this.notificationEmployeeName(item)
						const type = this.notificationAbsenceType(item)

						return {
							id: item.absence_history_id,
							title: `${name} - ${type}`,
							start: startDate.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							classNames: Number(item.es_temprana) === 1 ? ['event-pending-anticipada'] : ['event-pending'],

							employee_name: item.id_user || name,

							extendedProps: {
								id_user: item.id_user || null,
								days_requested:
							this.notificationRequestedDays(item),
								absence_types: type,
							},
						}
					})
					.filter(Boolean)

				success(events)
			} catch (error) {
				console.error('Error mostrando Absence pendientes:', error)
				failure(error)
			}
		},
	},
}
</script>
<style>
/* ========================================
 * COLORES FIJOS DE EVENTOS
 * ======================================== */

.fc-event.event-pending {
	background: repeating-linear-gradient(
		135deg,
		#73a0cf 0px,
		#739bc7 8px,
		#8fb4d9 8px,
		#8fb4d9 16px
	) !important;
	border-color: #86b7ef !important;
	color: #ffffff !important;
}

.fc-event.event-pending-anticipada {
	background: repeating-linear-gradient(
		135deg,
		#e07b28 0px,
		#d97324 8px,
		#f0994f 8px,
		#f0994f 16px
	) !important;
	border-color: #f2a05e !important;
	color: #ffffff !important;
}

.fc-event.event-approved {
	background: #68b868 !important;
	border-color: #1e8a46 !important;
	color: #ffffff !important;
}

.fc-event.event-rejected {
	background: #f04747 !important;
	border-color: #912222 !important;
	color: #ffffff !important;
}
.event-rejected .fc-event-title {
	text-decoration: line-through;
	opacity: 0.85;
}

.fc-event.event-cancelled {
	background: #9e9e9e !important;
	border-color: #7d7d7d !important;
	color: #ffffff !important;
}
.event-cancelled .fc-event-title {
	text-decoration: line-through;
	opacity: 0.8;
}

/* ========================================
 * DÍAS FESTIVOS / INHÁBILES
 * ======================================== */

.fc-day-festivo {
	position: relative;

	/*
	 * El color de fondo real se aplica en línea (inline style con
	 * !important) desde onDayCellDidMount, para ganarle a las reglas
	 * !important que FullCalendar aplica a "hoy" y a la selección.
	 * Este valor queda solo como respaldo visual.
	 */
	background-color: #e3f5e6;
	transition: background-color 0.15s ease;
}

.fc-day-festivo .fc-daygrid-day-number {
	color: #2f6b45;
	font-weight: 700;
}

.fc-day-festivo .fc-daygrid-day-frame {
	position: relative;
}

.fc-festivo-label {
	position: absolute;
	right: 4px;
	bottom: -50px;
	left: 4px;

	overflow: hidden;

	color: #2f6b45;
	font-size: 0.6rem;
	font-style: normal;
	font-weight: 600;
	line-height: 1.15;
	text-align: center;
	text-overflow: ellipsis;
	white-space: nowrap;
	letter-spacing: 0.01em;

	background: rgba(255, 255, 255, 0.55);
	border-radius: 4px;
	padding: 2px 4px 3px;

	pointer-events: none;
}
</style>

<style scoped>
/* ========================================
 * ESTRUCTURA GENERAL
 * ======================================== */

.layout {
	display: flex;
	align-items: stretch;
	width: 100%;
	gap: 16px;
}

.grow1 {
	display: flex;
	flex: 3;
	min-width: 260px;
	min-height: 0;
}

.grow2 {
	flex: 7;
	min-width: 0;
}

.grow3 {
	flex: 3;
	min-width: 0;
}

.grow4 {
	flex: 3;
	min-width: 0;
}

.section-calendar {
	padding-top: 25px;
	padding-inline: 30px;
}

.sectionPicker {
	width: 100%;
	min-width: 0;
}

.my-calendar {
	width: 100%;
	min-width: 0;

	--color-background-dark: transparent !important;
}

/* ========================================
 * SIDEBAR
 * ======================================== */

.cards {
	--sidebar-primary: #2389d7;
	--sidebar-primary-dark: #1468a8;
	--sidebar-primary-soft: #e7f3fb;
	--sidebar-soft: #edf6fc;
	--sidebar-soft-hover: #dceefa;
	--sidebar-border: #d4e2ec;
	--sidebar-border-strong: #b9d5e6;
	--sidebar-text: #17354d;
	--sidebar-muted: #66798a;
	--sidebar-warning: #b45309;
	--sidebar-warning-soft: #fff7e8;

	display: flex;
	flex: 1;
	flex-direction: column;

	width: 100%;
	height: 100%;
	max-height: 100%;
	min-width: 0;
	min-height: 0;

	overflow: hidden;

	color: var(--sidebar-text);
	background: #f8fbfd;
	border: 1px solid var(--sidebar-border);
	border-radius: 16px;

	box-shadow:
		0 8px 24px rgba(15, 47, 74, 0.08),
		0 2px 5px rgba(15, 47, 74, 0.05);
}

/* ========================================
 * ENCABEZADO DE VACACIONES
 * ======================================== */

.headers {
	position: relative;

	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;

	margin: 12px 12px 10px;
	padding: 18px 14px;

	text-align: center;

	background:
		linear-gradient(
			145deg,
			var(--sidebar-primary),
			var(--sidebar-primary-dark)
		);

	border-radius: 14px;

	box-shadow:
		0 10px 22px rgba(31, 127, 195, 0.25),
		0 3px 7px rgba(31, 127, 195, 0.14);
}

.header-content {
	display: flex;
	flex-direction: column;
	align-items: center;

	width: 100%;
	gap: 12px;
}

.h2-white {
	margin: 0;

	color: white;
	font-size: 1.15rem;
	font-weight: 700;
	letter-spacing: 0.02em;
}

/* ========================================
 * TARJETAS DE VACACIONES
 * ======================================== */

.vacations {
	width: 100%;
}

.vacations-grid {
	display: flex;
	flex-direction: row;
	align-items: stretch;

	width: 100%;
	overflow: hidden;

	background: white;
	border: 1px solid rgba(15, 23, 42, 0.12);
	border-radius: 10px;

	box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}

.vacation-card {
	display: flex;
	flex: 1 1 50%;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	box-sizing: border-box;
	width: 50%;
	min-width: 0;
	min-height: 105px;
	padding: 10px 6px;

	text-align: center;
	background: white;
}

.vacation-card + .vacation-card {
	border-top: 0;
	border-left: 1px solid rgba(15, 23, 42, 0.12);
}

.vacations-grid--single .vacation-card {
	flex-basis: 100%;
	width: 100%;
}

.vacation-card__title {
	max-width: 100%;
	margin-bottom: 3px;
	overflow: hidden;

	color: #334155;
	font-size: 0.62rem;
	font-weight: 700;
	text-overflow: ellipsis;
	text-transform: uppercase;
	letter-spacing: 0.03em;
	white-space: nowrap;
}

.vacation-card__value {
	color: #8b477f;
	font-size: 1.45rem;
	font-weight: 800;
	line-height: 1;
}

.vacation-card__subtitle {
	max-width: 100%;
	margin-top: 4px;
	overflow: hidden;

	color: #64748b;
	font-size: 0.54rem;
	font-weight: 600;
	text-overflow: ellipsis;
	text-transform: uppercase;
	letter-spacing: 0.03em;
	white-space: nowrap;
}

.vacation-card--accumulated {
	background: #fffaf0;
}

.vacation-card__warning {
	display: flex;
	align-items: flex-start;
	justify-content: center;

	width: 100%;
	margin-top: 7px;
	padding-top: 6px;
	gap: 3px;

	color: #92400e;
	font-size: 0.55rem;
	line-height: 1.2;

	border-top: 1px solid rgba(146, 64, 14, 0.14);
}

.vacation-card__warning svg {
	flex-shrink: 0;
}

/* ========================================
 * CONTENIDO DESPLAZABLE DEL SIDEBAR
 * ======================================== */

.infos {
	flex: 1 1 auto;
	min-height: 0;
	padding: 4px 12px 12px;

	overflow-x: hidden;
	overflow-y: auto;

	text-align: center;
	overscroll-behavior: contain;

	scrollbar-width: thin;
	scrollbar-color: #a9c7da transparent;
}

.infos::-webkit-scrollbar {
	width: 6px;
}

.infos::-webkit-scrollbar-track {
	background: transparent;
}

.infos::-webkit-scrollbar-thumb {
	background: #a9c7da;
	border-radius: 999px;
}

.infos::-webkit-scrollbar-thumb:hover {
	background: #82aec9;
}

/* ========================================
 * BOTONES PRINCIPALES
 * ======================================== */

.sidebar-button {
	width: 100% !important;
	min-height: 40px !important;
	margin-top: 8px !important;
	padding: 7px 12px !important;

	color: var(--sidebar-text) !important;
	font-size: 0.82rem !important;
	font-weight: 700 !important;

	background: var(--sidebar-soft) !important;
	border: 1px solid transparent !important;
	border-radius: 10px !important;

	box-shadow: none !important;

	transition:
		background-color 0.18s ease,
		border-color 0.18s ease,
		transform 0.18s ease,
		box-shadow 0.18s ease;
}

.btn-top {
	margin-top: 8px;
}

/* ========================================
 * ACORDEONES
 * ======================================== */

.acordeon-item {
	flex: 0 0 auto;

	box-sizing: border-box;
	width: 100%;
	margin-top: 8px;
	margin-bottom: 0;

	overflow: hidden;

	background: white;
	border: 1px solid var(--sidebar-border);
	border-radius: 10px;

	transition:
		border-color 0.18s ease,
		box-shadow 0.18s ease;
}

:is(.acordeon-title, .acordeon-notification) {
	display: flex;
	align-items: center;
	justify-content: space-between;

	box-sizing: border-box;
	width: 100%;
	min-height: 40px;
	padding: 8px 10px;

	color: var(--sidebar-text);
	font-family: inherit;
	font-size: 0.82rem;
	font-weight: 700;
	text-align: left;

	background: var(--sidebar-soft);
	border: none;

	cursor: pointer;

	transition:
		background-color 0.18s ease,
		color 0.18s ease;
}

.acordeon-notification {
	color: #8a3d08;
	background: var(--sidebar-warning-soft);
}

.acordeon-contenido {
	box-sizing: border-box;

	max-height: 0;
	padding: 0 8px;

	overflow: hidden;
	opacity: 0;

	background: white;

	transition:
		max-height 0.28s ease,
		padding 0.28s ease,
		opacity 0.2s ease;
}

.acordeon-contenido.abierto {
	max-height: min(44dvh, 390px);
	padding: 8px;

	overflow: hidden;
	opacity: 1;

	border-top: 1px solid #e5edf3;
}

/* ========================================
 * INDICADOR + / −
 * ======================================== */

.arrow {
	display: inline-flex;
	flex: 0 0 22px;
	align-items: center;
	justify-content: center;

	width: 22px;
	height: 22px;
	margin-left: 8px;

	color: var(--sidebar-primary-dark);
	font-size: 1rem;
	font-weight: 700;
	line-height: 1;

	background: rgba(31, 127, 195, 0.11);
	border-radius: 50%;
}

/* ========================================
 * NOTIFICACIONES
 * ======================================== */

.noti-wrapper {
	position: relative;

	display: flex;
	flex: 0 0 24px;
	align-items: center;
	justify-content: center;

	width: 24px;
	height: 24px;

	color: var(--sidebar-warning);
}

.noti-badge {
	position: absolute;
	top: -9px;
	right: -11px;

	transform: scale(0.82);
	transform-origin: center;
}

.noti-text {
	flex: 1;
	min-width: 0;
	margin-left: 9px;
	overflow: hidden;

	text-align: left;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* ========================================
 * MENÚ INTERNO DE ACORDEONES
 * ======================================== */

.accordion-menu {
	display: flex;
	flex-direction: column;

	box-sizing: border-box;
	width: 100%;
	min-width: 0;
	gap: 6px;
}

.accordion-menu--select {
	padding: 3px 0;
}

.accordion-user-list {
	box-sizing: border-box;
	width: 100%;
	max-height: 245px;
	margin: 0;
	padding: 0 3px 0 0;

	overflow-x: hidden;
	overflow-y: auto;

	list-style: none;
	overscroll-behavior: contain;

	scrollbar-width: thin;
	scrollbar-color: #b8cede transparent;
}

.accordion-user-list::-webkit-scrollbar {
	width: 5px;
}

.accordion-user-list::-webkit-scrollbar-track {
	background: transparent;
}

.accordion-user-list::-webkit-scrollbar-thumb {
	background: #b8cede;
	border-radius: 999px;
}

.accordion-user-list > li {
	margin: 0;
	padding: 0;
}

.accordion-user-list > li + li {
	margin-top: 4px;
}

/* ========================================
 * OPCIONES DE EQUIPO Y USUARIOS
 * ======================================== */

.accordion-option {
	display: flex;
	align-items: center;

	box-sizing: border-box;
	width: 100%;
	min-width: 0;
	min-height: 48px;
	padding: 6px 9px;
	gap: 9px;

	color: var(--sidebar-text);
	font-family: inherit;
	text-align: left;

	background: transparent;
	border: 1px solid transparent;
	border-radius: 9px;

	cursor: pointer;

	transition:
		background-color 0.16s ease,
		border-color 0.16s ease,
		transform 0.16s ease;
}

.accordion-option--group {
	min-height: 55px;

	background: #f3f8fc;
	border-color: #dde9f1;
}

.accordion-option__text {
	display: flex;
	flex: 1;
	flex-direction: column;

	min-width: 0;
	gap: 2px;
}

.accordion-option__text strong {
	max-width: 100%;
	overflow: hidden;

	color: var(--sidebar-text);
	font-size: 0.78rem;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.accordion-option__text small {
	max-width: 100%;
	overflow: hidden;

	color: var(--sidebar-muted);
	font-size: 0.67rem;
	font-weight: 500;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.accordion-option__icon {
	flex: 0 0 auto;
	margin-left: auto;

	color: var(--sidebar-primary);
}

/* ========================================
 * COMPATIBILIDAD CON LA ESTRUCTURA ANTERIOR
 * ======================================== */

.rst {
	width: 100%;
	min-width: 0;
}

.rst ul {
	width: 100%;
	margin: 0;
	padding: 0;

	list-style: none;
}

.rst-title {
	margin-bottom: 6px;
	padding: 7px 8px;

	background: #f3f8fc;
	border: 1px solid #dde9f1;
	border-radius: 9px;
}

.title_flex {
	display: flex;
	align-items: center;
	width: 100%;
	gap: 8px;
}

.subtitle_flex {
	display: flex;
	align-items: center;
	margin-left: 0;
}

.btn-top-subtitle {
	min-width: 0;
	margin-top: 0;

	overflow: hidden;

	color: var(--sidebar-text);
	font-size: 0.78rem;
	font-weight: 700;
	text-align: left;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.flex-to-right {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;

	margin-right: 0;
	margin-left: auto;

	color: var(--sidebar-primary);
	cursor: pointer;

	transition: transform 0.18s ease;
}

.pointer {
	cursor: pointer;
}

/* ========================================
 * SELECTOR ADMINISTRATIVO
 * ======================================== */

::v-deep .accordion-menu--select .v-select {
	width: 100%;
}

::v-deep .accordion-menu--select .vs__dropdown-toggle {
	min-height: 40px;

	background: #f8fbfd;
	border-color: var(--sidebar-border);
	border-radius: 9px;
}

::v-deep .accordion-menu--select .vs__selected-options {
	min-width: 0;
}

::v-deep .accordion-menu--select .vs__search {
	color: var(--sidebar-text);
	font-size: 0.78rem;
}

/* ========================================
 * PIE DEL SIDEBAR
 * ======================================== */

.footers {
	display: flex;
	flex: 0 0 auto;
	align-items: center;

	min-height: 43px;
	padding: 9px 78px 9px 12px;

	color: #47667d;
	font-size: 0.76rem;
	font-weight: 500;
	text-align: left;

	background: #edf6fc;
	border-top: 1px solid var(--sidebar-border);
}

.footers p {
	width: 100%;
	margin: 0;
	overflow: hidden;

	text-overflow: ellipsis;
	white-space: nowrap;
}

/* ========================================
 * TABLAS Y MODALES
 * ======================================== */

.table_component {
	width: 100%;
	overflow: auto;
}

.table_component table {
	width: 100%;

	border: 1px solid #dededf;
	border-collapse: collapse;

	table-layout: fixed;
	text-align: left;
}

.table_component th,
.table_component td {
	padding: 5px;
	border: 1px solid #dededf;
}

.table_component th {
	color: black;
	background-color: #eceff1;
}

.table_component td {
	color: black;
	background-color: white;
}

.caption-title {
	font-weight: 700;
}

.modal__content {
	margin: 50px;
}

/* ========================================
 * BOTÓN FLOTANTE
 * ======================================== */

.floating-help-button {
	position: fixed;
	right: 24px;
	bottom: 24px;
	z-index: 10000;

	display: flex;
	align-items: center;
	justify-content: center;

	width: 64px;
	height: 64px;
	padding: 4px;

	background-color: white;
	border: 1px solid #cbd5e0;
	border-radius: 50%;

	box-shadow:
		0 8px 20px rgba(0, 0, 0, 0.22),
		0 2px 6px rgba(0, 0, 0, 0.15);

	transition:
		transform 0.2s ease,
		box-shadow 0.2s ease;
}

/* ========================================
 * ANIMACIONES
 * ======================================== */

@keyframes shake {
	0% {
		transform: rotate(0deg);
	}

	15% {
		transform: rotate(-15deg);
	}

	30% {
		transform: rotate(15deg);
	}

	45% {
		transform: rotate(-10deg);
	}

	60% {
		transform: rotate(10deg);
	}

	75% {
		transform: rotate(-5deg);
	}

	90% {
		transform: rotate(5deg);
	}

	100% {
		transform: rotate(0deg);
	}
}

.bell-shake {
	animation: shake 0.8s ease;
}

/* ========================================
 * RESPONSIVE
 * ======================================== */

@media screen and (max-width: 1100px) {
	.layout {
		gap: 10px;
	}

	.grow1 {
		min-width: 235px;
	}

	.section-calendar {
		padding-top: 18px;
		padding-inline: 18px;
	}

	.headers {
		margin: 10px;
		padding: 15px 10px;
	}

	.infos {
		padding-inline: 10px;
	}

	:is(.sidebar-button, .acordeon-title, .acordeon-notification) {
		font-size: 0.76rem !important;
	}

	.vacation-card {
		min-height: 95px;
		padding: 8px 5px;
	}

	.vacation-card__title {
		font-size: 0.56rem;
	}

	.vacation-card__value {
		font-size: 1.25rem;
	}

	.vacation-card__subtitle {
		font-size: 0.48rem;
	}

	.vacation-card__warning {
		font-size: 0.5rem;
	}

	.accordion-option {
		padding: 6px 7px;
	}

	.accordion-option__text strong {
		font-size: 0.73rem;
	}

	.accordion-option__text small {
		font-size: 0.63rem;
	}
}

@media screen and (max-width: 700px) {
	.floating-help-button {
		right: 12px;
		bottom: 12px;

		width: 56px;
		height: 56px;
	}

	.modal__content {
		margin: 20px;
	}
}

/* ========================================
 * ESTADOS INTERACTIVOS
 * ======================================== */

.sidebar-button:hover {
	background: var(--sidebar-soft-hover) !important;
	border-color: #b9d8eb !important;

	box-shadow: 0 4px 10px rgba(31, 127, 195, 0.12) !important;

	transform: translateY(-1px);
}

.sidebar-button:active {
	transform: translateY(0);
}

.acordeon-item:hover {
	border-color: #b6d3e5;
	box-shadow: 0 4px 12px rgba(15, 47, 74, 0.07);
}

.acordeon-title:hover {
	color: var(--sidebar-primary-dark);
	background: var(--sidebar-soft-hover);
}

.acordeon-notification:hover {
	background: #ffedcc;
}

.accordion-option:hover {
	background: var(--sidebar-soft);
	border-color: #d1e4ef;
}

.accordion-option--group:hover {
	background: #e4f1f9;
	border-color: #bad8e9;
}

.accordion-option:active {
	transform: scale(0.99);
}

.flex-to-right:hover {
	transform: scale(1.12);
}

.floating-help-button:hover {
	transform: scale(1.08);

	box-shadow:
		0 10px 25px rgba(0, 0, 0, 0.28),
		0 3px 8px rgba(0, 0, 0, 0.18);
}

.accordion-title {
	display: flex;
	flex: 1;
	flex-direction: column;

	min-width: 0;
	gap: 2px;
}

.accordion-title__label {
	overflow: hidden;

	color: inherit;
	font-size: 0.8rem;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.accordion-title__description {
	overflow: hidden;

	color: var(--sidebar-muted);
	font-size: 0.63rem;
	font-weight: 500;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.acordeon-item--admin {
	border-color: rgba(35, 137, 215, 0.25);
}

.acordeon-item--admin > .acordeon-title {
	background: var(--sidebar-primary-soft);
}

.accordion-field {
	display: flex;
	flex-direction: column;

	width: 100%;
	gap: 5px;
}

.accordion-field__label {
	color: var(--sidebar-muted);
	font-size: 0.68rem;
	font-weight: 600;
	text-align: left;
}

.accordion-action-button {
	width: 100% !important;
	min-height: 38px !important;

	color: white !important;
	font-size: 0.76rem !important;
	font-weight: 700 !important;

	background: var(--sidebar-primary) !important;
	border: none !important;
	border-radius: 9px !important;
}

.sidebar-reset {
	margin-top: 10px;
	padding-top: 2px;
}

.accordion-action-button:hover {
	background: var(--sidebar-primary-dark) !important;
}
.accordion-action-button--all {
	color: var(--sidebar-primary-dark) !important;

	background: var(--sidebar-primary-soft) !important;
	border: 1px solid var(--sidebar-border-strong) !important;
}

.accordion-action-button--all:hover {
	color: white !important;

	background: var(--sidebar-primary) !important;
	border-color: var(--sidebar-primary) !important;
}
/* ========================================
 * NOTIFICACIONES PENDIENTES
 * ======================================== */

.pending-list {
	width: 100%;
	max-height: 280px;
	margin: 0;
	padding: 0 3px 0 0;

	overflow-x: hidden;
	overflow-y: auto;

	list-style: none;
	overscroll-behavior: contain;

	scrollbar-width: thin;
	scrollbar-color: #dca75e transparent;
}

.pending-list::-webkit-scrollbar {
	width: 5px;
}

.pending-list::-webkit-scrollbar-track {
	background: transparent;
}

.pending-list::-webkit-scrollbar-thumb {
	background: #dca75e;
	border-radius: 999px;
}

.pending-list__item {
	margin: 0;
	padding: 0;
}

.pending-list__item + .pending-list__item {
	margin-top: 5px;
}

.pending-card {
	display: flex;
	align-items: center;

	box-sizing: border-box;
	width: 100%;
	min-width: 0;
	min-height: 56px;
	padding: 7px 8px;
	gap: 9px;

	color: var(--sidebar-text);
	font-family: inherit;
	text-align: left;

	background: #fffaf1;
	border: 1px solid #f0ddbd;
	border-radius: 10px;

	cursor: pointer;

	transition:
		background-color 0.18s ease,
		border-color 0.18s ease,
		box-shadow 0.18s ease,
		transform 0.18s ease;
}

.pending-card__avatar {
	flex: 0 0 auto;
}

.pending-card__content {
	display: flex;
	flex: 1;
	flex-direction: column;

	min-width: 0;
	gap: 3px;
}

.pending-card__name {
	width: 100%;
	overflow: hidden;

	color: #243746;
	font-size: 0.76rem;
	font-weight: 700;
	line-height: 1.2;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.pending-card__type {
	width: 100%;
	overflow: hidden;

	color: #9a5a16;
	font-size: 0.66rem;
	font-weight: 600;
	line-height: 1.2;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.pending-card__days {
	display: inline-flex;
	flex: 0 0 34px;
	align-items: center;
	justify-content: center;

	width: 34px;
	height: 34px;

	color: white;
	font-size: 0.78rem;
	font-weight: 800;
	line-height: 1;

	background: #b45309;
	border: 3px solid #ffedd5;
	border-radius: 50%;

	box-shadow: 0 2px 6px rgba(180, 83, 9, 0.22);
}

.pending-card:hover {
	background: #fff3dc;
	border-color: #dfb877;

	box-shadow: 0 4px 10px rgba(146, 64, 14, 0.1);

	transform: translateY(-1px);
}

.pending-card:hover .pending-card__days {
	background: #92400e;
}

.pending-card:active {
	transform: translateY(0);
}
</style>
