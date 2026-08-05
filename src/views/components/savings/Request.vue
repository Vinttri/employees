<template id="content">
	<NcAppContent v-if="loading == true" name="Loading">
		<NcLoadingIcon />
	</NcAppContent>

	<NcAppContent v-else name="Loading">
		<div class="savings-page">
			<section class="savings-hero">
				<div class="savings-hero__copy">
					<p class="section-label">
						{{ t('employees', 'Savings loan') }}
					</p>
					<h2>{{ t('employees', 'Request and track your savings loan') }}</h2>
					<p>
						{{ t('employees', 'Check your available balance, request a loan and review previous movements from one place.') }}
					</p>
				</div>

				<div class="hero-actions">
					<NcButton
						v-if="userdata.state == 1"
						:aria-label="t('employees', 'Create request')"
						type="primary"
						wide
						@click="showSolicitud()">
						<template #icon>
							<Check :size="20" />
						</template>
						{{ t('employees', 'Create request') }}
					</NcButton>
				</div>
			</section>

			<NcNoteCard
				v-if="!hasEmployeeProfile"
				type="warning"
				:text="t('employees', 'Your user is not linked to an employee profile.')" />

			<section v-if="hasEmployeeProfile" class="summary-grid">
				<article class="summary-card summary-card-accent">
					<span>{{ t('employees', 'Current savings') }}</span>
					<strong>{{ savingsFormateado }}</strong>
					<small>{{ t('employees', 'Savings fund balance') }}</small>
				</article>

				<article class="summary-card">
					<span>{{ t('employees', 'Available to request') }}</span>
					<strong>{{ aproxFormateado }}</strong>
					<small>{{ t('employees', 'Up to {percent} of your balance', { percent: '90%' }) }}</small>
				</article>

				<article class="summary-card">
					<span>{{ t('employees', 'Request status') }}</span>
					<strong>{{ estadoSolicitud.label }}</strong>
					<small>{{ estadoSolicitud.description }}</small>
				</article>
			</section>

			<NcNoteCard
				v-if="hasEmployeeProfile && userdata.state == 2"
				type="success"
				:text="t('employees', 'Your request has been sent, please wait for a response.')" />

			<NcNoteCard
				v-if="hasEmployeeProfile && (userdata.state == 0 || !userdata.state)"
				type="info"
				:text="t('employees', 'Your profile is in read-only mode.')" />
		</div>

		<!-- Request modal -->
		<NcModal
			v-if="hasEmployeeProfile && modal && userdata.state == 1"
			ref="modalRef"
			size="large"
			:name="t('employees', 'Request')"
			@close="showSolicitud()">
			<div class="modal__content">
				<div class="request-layout">
					<div class="request-info">
						<p class="section-label">
							{{ t('employees', 'Loan conditions') }}
						</p>
						<h3>{{ t('employees', 'Before submitting') }}</h3>
						<ul class="info-list">
							<li>{{ t('employees', 'On June 30th, the deposit corresponding to the Savings Fund Loan will be made. This loan does not accrue interest.') }}</li>
							<li>{{ t('employees', 'The available amount may be up to {percent} of the total accumulated to date, considering both the worker and the employer contributions.', { percent: '90%' }) }}</li>
							<li>{{ t('employees', 'Enter the amount in pesos. If you wish to request a specific percentage, add it in the notes field.') }}</li>
							<li>{{ t('employees', 'Carefully verify the information before submitting your request, as it cannot be canceled or modified.') }}</li>
						</ul>
					</div>

					<div class="request-form">
						<div class="available-card">
							<span>{{ t('employees', 'Maximum available') }}</span>
							<strong>{{ aproxFormateado }}</strong>
						</div>
						<NcTextField
							:model-value="cantidadFormateada"
							:label="t('employees', 'Amount to request')"
							trailing-button-icon="close"
							:show-trailing-button="quantity !== ''"
							@trailing-button-click="limpiarCantidad"
							@input="actualizarCantidad"
							@keypress="soloNumerosYPunto">
							<template #icon>
								<CurrencyUsd :size="20" />
							</template>
						</NcTextField>

						<NcTextArea
							v-model="notes"
							:label="t('employees', 'Notes')"
							:placeholder="t('employees', 'Notes')" />

						<NcCheckboxRadioSwitch
							:checked.sync="acept_terms"
							value="true"
							name="acept_terms">
							<strong class="terms-text">
								{{ t('employees', 'I authorize to deduct from my savings the corresponding amount according to the loan conditions.') }}
							</strong>
						</NcCheckboxRadioSwitch>

						<div class="form-actions">
							<NcButton
								:text="t('employees', 'Submit request')"
								type="primary"
								:disabled="!isFormValid"
								@click="EnviarSolicitud()">
								{{ t('employees', 'Submit request') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</NcModal>

		<historial v-if="hasEmployeeProfile" :id="userdata.id_savings" />
	</NcAppContent>
</template>

<script>
import historial from './History.vue'
import { ref } from 'vue'
import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

// iconos
import CurrencyUsd from 'vue-material-design-icons/CurrencyUsd.vue'
import Check from 'vue-material-design-icons/Check.vue'

import {
	NcLoadingIcon,
	NcAppContent,
	NcNoteCard,
	NcButton,
	NcModal,
	NcTextField,
	NcTextArea,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'

import { getLocale, translate as t } from '@nextcloud/l10n'

export default {
	name: 'Request',

	components: {
		NcLoadingIcon,
		NcAppContent,
		NcNoteCard,
		Check,
		CurrencyUsd,
		NcButton,
		NcModal,
		NcTextField,
		NcTextArea,
		NcCheckboxRadioSwitch,
		historial,
	},

	inject: {
		employee: { default: () => [] },
	},

	data() {
		return {
			loading: true,
			userdata: [],
			options: [],
			modal: false,
			modalRef: ref(null),
			quantity: '',
			notes: '',
			aproxValor: 0,
			aproxFormateado: '',
			acept_terms: [],
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

		cantidadFormateada() {
			if (this.quantity === '') return ''
			const partes = this.quantity.toString().split('.')
			partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',')
			return partes.join('.')
		},

		savingsFormateado() {
			return this.formatMoney(this.toNumber(this.currentEmployee?.savings_fund))
		},

		estadoSolicitud() {
			if (Number(this.userdata.state) === 1) {
				return {
					label: t('employees', 'Available'),
					description: t('employees', 'You can submit a new savings loan request.'),
				}
			}

			if (Number(this.userdata.state) === 2) {
				return {
					label: t('employees', 'Pending review'),
					description: t('employees', 'Your request was sent and is waiting for approval.'),
				}
			}

			return {
				label: t('employees', 'Read-only'),
				description: t('employees', 'Requests are currently disabled for your profile.'),
			}
		},

		isFormValid() {
			return this.acept_terms[0] === 'true'
				&& this.quantity !== ''
				&& Number(this.quantity) > 0
				&& Number(this.quantity) <= this.aproxValor
		},
	},

	mounted() {
		if (!this.hasEmployeeProfile) {
			this.loading = false
			return
		}

		const savingsFund = this.currentEmployee.savings_fund ?? '0'
		this.aproxValor = this.toNumber(savingsFund) * 0.9
		this.aproxFormateado = this.formatMoney(this.aproxValor)
		this.getAll()
	},

	methods: {
		// expone t en template si lo quieres usar como método
		t,

		toNumber(value) {
			const normalized = String(value ?? '0').replace(/,/g, '')
			const number = Number(normalized)
			return Number.isFinite(number) ? number : 0
		},

		formatMoney(value) {
			const locale = String(getLocale() || 'en').replace('_', '-')
			return Intl.NumberFormat(locale, { style: 'currency', currency: 'MXN' }).format(Number(value) || 0)
		},

		async getAll() {
			if (!this.hasEmployeeProfile) {
				this.loading = false
				return
			}

			this.loading = true
			try {
				const response = await axios.post(generateUrl('/apps/employees/GetInfoAhorro'), {
					id_user: this.currentEmployee.id_employees,
				})
				const payload = response?.data?.ocs?.data ?? response?.data ?? []
				const savings = Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : [])

				this.userdata = savings[0] || {}
			} catch (err) {
				console.error(err)
				this.userdata = {}
				showError(t('employees', 'Could not fetch your information'))
			} finally {
				this.loading = false
			}
		},

		showSolicitud() {
			this.modal = !this.modal
		},

		actualizarCantidad(event) {
			let valor = event.target.value.replace(/,/g, '')

			if (!/^\d*\.?\d*$/.test(valor)) {
				valor = this.quantity
			}
			if (Number(valor) > this.aproxValor) {
				valor = this.quantity
			}
			this.quantity = valor
		},

		soloNumerosYPunto(event) {
			const char = String.fromCharCode(event.which)

			if (!/[0-9.]/.test(char)) {
				event.preventDefault()
			}
			if (char === '.' && this.quantity.includes('.')) {
				event.preventDefault()
			}

			const posibleValor = (this.quantity + char).replace(/,/g, '')
			if (Number(posibleValor) > this.aproxValor) {
				event.preventDefault()
			}
		},

		limpiarCantidad() {
			this.quantity = ''
		},

		EnviarSolicitud() {
			if (this.acept_terms[0] === 'true' && this.quantity !== '') {
				axios.post(
					generateUrl('/apps/employees/EnviarSolicitud'),
					{
						id_savings: this.userdata.id_savings,
						quantity_requested: this.quantity,
						note: this.notes,
					},
				).then(
					() => {
						this.getAll()
						this.modal = false
						this.quantity = ''
						this.notes = ''
						this.aproxValor = 0
						this.aproxFormateado = ''
						this.acept_terms = []
					},
					(err) => { showError(Promise.reject(err)) },
				)
			} else {
				showError(t('employees', 'Verifique el formulario'))
			}
		},
	},
}
</script>

<style scoped>
.savings-page {
	display: grid;
	gap: 18px;
	padding: 20px;
}

.savings-hero,
.summary-card,
.request-info,
.request-form {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.savings-hero {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 18px;
	padding: 22px;
	background: linear-gradient(180deg, var(--color-main-background) 0%, var(--color-background-hover) 100%);
}

.section-label {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.savings-hero h2,
.request-info h3 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	line-height: 1.2;
}

.savings-hero p {
	max-width: 720px;
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.45;
}

.hero-actions {
	min-width: 190px;
}

.summary-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 14px;
}

.summary-card {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
	padding: 16px;
}

.summary-card-accent {
	border-color: rgba(37, 99, 235, .22);
	background: linear-gradient(180deg, rgba(37, 99, 235, .08), rgba(37, 99, 235, .02)), var(--color-main-background);
}

.summary-card span,
.available-card span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.summary-card strong,
.available-card strong {
	color: var(--color-main-text);
	font-size: 26px;
	line-height: 1.1;
}

.summary-card small {
	color: var(--color-text-maxcontrast);
	line-height: 1.35;
}

.modal__content {
	padding: 22px;
}

.request-layout {
	display: grid;
	grid-template-columns: minmax(260px, .9fr) minmax(320px, 1.1fr);
	gap: 18px;
}

.request-info,
.request-form {
	padding: 18px;
}

.info-list {
	display: grid;
	gap: 10px;
	margin: 16px 0 0;
	padding-left: 18px;
	color: var(--color-text-maxcontrast);
	line-height: 1.45;
}

.request-form {
	display: grid;
	gap: 14px;
}

.available-card {
	display: grid;
	gap: 4px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-background-hover);
}

.terms-text {
	margin-left: 8px;
	font-size: 13px;
	line-height: 1.35;
}

.form-actions {
	display: flex;
	justify-content: flex-end;
}

@media (max-width: 800px) {
	.savings-page {
		padding: 12px;
	}

	.savings-hero,
	.request-layout {
		grid-template-columns: 1fr;
	}

	.savings-hero {
		align-items: stretch;
		flex-direction: column;
	}

	.summary-grid {
		grid-template-columns: 1fr;
	}
}
</style>
