<!-- eslint-disable object-curly-newline -->
<template>
	<div class="contacts-list__item-wrapper">
		<!-- Empty state -->
		<div v-if="Object.keys(data).length === 0">
			<div class="empty">
				<div v-if="Object.keys(data).length === 0" class="positions-empty-state">
					<div class="positions-empty-card">
						<img class="positions-empty-image"
							src="../../../../img/crowesito-think.png"
							alt="Empty position state">

						<h2>{{ t('employees', 'Select a position for more details') }}</h2>

						<p class="positions-empty-description">
							{{ t('employees', 'Choose a position from the list to view assigned employees, edit its name or change the display mode.') }}
						</p>

						<div class="positions-empty-grid">
							<div class="positions-empty-item">
								<strong>{{ t('employees', 'View assigned employees') }}</strong>
								<span>{{ t('employees', 'Check which employees currently have this position.') }}</span>
							</div>

							<div class="positions-empty-item">
								<strong>{{ t('employees', 'Edit positions') }}</strong>
								<span>{{ t('employees', 'Update position names.') }}</span>
							</div>

							<div class="positions-empty-item">
								<strong>{{ t('employees', 'Change view') }}</strong>
								<span>{{ t('employees', 'Switch between card view and list view.') }}</span>
							</div>
						</div>

						<div class="positions-empty-actions">
							<NcButton type="primary" @click="$root.$emit('reload')">
								{{ t('employees', 'Refresh positions') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Content -->
		<div v-else>
			<div class="position-details">
				<div class="position-hero">
					<div class="position-hero__content">
						<span class="position-hero__eyebrow">
							{{ t('employees', 'Position details') }}
						</span>
						<div class="position-hero__title-row">
							<h2 class="position-hero__title">
								{{ data.name }}
							</h2>
							<span class="position-hero__count">
								{{ employeeCount }} {{ t('employees', 'employees') }}
							</span>
						</div>
						<p class="position-hero__description">
							{{ t('employees', 'Review the employees assigned to this position, rename it when needed and switch between available display modes.') }}
						</p>
						<div class="position-hero__meta">
							<div class="position-meta-card">
								<span class="position-meta-card__label">{{ t('employees', 'Position') }}</span>
								<strong class="position-meta-card__value">{{ data.name }}</strong>
							</div>
							<div class="position-meta-card">
								<span class="position-meta-card__label">{{ t('employees', 'Level') }}</span>
								<strong class="position-meta-card__value">{{ checknull(data.Nivel) !== '' ? data.Nivel : t('employees', 'Not set') }}</strong>
							</div>
							<div class="position-meta-card">
								<span class="position-meta-card__label">{{ t('employees', 'Assigned employees') }}</span>
								<strong class="position-meta-card__value">{{ employeeCount }}</strong>
							</div>
							<div class="position-meta-card">
								<span class="position-meta-card__label">{{ t('employees', 'Display mode') }}</span>
								<strong class="position-meta-card__value">
									{{ preferencias_positions ? t('employees', 'Cards') : t('employees', 'List') }}
								</strong>
							</div>
						</div>
					</div>
					<div class="position-hero__actions">
						<NcButton type="primary" @click="showEdit()">
							<template #icon>
								<AccountEdit :size="20" />
							</template>
							{{ t('employees', 'Edit') }}
						</NcButton>
						<NcActions>
							<template #icon>
								<AccountCog :size="20" />
							</template>

							<NcActionButton :close-after-click="true" @click="ChangeView()">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								{{ t('employees', 'Change view type') }}
							</NcActionButton>

							<NcActionSeparator />

							<NcActionButton :close-after-click="true" @click="showDialog = true">
								<template #icon>
									<DeleteAlert :size="20" />
								</template>
								{{ t('employees', 'Delete position') }}
							</NcActionButton>

							<NcDialog
								:open.sync="showDialog"
								:name="t('employees', 'Confirm')"
								:message="t('employees', 'Do you want to delete {position}?', { position: data.name })"
								:buttons="buttons" />
						</NcActions>
					</div>
				</div>
				<div class="employees-panel">
					<div class="employees-panel__header">
						<div>
							<h3 class="employees-panel__title">
								{{ t('employees', 'Employees in position') }}
							</h3>
							<p class="employees-panel__subtitle">
								{{ employeeCount }} {{ t('employees', 'people assigned to this position') }}
							</p>
						</div>
						<span class="employees-panel__view-badge">
							{{ preferencias_positions ? t('employees', 'Card view') : t('employees', 'List view') }}
						</span>
					</div>
					<!-- Card/grid view -->
					<div v-if="preferencias_positions" class="employees-grid-panel">
						<ul class="employees-grid">
							<li
								v-for="item in peopleArea.puesto"
								:key="item.id_employees"
								class="employees-grid__item">
								<div class="employee-card">
									<NcAvatar :user="item.id_user" :display-name="item.id_user" :size="60" />
									<div class="employee-card__body">
										<div class="employee-card__name">
											{{ item.displayname ? item.displayname : item.id_user }}
										</div>
										<div class="employee-card__user">
											{{ item.id_user }}
										</div>
									</div>
								</div>
							</li>
						</ul>
					</div>

					<!-- List view -->
					<div v-else class="employees-list-panel">
						<ul class="employees-list">
							<NcListItem
								v-for="item in peopleArea.puesto"
								:key="item.id_employees"
								bold
								:name="item.displayname ? item.displayname : item.id_user"
								@click.prevent>
								<template #icon>
									<NcAvatar
										:size="44"
										:user="item.id_user"
										:display-name="item.displayname ? item.displayname : item.id_user" />
								</template>
								<template v-if="!item.displayname" #subname>
									{{ item.id_user }}
								</template>
							</NcListItem>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<NcModal
			v-if="show"
			ref="modalRef"
			:name="t('employees', 'Edit')"
			@close="closeModal">
			<div class="modal__content">
				<div class="modal-form">
					<div class="form-group">
						<NcTextField
							:value.sync="area"
							:v-model="area"
							:label="t('employees', 'Position name')" />
					</div>
					<div class="form-group">
						<NcTextField
							:value.sync="level"
							type="number"
							:label="t('employees', 'Level')" />
					</div>
					<div class="form-group">
						<NcButton
							class="center"
							:aria-label="t('employees', 'Save changes')"
							type="primary"
							@click="guardarcambioarea()">
							{{ t('employees', 'Save changes') }}
						</NcButton>
					</div>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
// ICONOS
import DeleteAlert from 'vue-material-design-icons/DeleteAlert.vue'
import AccountEdit from 'vue-material-design-icons/AccountEdit.vue'
import AccountCog from 'vue-material-design-icons/AccountCog.vue'

import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import {
	NcAvatar,
	NcActions,
	NcActionButton,
	NcActionSeparator,
	NcDialog,
	NcTextField,
	NcButton,
	NcListItem,
	NcModal,
} from '@nextcloud/vue'

export default {
	name: 'PositionsDetails',

	components: {
		NcAvatar,
		NcActionSeparator,
		NcActions,
		AccountCog,
		AccountEdit,
		NcActionButton,
		DeleteAlert,
		NcDialog,
		NcTextField,
		NcButton,
		NcListItem,
		NcModal,
	},

	props: {
		data: {
			type: Object,
			required: true,
		},
		peopleArea: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			show: false,
			options: [],
			Empleados: [],
			showDialog: false,
			buttons: [
				{
					label: this.t('employees', 'Cancel'),
					callback: () => { this.lastResponse = 'Pressed "Cancel"' },
				},
				{
					label: this.t('employees', 'Delete'),
					type: 'primary',
					callback: () => { this.eliminarPuesto(this.data.id_positions) },
				},
			],
			area: '',
			level: '',
			preferencias_positions: null,
		}
	},

	computed: {
		employeeCount() {
			return this.peopleArea?.puesto?.length || 0
		},
	},

	mounted() {
		this.$root.$on('show', (data) => {
			this.show = data
		})
		this.preferencias_positions = localStorage.getItem('nextcloud_empleados_preferencias_positions')
		if (this.preferencias_positions === null) {
			localStorage.setItem('nextcloud_empleados_preferencias_positions', 'false')
			this.preferencias_positions = false
		} else {
			this.preferencias_positions = this.preferencias_positions === 'true'
		}
	},

	methods: {
		t,

		showEdit() {
			this.show = !this.show
			if (this.show === true) {
				this.getall()
				this.area = this.data.name
				this.level = this.checknull(this.data.Nivel)
			}
		},

		closeModal() {
			this.show = !this.show
		},

		async eliminarPuesto(puesto) {
			this.showDialog = false
			try {
				await axios.post(generateUrl('/apps/employees/EliminarPuesto'), {
					id_position: puesto,
				})
				showSuccess(this.t('employees', 'Position removed successfully'))
				this.$root.$emit('reload')
				this.$root.$emit('send-data-position', {})
			} catch (err) {
				showError(this.t('employees', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},

		ChangeView() {
			this.preferencias_positions = !this.preferencias_positions
			localStorage.setItem('nextcloud_empleados_preferencias_positions', this.preferencias_positions)
		},

		checknull(val) {
			return val == null ? '' : val
		},

		async getall() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetPositionsFix'))
				this.options = response.data
			} catch (err) {
				showError(this.t('employees', 'An exception has occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async guardarcambioarea() {
			try {
				await axios.post(generateUrl('/apps/employees/GuardarCambioPositions'), {
					id_positions: this.data.id_positions,
					name: this.area,
					level: this.level !== '' && this.level !== null ? Number(this.level) : null,
				})
				showSuccess(this.t('employees', 'Position updated successfully'))
				this.$root.$emit('reload')
				this.$root.$emit('send-data-position', {})
				this.showEdit()
			} catch (err) {
				this.showEdit()
				showError(this.t('employees', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style>
.position-details {
	padding: 20px;
}

.position-hero {
	display: flex;
	justify-content: space-between;
	gap: 18px;
	padding: 24px;
	border: 1px solid var(--color-border);
	border-radius: 22px;
	background:
		radial-gradient(circle at top right, rgb(from var(--color-primary-element-light) r g b / 0.14), transparent 26%),
		linear-gradient(135deg, var(--color-main-background), var(--color-background-dark));
	box-shadow: 0 16px 36px rgb(from var(--color-box-shadow) r g b / 0.08);
}

.position-hero__content {
	flex: 1;
	min-width: 0;
}

.position-hero__eyebrow {
	display: inline-flex;
	margin-bottom: 10px;
	padding: 5px 11px;
	border-radius: 999px;
	background: rgb(from var(--color-primary-element-light) r g b / 0.12);
	color: var(--color-primary-element);
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.position-hero__title-row {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

.position-hero__title {
	margin: 0;
	font-size: 28px;
	line-height: 1.1;
}

.position-hero__count {
	display: inline-flex;
	align-items: center;
	padding: 7px 12px;
	border-radius: 999px;
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 12px;
	font-weight: 700;
}

.position-hero__description {
	max-width: 680px;
	margin: 12px 0 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.55;
}

.position-hero__meta {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 14px;
	margin-top: 20px;
}

.position-meta-card {
	padding: 14px 16px;
	border: 1px solid rgb(from var(--color-border) r g b / 0.65);
	border-radius: 16px;
	background: rgb(from var(--color-main-background) r g b / 0.72);
	backdrop-filter: blur(8px);
}

.position-meta-card__label {
	display: block;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.position-meta-card__value {
	display: block;
	font-size: 15px;
	line-height: 1.4;
	color: var(--color-main-text);
}

.position-hero__actions {
	display: flex;
	align-items: flex-start;
	justify-content: flex-end;
}

.employees-panel {
	margin-top: 20px;
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: 22px;
	background: linear-gradient(180deg, rgb(from var(--color-main-background) r g b / 0.88), var(--color-main-background));
	box-shadow: 0 10px 28px rgb(from var(--color-box-shadow) r g b / 0.06);
}

.employees-panel__header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 14px;
	flex-wrap: wrap;
	margin-bottom: 16px;
}

.employees-panel__title {
	margin: 0;
	font-size: 20px;
}

.employees-panel__subtitle {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}

.employees-panel__view-badge {
	display: inline-flex;
	align-items: center;
	padding: 7px 12px;
	border-radius: 999px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	color: var(--color-main-text);
	font-size: 12px;
	font-weight: 600;
}

.employees-grid-panel,
.employees-list-panel {
	padding: 8px;
	border-radius: 18px;
	background: rgb(from var(--color-primary-element-light) r g b / 0.08);
}

.employees-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 14px;
	padding: 0;
	margin: 0;
	list-style: none;
}

.employees-grid__item {
	min-width: 0;
}

.employee-card {
	display: flex;
	align-items: center;
	gap: 14px;
	height: 100%;
	padding: 16px;
	border: 1px solid rgb(from var(--color-primary-element) r g b / 0.2);
	border-radius: 18px;
	background: linear-gradient(180deg, var(--color-main-background), rgb(from var(--color-main-background) r g b / 0.96));
	box-shadow: 0 10px 24px rgb(from var(--color-box-shadow) r g b / 0.08);
	transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.employee-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 16px 30px rgb(from var(--color-box-shadow) r g b / 0.12);
}

.employee-card__body {
	min-width: 0;
}

.employee-card__name {
	font-size: 14px;
	font-weight: 700;
	color: var(--color-main-text);
	word-break: break-word;
}

.employee-card__user {
	margin-top: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	word-break: break-word;
}

.employees-list {
	padding: 0;
	margin: 0;
	list-style: none;
}

.modal__content {
	margin: 40px;
}

.modal-form {
	display: flex;
	flex-direction: column;
}

.form-group {
	margin: calc(var(--default-grid-baseline) * 4) 0;
	display: flex;
	flex-direction: column;
	align-items: flex-start;
}
.positions-empty-state {
	min-height: calc(100vh - var(--header-height) - 80px);
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 32px;
}

.positions-empty-card {
	width: min(760px, 100%);
	padding: 36px;
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 16px rgb(from var(--color-box-shadow) r g b / 0.08);
	text-align: center;
}

.positions-empty-image {
	width: 150px;
	margin-bottom: 16px;
	opacity: 0.95;
}

.positions-empty-card h2 {
	margin: 0 0 8px;
	font-size: 24px;
	font-weight: 700;
	color: var(--color-main-text);
}

.positions-empty-description {
	max-width: 560px;
	margin: 0 auto 24px;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.positions-empty-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin: 24px 0;
}

.positions-empty-item {
	padding: 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	text-align: left;
}

.positions-empty-item strong {
	display: block;
	margin-bottom: 6px;
	color: var(--color-main-text);
	font-size: 15px;
}

.positions-empty-item span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	line-height: 1.4;
}

.positions-empty-actions {
	display: flex;
	justify-content: center;
	gap: 12px;
	flex-wrap: wrap;
	margin-top: 20px;
}

@media (max-width: 960px) {
	.position-hero {
		flex-direction: column;
	}

	.position-hero__meta {
		grid-template-columns: 1fr;
	}

	.position-hero__actions {
		justify-content: flex-start;
	}
}

@media (max-width: 700px) {
	.position-details {
		padding: 12px;
	}

	.position-hero,
	.employees-panel {
		padding: 18px;
		border-radius: 18px;
	}

	.position-hero__title {
		font-size: 24px;
	}

	.modal__content {
		margin: 24px 18px;
	}

	.positions-empty-state {
		align-items: flex-start;
		padding: 20px 12px;
	}

	.positions-empty-card {
		padding: 24px 16px;
	}

	.positions-empty-grid {
		grid-template-columns: 1fr;
	}

	.positions-empty-item {
		text-align: center;
	}
}
</style>
