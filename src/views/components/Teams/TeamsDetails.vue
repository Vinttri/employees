<!-- eslint-disable object-curly-newline -->
<template>
	<div class="contacts-list__item-wrapper">
		<div v-if="Object.keys(data).length === 0">
			<div v-if="Object.keys(data).length === 0" class="teams-empty-state">
				<div class="teams-empty-card">
					<img class="teams-empty-image" src="../../../../img/crowesito-think.png" alt="Empty team state">

					<h2>{{ t('employees', 'Select a team for more details') }}</h2>

					<p class="teams-empty-description">
						{{ t('employees', 'Choose a team from the list to view its members, team lead and available actions.') }}
					</p>

					<div class="teams-empty-grid">
						<div class="teams-empty-item">
							<strong>{{ t('employees', 'Team members') }}</strong>
							<span>{{ t('employees', 'Review the employees assigned to each work team.') }}</span>
						</div>

						<div class="teams-empty-item">
							<strong>{{ t('employees', 'Team lead') }}</strong>
							<span>{{ t('employees', 'Check or update the person responsible for the team.') }}</span>
						</div>

						<div class="teams-empty-item">
							<strong>{{ t('employees', 'Change view') }}</strong>
							<span>{{ t('employees', 'Switch between card view and list view.') }}</span>
						</div>
					</div>

					<div class="teams-empty-actions">
						<NcButton type="primary" @click="$root.$emit('reload')">
							{{ t('employees', 'Refresh teams') }}
						</NcButton>
					</div>
				</div>
			</div>
		</div>

		<div v-else>
			<div class="team-details">
				<div class="team-hero">
					<div class="team-hero__content">
						<span class="team-hero__eyebrow">
							{{ t('employees', 'Team details') }}
						</span>
						<div class="team-hero__title-row">
							<h2 class="team-hero__title">
								{{ data.name }}
							</h2>
							<span class="team-hero__count">
								{{ memberCount }} {{ t('employees', 'members') }}
							</span>
						</div>
						<p class="team-hero__description">
							{{ t('employees', 'Review the people assigned to this team, update its lead and switch between available display modes.') }}
						</p>
						<div class="team-hero__meta">
							<div class="team-meta-card">
								<span class="team-meta-card__label">{{ t('employees', 'Team') }}</span>
								<strong class="team-meta-card__value">{{ data.name }}</strong>
							</div>
							<div class="team-meta-card">
								<span class="team-meta-card__label">{{ t('employees', 'Team lead') }}</span>
								<strong class="team-meta-card__value">{{ data.team_leader_id || t('employees', 'Not assigned') }}</strong>
							</div>
							<div class="team-meta-card">
								<span class="team-meta-card__label">{{ t('employees', 'Display mode') }}</span>
								<strong class="team-meta-card__value">
									{{ preferencias_teams ? t('employees', 'Cards') : t('employees', 'List') }}
								</strong>
							</div>
						</div>
					</div>
					<div class="team-hero__actions">
						<NcActions>
							<template #icon>
								<AccountCog :size="20" />
							</template>

							<NcActionButton :close-after-click="true" @click="showEdit()">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								{{ t('employees', 'Enable editing') }}
							</NcActionButton>

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
								{{ t('employees', 'Delete team') }}
							</NcActionButton>

							<NcDialog
								:open.sync="showDialog"
								:name="t('employees', 'Confirm')"
								:message="t('employees', 'Do you want to delete {name}?', { name: data.name })"
								:buttons="buttons" />
						</NcActions>
					</div>
				</div>
				<div class="members-panel">
					<div class="members-panel__header">
						<div>
							<h3 class="members-panel__title">
								{{ t('employees', 'Team members') }}
							</h3>
							<p class="members-panel__subtitle">
								{{ memberCount }} {{ t('employees', 'people assigned to this team') }}
							</p>
						</div>
						<span class="members-panel__view-badge">
							{{ preferencias_teams ? t('employees', 'Card view') : t('employees', 'List view') }}
						</span>
					</div>
					<!-- Cards -->
					<div v-if="preferencias_teams" class="members-grid-panel">
						<ul class="members-grid">
							<li v-for="item in peopleArea.equipo" :key="item.id_employees" class="members-grid__item">
								<div class="member-card">
									<NcAvatar :user="item.id_user" :display-name="item.id_user" :size="60" />
									<div class="member-card__body">
										<div class="member-card__name">
											{{ item.displayname ? item.displayname : item.id_user }}
										</div>
										<div class="member-card__user">
											{{ item.id_user }}
										</div>
									</div>
								</div>
							</li>
						</ul>
					</div>

					<!-- List -->
					<div v-else class="members-list-panel">
						<ul class="members-list">
							<NcListItem
								v-for="item in peopleArea.equipo"
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

		<!-- Edit modal -->
		<NcModal v-if="show"
			ref="modalRef"
			:name="t('employees', 'Edit')"
			@close="closeModal">
			<div class="modal__content">
				<div class="modal-form">
					<div class="form-group">
						<NcTextField
							:value.sync="team_name"
							:v-model="team_name"
							:label="t('employees', 'Team name')" />
					</div>

					<div class="form-group">
						<NcSelect
							v-model="selected_user"
							:options="optionsGestor"
							:user-select="true"
							:input-label="t('employees', 'Team lead')" />
					</div>

					<div class="form-group">
						<NcButton class="center"
							:aria-label="t('employees', 'Save changes')"
							type="primary"
							@click="GuardarCambioEquipo()">
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
	NcSelect,
	NcListItem,
	NcModal,
} from '@nextcloud/vue'

