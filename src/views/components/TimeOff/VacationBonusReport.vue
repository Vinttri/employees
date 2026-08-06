<template>
	<div class="informe-prima">
		<header class="informe-header">
			<div class="informe-identidad">
				<NcAvatar
					v-if="employeeUid"
					disable-menu
					class="informe-avatar"
					:size="56"
					:user="employeeUid"
					:display-name="nombreEmpleado" />

				<div v-else class="informe-avatar-fallback">
					{{ empleadoIniciales }}
				</div>

				<div class="informe-heading">
					<span class="informe-eyebrow">
						{{ t('employees', 'Vacation bonus ledger') }}
					</span>

					<h2 class="informe-title">
						{{ t('employees', 'Vacation Bonus Report') }}
					</h2>

					<p v-if="nombreEmpleado" class="informe-sub">
						{{ nombreEmpleado }}
					</p>
				</div>
			</div>

			<div
				v-if="!loading && periodos.length > 0"
				class="informe-resumen">
				<div class="resumen-item">
					<span class="resumen-item__label">
						{{ t('employees', 'Total periods') }}
					</span>
					<strong class="resumen-item__value">
						{{ resumenPeriodos.total }}
					</strong>
				</div>

				<div class="resumen-item resumen-item--paid">
					<span class="resumen-item__label">
						{{ t('employees', 'Paid') }}
					</span>
					<strong class="resumen-item__value">
						{{ resumenPeriodos.pagados }}
					</strong>
				</div>

				<div class="resumen-item resumen-item--pending">
					<span class="resumen-item__label">
						{{ t('employees', 'Pending') }}
					</span>
					<strong class="resumen-item__value">
						{{ resumenPeriodos.pendientes }}
					</strong>
				</div>

				<div class="resumen-item resumen-item--days">
					<span class="resumen-item__label">
						{{ t('employees', 'Paid days') }}
					</span>
					<strong class="resumen-item__value">
						{{ resumenPeriodos.diasPagados }}
					</strong>
				</div>
			</div>
		</header>

		<div v-if="loading" class="informe-state">
			<NcLoadingIcon :size="36" />
			<strong>{{ t('employees', 'Loading anniversaries...') }}</strong>
			<span>{{ t('employees', 'Please wait while the payment history is loaded.') }}</span>
		</div>

		<div v-else-if="periodos.length === 0" class="informe-state">
			<span class="informe-state__icon">🏖️</span>
			<strong>{{ t('employees', 'No anniversary periods found') }}</strong>
			<span>{{ t('employees', 'No anniversary periods found for this employee.') }}</span>
		</div>

		<div v-else class="ledger-shell">
			<table class="ledger">
				<thead>
					<tr>
						<th scope="col" class="col-Anniversary">
							{{ t('employees', 'Anniversary') }}
						</th>
						<th scope="col" class="col-date">
							{{ t('employees', 'Requested on') }}
						</th>
						<th scope="col" class="col-action">
							{{ t('employees', 'Action') }}
						</th>
						<th scope="col" class="col-status">
							{{ t('employees', 'Status') }}
						</th>
					</tr>
				</thead>

				<tbody>
					<tr
						v-for="periodo in periodos"
						:key="periodo.number_anniversary"
						class="ledger-row"
						:class="{ 'ledger-row--paid': periodo.paid }">
						<td
							class="cell-Anniversary"
							:data-label="t('employees', 'Anniversary')">
							<span
								class="seal"
								:class="{ 'seal--paid': periodo.paid }">

								<CheckDecagram
									v-if="periodo.paid"
									:size="18" />

								<span v-else>
									{{ periodo.number_anniversary }}
								</span>
							</span>

							<div class="periodo-info">
								<strong>
									{{ t('employees', 'Anniversary {n}', {
										n: periodo.number_anniversary,
									}) }}
								</strong>

								<small>
									{{ formatFecha(periodo.period_start) }}
									<span aria-hidden="true">→</span>
									{{ formatFecha(periodo.period_end) }}
								</small>

								<span
									v-if="periodo.es_actual"
									class="periodo-actual">

									{{ t('employees', 'Current period') }}
								</span>
							</div>
						</td>

						<td
							class="cell-date"
							:data-label="t('employees', 'Requested on')">
							<span
								v-if="periodo.date_request"
								class="date-principal">

								{{ formatFecha(periodo.date_request) }}
							</span>

							<span v-else class="muted">
								{{ t('employees', 'No request') }}
							</span>
						</td>

						<td
							class="cell-action"
							:data-label="t('employees', 'Action')">
							<button
								type="button"
								class="payment-button"
								:class="{ 'payment-button--edit': periodo.paid }"
								@click="abrirConfirmacion(periodo)">
								{{ periodo.paid
									? t('employees', 'Edit payment')
									: t('employees', 'Mark payment') }}
							</button>
						</td>

						<td
							class="cell-status"
							:data-label="t('employees', 'Status')">
							<div class="status-stack">
								<span
									v-if="periodo.paid"
									class="badge badge--paid">

									<CheckDecagram :size="14" />
									{{ t('employees', 'Paid') }}
								</span>

								<span v-else class="badge badge--pendiente">
									<ClockOutline :size="14" />
									{{ t('employees', 'Pending') }}
								</span>

								<small
									v-if="periodo.paid"
									class="status-detalle">

									{{ t('employees', '{date} · {days} days', {
										date: formatFecha(periodo.date_payment),
										days: periodo.days_paid,
									}) }}
								</small>

								<small
									v-else-if="periodo.date_request"
									class="status-detalle">

									{{ t('employees', 'Requested and awaiting payment') }}
								</small>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<NcModal
			v-if="confirmando"
			size="normal"
			:name="periodoSeleccionado && periodoSeleccionado.paid
				? t('employees', 'Edit payment')
				: t('employees', 'Confirm payment')"
			@close="cancelarConfirmacion">
			<div class="confirm-pago">
				<div class="confirm-periodo">
					<span
						class="seal confirm-periodo__seal"
						:class="{ 'seal--paid': periodoSeleccionado.paid }">

						<CheckDecagram
							v-if="periodoSeleccionado.paid"
							:size="18" />

						<span v-else>
							{{ periodoSeleccionado.number_anniversary }}
						</span>
					</span>

					<div class="confirm-periodo__info">
						<strong>
							{{ t('employees', 'Anniversary {n}', {
								n: periodoSeleccionado.number_anniversary,
							}) }}
						</strong>

						<small>
							{{ formatFecha(periodoSeleccionado.period_start) }}
							<span aria-hidden="true">→</span>
							{{ formatFecha(periodoSeleccionado.period_end) }}
						</small>
					</div>
				</div>

				<p class="confirm-lead">
					<template v-if="periodoSeleccionado.paid">
						{{ t('employees', 'You are editing the payment record for {n}.', {
							n: t('employees', 'Anniversary {n}', {
								n: periodoSeleccionado.number_anniversary,
							}),
						}) }}
					</template>

					<template v-else>
						{{ t('employees', 'You are about to mark the vacation bonus for {n} as paid.', {
							n: t('employees', 'Anniversary {n}', {
								n: periodoSeleccionado.number_anniversary,
							}),
						}) }}
					</template>
				</p>

				<div class="confirm-grid">
					<div class="confirm-field">
						<label for="prima-payment-date">
							{{ t('employees', 'Payment date') }}
						</label>

						<input
							id="prima-payment-date"
							v-model="fechaConfirmar"
							type="date"
							class="confirm-input">
					</div>

					<div class="confirm-field">
						<label for="prima-payment-days">
							{{ t('employees', 'Days to mark as paid') }}
						</label>

						<input
							id="prima-payment-days"
							v-model.number="diasConfirmar"
							type="number"
							min="0"
							step="0.5"
							class="confirm-input">

						<small class="confirm-hint">
							{{ t('employees', 'Entitled days for this period: {days}', {
								days: periodoSeleccionado.days_entitlement,
							}) }}
						</small>
					</div>
				</div>

				<div class="confirm-note">
					<ClockOutline :size="18" />

					<span>
						{{ t('employees', 'The payment date and paid days can be edited later.') }}
					</span>
				</div>

				<div class="confirm-actions">
					<NcButton
						type="tertiary"
						@click="cancelarConfirmacion">
						{{ t('employees', 'Cancel') }}
					</NcButton>

					<NcButton
						type="primary"
						@click="confirmarPago">
						{{ periodoSeleccionado.paid
							? t('employees', 'Save changes')
							: t('employees', 'Confirm payment') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import CheckDecagram from 'vue-material-design-icons/CheckDecagram.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'

import { NcAvatar, NcButton, NcModal, NcLoadingIcon } from '@nextcloud/vue'

export default {
	name: 'VacationBonusReport',

	components: {
		NcAvatar,
		NcButton,
		NcModal,
		NcLoadingIcon,
		CheckDecagram,
		ClockOutline,
	},

	props: {
		idEmployee: { type: [Number, String], required: true },
		nombreEmpleado: { type: String, default: '' },
		historialCompleto: { type: Array, default: () => [] },
	},

	emits: ['pago-confirmado', 'close'],

	data() {
		return {
			loading: true,
			periodos: [],
			confirmando: false,
			periodoSeleccionado: null,
			fechaConfirmar: '',
			diasConfirmar: 0,
		}
	},

	computed: {
		primasHistoricasEmpleado() {
			return this.historialCompleto.filter(item => {
				if (item.employee_name !== this.nombreEmpleado) return false
				const g = parseInt(item.is_manager)
				const s = parseInt(item.is_partner)
				if (g === 3 || s === 3 || g === 2 || s === 2) return false // cancelada
				if (parseInt(item.bonus_vacation) !== 1) return false
				return true
			})
		},

		empleadoRegistro() {
			return this.historialCompleto.find(item =>
				item.employee_name === this.nombreEmpleado,
			) || {}
		},

		employeeUid() {
			const item = this.empleadoRegistro

			return item.id_user
				|| item.id_user
				|| item.uid
				|| item.user
				|| item.username
				|| this.nombreEmpleado
				|| ''
		},

		empleadoIniciales() {
			if (!this.nombreEmpleado) return '?'

			return this.nombreEmpleado
				.split(/[\s._-]+/)
				.filter(Boolean)
				.map(parte => parte[0])
				.slice(0, 2)
				.join('')
				.toUpperCase()
		},

		resumenPeriodos() {
			const pagados = this.periodos.filter(periodo => periodo.paid)

			return {
				total: this.periodos.length,
				pagados: pagados.length,
				pendientes: this.periodos.length - pagados.length,
				diasPagados: pagados.reduce(
					(total, periodo) => total + (Number(periodo.days_paid) || 0),
					0,
				),
			}
		},
	},

	mounted() {
		this.cargarInforme()
	},

	methods: {
		t,

		async cargarInforme() {
			this.loading = true
			try {
				const [resPeriodos, resPagos] = await Promise.all([
					axios.get(
						generateUrl('/apps/employees/periodos-vacaciones'),
						{ params: { id_employee: this.idEmployee } },
					),
					axios.get(
						generateUrl('/apps/employees/prima-vacacional-pagos'),
						{ params: { id_employee: this.idEmployee } },
					),
				])

				const base = resPeriodos?.data?.ocs?.data?.message || resPeriodos?.data?.message || []
				const pagos = resPagos?.data?.ocs?.data?.message || resPagos?.data?.message || []

				this.periodos = base
					.map(p => {
						const anioPeriodo = p.period_start ? p.period_start.slice(0, 4) : null
						const registroPrima = anioPeriodo
							? this.primasHistoricasEmpleado.find(r => (r.date_from || '').slice(0, 4) === anioPeriodo) || null
							: null
						const pago = pagos.find(pg => Number(pg.number_anniversary) === Number(p.number_anniversary)) || null

						return {
							...p,
							date_request: registroPrima ? registroPrima.date_from : null,
							paid: !!pago,
							days_paid: pago ? pago.days_paid : null,
							date_payment: pago ? pago.date_payment : null,
						}
					})
					.sort((a, b) => b.number_anniversary - a.number_anniversary)
			} catch (err) {
				showError(t('employees', 'Error loading the vacation bonus report'))
			} finally {
				this.loading = false
			}
		},

		async buscarSolicitudPrima(numeroAniversario) {
			try {
				const res = await axios.get(
					generateUrl('/apps/employees/historial-reporte-Anniversary'),
					{
						params: {
							id_employee: this.idEmployee,
							number_anniversary: numeroAniversario,
						},
					},
				)
				const rows = res?.data?.ocs?.data?.message || res?.data?.message || []
				return rows.find(r =>
					Number(r.bonus_vacation) === 1
					&& Number(r.is_manager) !== 2 && Number(r.is_manager) !== 3
					&& Number(r.is_partner) !== 2 && Number(r.is_partner) !== 3,
				) || null
			} catch (err) {
				return null
			}
		},

		abrirConfirmacion(periodo) {
			this.periodoSeleccionado = periodo

			if (periodo.paid) {
				// Editando un pago ya registrado: precarga lo que se guardó.
				this.fechaConfirmar = periodo.date_payment
					? periodo.date_payment.substring(0, 10)
					: (periodo.date_request ? periodo.date_request.substring(0, 10) : '')
				this.diasConfirmar = periodo.days_paid ?? periodo.days_entitlement
			} else {
				// Primera confirmación: la date es la de PAGO (hoy por defecto),
				// no la date en la que se solicitó la prima.
				this.fechaConfirmar = new Date().toISOString().slice(0, 10)
				this.diasConfirmar = periodo.days_entitlement
			}

			this.confirmando = true
		},

		cancelarConfirmacion() {
			this.confirmando = false
			this.periodoSeleccionado = null
		},

		async confirmarPago() {
			const periodo = this.periodoSeleccionado
			try {
				await axios.post(generateUrl('/apps/employees/prima-vacacional-pagos'), {
					id_employee: this.idEmployee,
					number_anniversary: periodo.number_anniversary,
					date_payment: this.fechaConfirmar,
					days_paid: this.diasConfirmar,
				})

				periodo.paid = true
				periodo.days_paid = this.diasConfirmar
				periodo.date_payment = this.fechaConfirmar

				this.$emit('pago-confirmado', {
					id_employee: this.idEmployee,
					number_anniversary: periodo.number_anniversary,
					date: this.fechaConfirmar,
					days_paid: this.diasConfirmar,
				})

				this.confirmando = false
				this.periodoSeleccionado = null
			} catch (err) {
				showError(t('employees', 'Error saving the vacation bonus payment'))
			}
		},

		formatFecha(date) {
			if (!date) return ''
			const [y, m, d] = date.slice(0, 10).split('-')
			return `${d}/${m}/${y}`
		},
	},
}
</script>

<style scoped>
.informe-prima {
	--prima-accent: var(--color-warning);
	--prima-accent-dark: var(--color-warning);
	--prima-accent-soft: rgb(from var(--color-warning) r g b / 0.12);
	--prima-success: var(--color-success);
	--prima-success-soft: var(--color-success);
	--prima-warning: var(--color-warning);
	--prima-warning-soft: var(--color-warning);
	--prima-border: var(--color-border);
	--prima-surface: var(--color-main-background);
	--prima-surface-soft: var(--color-background-hover);

	box-sizing: border-box;
}

.informe-prima,
.informe-prima * {
	box-sizing: border-box;
}

/* ========================================
 * ENCABEZADO
 * ======================================== */

.informe-header {
	display: flex;
	align-items: center;
	justify-content: space-between;

	padding: 22px 24px;
	gap: 24px;

	background:
		linear-gradient(
			135deg,
			rgb(from var(--color-warning) r g b / 0.11),
			rgb(from var(--color-warning) r g b / 0.025) 48%,
			transparent
		);

	border-bottom: 1px solid var(--prima-border);
}

.informe-identidad {
	display: flex;
	align-items: center;

	min-width: 0;
	gap: 14px;
}

.informe-avatar {
	flex: 0 0 auto;

	border: 2px solid rgb(from var(--color-element-warning) r g b / 0.32);
	border-radius: 50%;

	box-shadow: 0 4px 12px rgb(from var(--color-box-shadow) r g b / 0.14);
}

.informe-avatar-fallback {
	display: flex;
	flex: 0 0 56px;
	align-items: center;
	justify-content: center;

	width: 56px;
	height: 56px;

	color: var(--prima-accent-dark);
	font-size: 1rem;
	font-weight: 800;

	background: var(--prima-accent-soft);
	border: 2px solid rgb(from var(--color-element-warning) r g b / 0.3);
	border-radius: 50%;
}

.informe-heading {
	min-width: 0;
}

.informe-eyebrow {
	display: block;

	margin-bottom: 4px;

	color: var(--prima-accent);
	font-size: 0.68rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.11em;
}

.informe-title {
	margin: 0;
	overflow: hidden;

	color: var(--color-main-text);
	font-family: Georgia, 'Iowan Old Style', serif;
	font-size: 1.55rem;
	font-weight: 700;
	line-height: 1.2;
	text-overflow: ellipsis;
	letter-spacing: -0.015em;
	white-space: nowrap;
}

.informe-sub {
	margin: 4px 0 0;
	overflow: hidden;

	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* ========================================
 * RESUMEN SUPERIOR
 * ======================================== */

.informe-resumen {
	display: grid;
	flex: 0 0 auto;
	grid-template-columns: repeat(4, minmax(92px, 1fr));

	min-width: 430px;
	gap: 8px;
}

.resumen-item {
	display: flex;
	flex-direction: column;
	justify-content: center;

	min-height: 62px;
	padding: 9px 11px;
	gap: 3px;

	background: var(--prima-surface);
	border: 1px solid var(--prima-border);
	border-radius: 10px;

	box-shadow: 0 2px 7px rgb(from var(--color-box-shadow) r g b / 0.035);
}

.resumen-item--paid {
	background: var(--prima-success-soft);
	border-color: rgb(from var(--color-border-success) r g b / 0.18);
}

.resumen-item--pending {
	background: var(--prima-warning-soft);
	border-color: rgb(from var(--color-element-warning) r g b / 0.18);
}

.resumen-item--days {
	background: var(--prima-accent-soft);
	border-color: rgb(from var(--color-element-warning) r g b / 0.2);
}

.resumen-item__label {
	color: var(--color-text-maxcontrast);
	font-size: 0.59rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.resumen-item__value {
	color: var(--color-main-text);
	font-size: 1.15rem;
	font-weight: 800;
	line-height: 1;
}

.resumen-item--paid .resumen-item__value {
	color: var(--prima-success);
}

.resumen-item--pending .resumen-item__value {
	color: var(--prima-warning);
}

.resumen-item--days .resumen-item__value {
	color: var(--prima-accent-dark);
}

/* ========================================
 * ESTADOS
 * ======================================== */

.informe-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	min-height: 280px;
	padding: 42px 24px;
	gap: 8px;

	color: var(--color-text-maxcontrast);
	text-align: center;
}

.informe-state strong {
	color: var(--color-main-text);
	font-size: 1rem;
}

.informe-state span {
	max-width: 460px;
	font-size: 0.84rem;
}

.informe-state__icon {
	font-size: 2.5rem !important;
}

/* ========================================
 * TABLA
 * ======================================== */

.ledger-shell {
	padding: 18px 20px 22px;
	overflow-x: auto;
}

.ledger {
	width: 100%;
	min-width: 830px;

	color: var(--color-main-text);
	font-size: 0.82rem;

	background: var(--prima-surface);
	border: 1px solid var(--prima-border);
	border-collapse: separate;
	border-spacing: 0;
	border-radius: 12px;

	table-layout: fixed;
}

.col-Anniversary {
	width: 34%;
}

.col-date {
	width: 17%;
}

.col-action {
	width: 20%;
}

.col-status {
	width: 29%;
}

.ledger thead th {
	padding: 10px 14px;

	color: var(--color-text-maxcontrast);
	font-size: 0.64rem;
	font-weight: 800;
	text-align: left;
	text-transform: uppercase;
	letter-spacing: 0.07em;
	white-space: nowrap;

	background: var(--prima-surface-soft);
	border-bottom: 1px solid var(--prima-border);
}

.ledger thead th:first-child {
	border-top-left-radius: 11px;
}

.ledger thead th:last-child {
	border-top-right-radius: 11px;
}

.ledger-row {
	background: var(--prima-surface);

	transition:
		background-color 0.16s ease,
		box-shadow 0.16s ease;
}

.ledger-row td {
	padding: 13px 14px;

	vertical-align: middle;

	border-bottom: 1px solid var(--prima-border);
}

.ledger-row--paid td:first-child {
	box-shadow: inset 3px 0 0 var(--prima-success);
}

.ledger-row:last-child td {
	border-bottom: none;
}

.cell-Anniversary {
	display: flex;
	align-items: center;
	gap: 12px;
}

.seal {
	display: inline-flex;
	flex: 0 0 38px;
	align-items: center;
	justify-content: center;

	width: 38px;
	height: 38px;

	color: var(--color-text-maxcontrast);
	font-family: Georgia, serif;
	font-size: 0.92rem;
	font-weight: 800;

	background: var(--prima-surface-soft);
	border: 2px dashed var(--prima-border);
	border-radius: 50%;
}

.seal--paid {
	color: var(--prima-accent-dark);

	background: var(--prima-accent-soft);
	border-color: var(--prima-accent);
	border-style: solid;
}

.periodo-info {
	display: flex;
	flex-direction: column;

	min-width: 0;
	gap: 3px;
}

.periodo-info strong {
	overflow: hidden;

	color: var(--color-main-text);
	font-size: 0.88rem;
	font-weight: 750;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.periodo-info small {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
}

.periodo-actual {
	align-self: flex-start;

	padding: 2px 6px;

	color: var(--prima-accent-dark);
	font-size: 0.58rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.045em;

	background: var(--prima-accent-soft);
	border-radius: 999px;
}

.cell-date {
	font-variant-numeric: tabular-nums;
}

.date-principal {
	color: var(--color-main-text);
	font-size: 0.8rem;
	font-weight: 650;
	white-space: nowrap;
}

.muted {
	color: var(--color-text-maxcontrast);
	font-size: 0.77rem;
}

.payment-button {
	min-width: 128px;
	min-height: 36px;
	padding: 7px 12px;

	color: var(--color-main-text);
	font-family: inherit;
	font-size: 0.74rem;
	font-weight: 750;

	background: var(--color-background-darker);
	border: 1px solid var(--color-border);
	border-radius: 8px;

	cursor: pointer;

	transition:
		background-color 0.15s ease,
		border-color 0.15s ease,
		color 0.15s ease,
		transform 0.15s ease;
}

.payment-button--edit {
	color: var(--prima-accent-dark);

	background: var(--prima-accent-soft);
	border-color: rgb(from var(--color-element-warning) r g b / 0.35);
}

.status-stack {
	display: flex;
	flex-direction: column;
	align-items: flex-start;

	gap: 5px;
}

.badge {
	display: inline-flex;
	align-items: center;

	padding: 4px 9px;
	gap: 5px;

	font-size: 0.68rem;
	font-weight: 750;
	white-space: nowrap;

	border-radius: 999px;
}

.badge--paid {
	color: var(--prima-success);
	background: var(--prima-success-soft);
}

.badge--pendiente {
	color: var(--prima-warning);
	background: var(--prima-warning-soft);
}

.status-detalle {
	color: var(--color-text-maxcontrast);
	font-size: 0.68rem;
	font-variant-numeric: tabular-nums;
}

/* ========================================
 * MODAL DE CONFIRMACIÓN
 * ======================================== */

.confirm-pago {
	display: flex;
	flex-direction: column;

	padding: 24px 28px 28px;
	gap: 18px;
}

.confirm-periodo {
	display: flex;
	align-items: center;

	padding: 12px 14px;
	gap: 12px;

	background: var(--prima-accent-soft);
	border: 1px solid rgb(from var(--color-element-warning) r g b / 0.22);
	border-radius: 10px;
}

.confirm-periodo__seal {
	flex-basis: 40px;
	width: 40px;
	height: 40px;
}

.confirm-periodo__info {
	display: flex;
	flex-direction: column;

	min-width: 0;
	gap: 3px;
}

.confirm-periodo__info strong {
	color: var(--color-main-text);
	font-size: 0.9rem;
}

.confirm-periodo__info small {
	color: var(--color-text-maxcontrast);
	font-size: 0.74rem;
	font-variant-numeric: tabular-nums;
}

.confirm-lead {
	margin: 0;

	color: var(--color-main-text);
	font-size: 0.87rem;
	line-height: 1.5;
}

.confirm-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));

	gap: 14px;
}

.confirm-field {
	display: flex;
	flex-direction: column;

	min-width: 0;
	gap: 6px;
}

.confirm-field label {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-weight: 750;
}

.confirm-input {
	width: 100%;
	min-height: 42px;
	padding: 9px 11px;

	color: var(--color-main-text);
	font-family: inherit;
	font-size: 0.88rem;
	font-variant-numeric: tabular-nums;

	background: var(--color-main-background);
	border: 1px solid var(--color-border-dark);
	border-radius: 8px;

	outline: none;

	transition:
		border-color 0.15s ease,
		box-shadow 0.15s ease;
}

.confirm-hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.67rem;
	line-height: 1.35;
}