export default {
	name: 'TeamsDetails',

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
		NcSelect,
		NcListItem,
		NcModal,
	},

	props: {
		data: { type: Object, required: true },
		peopleArea: { type: Object, required: true },
	},

	data() {
		return {
			show: false,
			showDialog: false,

			// Para edición
			team_name: '',
			optionsGestor: [], // lista de usuarios (from GetConfigurations.Users)
			selected_user: null, // objeto usuario (NcSelect) o string id

			// Preferencias de vista
			preferencias_teams: null,

			buttons: [
				{
					label: 'Cancelar',
					callback: () => { this.lastResponse = 'Pressed "Cancel"' },
				},
				{
					label: 'Eliminar',
					type: 'primary',
					callback: () => { this.eliminarEquipo(this.data.id_team) },
				},
			],
		}
	},

	computed: {
		memberCount() {
			return this.peopleArea?.equipo?.length || 0
		},
	},

	mounted() {
		this.$root.$on('show', (data) => { this.show = data })
		this.preferencias_teams = localStorage.getItem('nextcloud_empleados_preferencias_teams')
		if (this.preferencias_teams === null) {
			localStorage.setItem('nextcloud_empleados_preferencias_teams', 'false')
			this.preferencias_teams = false
		} else {
			this.preferencias_teams = this.preferencias_teams === 'true'
		}
	},

	methods: {
		t,

		async showEdit() {
			this.show = !this.show
			if (this.show) {
				try {
					const response = await axios.get(generateUrl('/apps/employees/GetConfigurations'))
					const data = response?.data || {}

					this.optionsGestor = data.Users || []
					this.team_name = this.data.name

					const current = this.data.team_leader_id
					this.selected_user = this.optionsGestor.find(
						opt => String(opt.id) === String(current),
					) || null
				} catch (err) {
					showError(t('employees', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
				}
			}
		},

		closeModal() {
			this.show = !this.show
		},

		async eliminarEquipo(equipo) {
			this.showDialog = false
			try {
				await axios.post(generateUrl('/apps/employees/EliminarEquipo'), { id_team: equipo })
				showSuccess(t('employees', 'Equipo eliminado exitosamente'))
				this.$root.$emit('reload')
				this.$root.$emit('send-data-team', {})
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		ChangeView() {
			this.preferencias_teams = !this.preferencias_teams
			localStorage.setItem('nextcloud_empleados_preferencias_teams', this.preferencias_teams)
		},

		// Normaliza el valor de selected_user para enviar ID correcto
		_normalizeSelectedUser(val) {
			// NcSelect con userSelect suele retornar el objeto de usuario con { id, uid, displayName, ... }
			// pero si ya viene de la API puede ser string. Manejamos ambos.
			if (!val) return ''
			if (typeof val === 'string') return val
			if (typeof val === 'object' && val.id) return val.id
			if (typeof val === 'object' && val.uid) return val.uid
			return String(val)
		},

		async GuardarCambioEquipo() {
			try {
				const idJefe = this._normalizeSelectedUser(this.selected_user)

				await axios.post(generateUrl('/apps/employees/GuardarCambioEquipo'), {
					Id_Equipo: this.data.id_team,
					team_leader_id: idJefe,
					name: this.team_name,
				})

				showSuccess(t('employees', 'Equipo actualizado exitosamente'))
				this.$root.$emit('reload')
				this.$root.$emit('send-data-team', {})
				this.showEdit()
			} catch (err) {
				this.showEdit()
				showError(t('employees', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style>
.team-details {
	padding: 20px;
}

.team-hero {
	display: flex;
	justify-content: space-between;
	gap: 18px;
	padding: 24px;
	border: 1px solid var(--color-border);
	border-radius: 22px;
	background: var(--color-main-background);
	box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
}

.team-hero__content {
	flex: 1;
	min-width: 0;
}

.team-hero__eyebrow {
	display: inline-flex;
	margin-bottom: 10px;
	padding: 5px 11px;
	border-radius: 999px;
	background: rgba(52, 120, 246, 0.12);
	color: var(--color-primary-element);
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.team-hero__title-row {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

.team-hero__title {
	margin: 0;
	font-size: 28px;
	line-height: 1.1;
}

.team-hero__count {
	display: inline-flex;
	align-items: center;
	padding: 7px 12px;
	border-radius: 999px;
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 12px;
	font-weight: 700;
}

.team-hero__description {
	max-width: 680px;
	margin: 12px 0 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.55;
}

.team-hero__meta {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 14px;
	margin-top: 20px;
}

.team-meta-card {
	padding: 14px 16px;
	border: 1px solid var(--color-border);
	border-radius: 16px;
	background: var(--color-background-hover);
}

.team-meta-card__label {
	display: block;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.team-meta-card__value {
	display: block;
	font-size: 15px;
	line-height: 1.4;
	color: var(--color-main-text);
	word-break: break-word;
}

.team-hero__actions {
	display: flex;
	align-items: flex-start;
	justify-content: flex-end;
}

.members-panel {
	margin-top: 20px;
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: 22px;
	background: var(--color-main-background);
	box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
}

.members-panel__header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 14px;
	flex-wrap: wrap;
	margin-bottom: 16px;
}

.members-panel__title {
	margin: 0;
	font-size: 20px;
}

.members-panel__subtitle {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}

.members-panel__view-badge {
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

.members-grid-panel,
.members-list-panel {
	padding: 8px;
	border-radius: 18px;
	background: var(--color-background-hover);
}

.members-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 14px;
	padding: 0;
	margin: 0;
	list-style: none;
}

.members-grid__item {
	min-width: 0;
}

.member-card {
	display: flex;
	align-items: center;
	gap: 14px;
	height: 100%;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: 18px;
	background: var(--color-main-background);
	box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
	transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.member-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12);
}

.member-card__body {
	min-width: 0;
}

.member-card__name {
	font-size: 14px;
	font-weight: 700;
	color: var(--color-main-text);
	word-break: break-word;
}

.member-card__user {
	margin-top: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	word-break: break-word;
}

.members-list {
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
.teams-empty-state {
	min-height: calc(100vh - var(--header-height) - 80px);
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 32px;
}

.teams-empty-card {
	width: min(760px, 100%);
	padding: 36px;
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 16px rgba(0, 0, 0, 0.08);
	text-align: center;
}

.teams-empty-image {
	width: 150px;
	margin-bottom: 16px;
	opacity: 0.95;
}

.teams-empty-card h2 {
	margin: 0 0 8px;
	font-size: 24px;
	font-weight: 700;
	color: var(--color-main-text);
}

.teams-empty-description {
	max-width: 560px;
	margin: 0 auto 24px;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.teams-empty-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin: 24px 0;
}

.teams-empty-item {
	padding: 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	text-align: left;
}

.teams-empty-item strong {
	display: block;
	margin-bottom: 6px;
	color: var(--color-main-text);
	font-size: 15px;
}

.teams-empty-item span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	line-height: 1.4;
}

.teams-empty-actions {
	display: flex;
	justify-content: center;
	gap: 12px;
	flex-wrap: wrap;
	margin-top: 20px;
}

@media (max-width: 960px) {
	.team-hero {
		flex-direction: column;
	}

	.team-hero__meta {
		grid-template-columns: 1fr;
	}

	.team-hero__actions {
		justify-content: flex-start;
	}
}

@media (max-width: 700px) {
	.team-details {
		padding: 12px;
	}

	.team-hero,
	.members-panel {
		padding: 18px;
		border-radius: 18px;
	}

	.team-hero__title {
		font-size: 24px;
	}

	.modal__content {
		margin: 24px 18px;
	}

	.teams-empty-state {
		align-items: flex-start;
		padding: 20px 12px;
	}

	.teams-empty-card {
		padding: 24px 16px;
	}

	.teams-empty-grid {
		grid-template-columns: 1fr;
	}

	.teams-empty-item {
		text-align: center;
	}
}
</style>