.confirm-note {
	display: flex;
	align-items: flex-start;

	padding: 10px 12px;
	gap: 8px;

	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	line-height: 1.4;

	background: var(--prima-surface-soft);
	border-radius: 8px;
}

.confirm-note svg {
	flex: 0 0 auto;
	color: var(--prima-accent);
}

.confirm-actions {
	display: flex;
	align-items: center;
	justify-content: flex-end;

	padding-top: 14px;
	gap: 8px;

	border-top: 1px solid var(--prima-border);
}

/* ========================================
 * RESPONSIVE
 * ======================================== */

@media screen and (max-width: 930px) {
	.informe-header {
		align-items: flex-start;
		flex-direction: column;
	}

	.informe-resumen {
		grid-template-columns: repeat(4, minmax(0, 1fr));
		width: 100%;
		min-width: 0;
	}
}

@media screen and (max-width: 700px) {
	.informe-prima {
		width: calc(100vw - 24px);
		max-height: 88vh;
		border-radius: 10px;
	}

	.informe-header {
		padding: 18px;
	}

	.informe-title {
		font-size: 1.25rem;
	}

	.informe-resumen {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.ledger-shell {
		padding: 14px;
	}

	.ledger {
		min-width: 0;
		border: none;
		background: transparent;
	}

	.ledger thead {
		display: none;
	}

	.ledger,
	.ledger tbody,
	.ledger-row {
		display: block;
		width: 100%;
	}

	.ledger-row {
		margin-bottom: 10px;
		overflow: hidden;

		background: var(--prima-surface);
		border: 1px solid var(--prima-border);
		border-radius: 10px;
	}

	.ledger-row td {
		display: grid;
		grid-template-columns: minmax(100px, 38%) 1fr;

		width: 100%;
		padding: 9px 11px;
		gap: 10px;

		border-bottom: 1px solid var(--prima-border);
	}

	.ledger-row td::before {
		content: attr(data-label);

		color: var(--color-text-maxcontrast);
		font-size: 0.62rem;
		font-weight: 800;
		text-transform: uppercase;
		letter-spacing: 0.045em;
	}

	.ledger-row--paid td:first-child {
		box-shadow: inset 3px 0 0 var(--prima-success);
	}

	.cell-Anniversary {
		align-items: center;
		display: grid;
		grid-template-columns: minmax(100px, 38%) 42px 1fr;
	}

	.cell-Anniversary::before {
		grid-column: 1;
	}

	.cell-Anniversary .seal {
		grid-column: 2;
	}

	.cell-Anniversary .periodo-info {
		grid-column: 3;
	}

	.payment-button {
		justify-self: start;
	}

	.confirm-grid {
		grid-template-columns: 1fr;
	}
}

/* ========================================
 * ESTADOS INTERACTIVOS
 * ======================================== */

.ledger-row:hover {
	background: var(--prima-surface-soft);
}

.payment-button:hover {
	background: var(--color-background-hover);
}

.payment-button--edit:hover {
	color: var(--color-main-text);
	background: var(--prima-accent);
	border-color: var(--prima-accent);
}

.payment-button:active {
	transform: scale(0.98);
}

.confirm-input:focus {
	border-color: var(--prima-accent);

	box-shadow: 0 0 0 2px var(--prima-accent-soft);
}
</style>
