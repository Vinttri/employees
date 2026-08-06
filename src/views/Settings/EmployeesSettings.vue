<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div class="employees-settings">
		<!-- Loading -->
		<div v-if="loading">
			<div class="center-screen">
				<NcLoadingIcon :size="64" appearance="dark" name="Loading on light background" />
			</div>
		</div>

		<!-- Main -->
		<div v-else id="admin">
			<div class="stats-grid">
				<div class="stat-card">
					<div class="stat-card__icon">
						<AccountGroup :size="22" />
					</div>
					<div>
						<span class="stat-card__label">{{ t('employees', 'Active employees') }}</span>
						<strong>{{ Empleados.length }}</strong>
					</div>
				</div>
				<div class="stat-card">
					<div class="stat-card__icon">
						<AccountOff :size="22" />
					</div>
					<div>
						<span class="stat-card__label">{{ t('employees', 'Deactivated employees') }}</span>
						<strong>{{ Desactivados.length }}</strong>
					</div>
				</div>
				<div class="stat-card">
					<div class="stat-card__icon">
						<AccountPlus :size="22" />
					</div>
					<div>
						<span class="stat-card__label">{{ t('employees', 'Users without employee record') }}</span>
						<strong>{{ Usuarios.length }}</strong>
					</div>
				</div>
			</div>

			<section class="directory-sync-card">
				<div class="directory-sync-card__content">
					<div>
						<h3>{{ t('employees', 'Nextcloud directory synchronization') }}</h3>
						<p>{{ t('employees', 'New users, Contacts organization data and Teams/Collectives are added automatically. Local edits are never overwritten, and deleted synchronized records stay deleted.') }}</p>
					</div>
					<NcButton type="primary"
						:disabled="syncingDirectory"
						@click="syncDirectoryNow">
						{{ syncingDirectory ? t('employees', 'Synchronizing…') : t('employees', 'Synchronize now') }}
					</NcButton>
				</div>
				<div v-if="directorySyncStatus" class="directory-sync-card__status">
					<span>{{ t('employees', 'Automatic synchronization: every 15 minutes') }}</span>
					<span v-if="directorySyncStatus.last_run">
						{{ t('employees', 'Last synchronization: {date}', { date: formatSyncDate(directorySyncStatus.last_run) }) }}
					</span>
					<span v-if="directorySyncStatus.last_result">
						{{ t('employees', 'Created: {count}; requires completion: {incomplete}', {
							count: syncCreatedCount(directorySyncStatus.last_result),
							incomplete: (directorySyncStatus.last_result.incomplete || []).length,
						}) }}
					</span>
				</div>
			</section>

			<VueTabs>
				<!-- Active employees -->
				<VTab :title="t('employees', 'Active employees')">
					<div class="tab-toolbar">
						<div>
							<h3>{{ t('employees', 'Active employees') }}</h3>
							<p>
								{{ t('employees', 'Open the action menu of an employee to edit module permissions.') }}
							</p>
						</div>

						<NcTextField class="tab-search"
							:value.sync="activeSearch"
							:label="t('employees', 'Search active employees')" />
					</div>

					<div v-if="filteredEmpleados.length > 0" class="container list-container">
						<table class="grid Employee-table">
							<tr>
								<th class="header__cell header__cell--avatar">
									&nbsp;
								</th>
								<th>{{ t('employees', 'Name') }}</th>
								<th class="employee-id-column">
									{{ t('employees', 'User') }}
								</th>
								<th>{{ t('employees', 'Options') }}</th>
							</tr>
							<tr v-for="item in filteredEmpleados" :key="getEmployeeUid(item)" v-bind="$attrs">
								<td class="row__cell row__cell--avatar">
									<NcAvatar :user="getEmployeeUid(item)"
										:display-name="getEmpleadoDisplayName(item)"
										:show-user-status-compact="false"
										:show-user-status="false" />
								</td>
								<td>
									<div class="employee-main-cell">
										<strong>{{ getEmpleadoDisplayName(item) }}</strong>
										<span class="status-badge status-badge--active">
											{{ t('employees', 'Employee record enabled') }}
										</span>
									</div>
								</td>
								<td class="employee-id-column">
									<code>{{ getEmployeeUid(item) }}</code>
								</td>
								<td>
									<NcActions>
										<NcActionButton close-after-click @click="openPermisosDialog(item)">
											<template #icon>
												<AccountGroup :size="20" />
											</template>
											{{ t('employees', 'View permissions') }}
										</NcActionButton>

										<NcActionButton close-after-click
											@click="DeactiveUserDialog(getEmpleadoIndex(item), getEmpleadoDisplayName(item))">
											<template #icon>
												<AccountOff :size="20" />
											</template>
											{{ t('employees', 'Disable employee') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</table>
					</div>
					<div v-else class="container empty-container">
						<NcEmptyContent :name="activeSearch ? t('employees', 'No employees match the search') : t('employees', 'No users yet')">
							<template #icon>
								<AccountOff :size="20" />
							</template>
						</NcEmptyContent>
					</div>
				</VTab>

				<!-- Deactivated employees -->
				<VTab :title="t('employees', 'Deactivated employees')">
					<div class="tab-toolbar">
						<div>
							<h3>{{ t('employees', 'Deactivated employees') }}</h3>
							<p>
								{{ t('employees', 'Reactivate or delete employee records that are no longer active.') }}
							</p>
						</div>

						<NcTextField class="tab-search"
							:value.sync="inactiveSearch"
							:label="t('employees', 'Search deactivated employees')" />
					</div>

					<div v-if="filteredDesactivados.length > 0" class="container list-container">
						<table class="grid Employee-table">
							<tr>
								<th class="header__cell header__cell--avatar">
									&nbsp;
								</th>
								<th>{{ t('employees', 'Name') }}</th>
								<th class="employee-id-column">
									{{ t('employees', 'User') }}
								</th>
								<th>{{ t('employees', 'Options') }}</th>
							</tr>
							<tr v-for="item in filteredDesactivados"
								:key="getEmployeeUid(item)"
								v-bind="$attrs">
								<td class="row__cell row__cell--avatar">
									<NcAvatar :user="getEmployeeUid(item)"
										:display-name="getEmpleadoDisplayName(item)"
										:show-user-status-compact="false"
										:show-user-status="false" />
								</td>
								<td>
									<div class="employee-main-cell">
										<strong>{{ getEmpleadoDisplayName(item) }}</strong>
										<span class="status-badge status-badge--disabled">
											{{ t('employees', 'Employee record disabled') }}
										</span>
									</div>
								</td>
								<td class="employee-id-column">
									<code>{{ getEmployeeUid(item) }}</code>
								</td>
								<td>
									<NcActions>
										<NcActionButton close-after-click @click="ActivarUsuario(getDesactivadoIndex(item))">
											<template #icon>
												<AccountPlus :size="20" />
											</template>
											{{ t('employees', 'Activate') }}
										</NcActionButton>
										<NcActionButton close-after-click @click="EliminarUserDialog(getDesactivadoIndex(item), getEmpleadoDisplayName(item))">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('employees', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</table>
					</div>
					<div v-else class="container empty-container">
						<NcEmptyContent :name="inactiveSearch ? t('employees', 'No deactivated employees match the search') : t('employees', 'No users yet')">
							<template #icon>
								<AccountOff :size="20" />
							</template>
						</NcEmptyContent>
					</div>
				</VTab>

				<!-- Users without employee record -->
				<VTab :title="t('employees', 'Users without employee record')">
					<div class="tab-toolbar">
						<div>
							<h3>{{ t('employees', 'Users without employee record') }}</h3>
							<p>{{ t('employees', 'New Nextcloud users are synchronized automatically; this list is also available for an immediate manual import.') }}</p>
						</div>

						<NcTextField class="tab-search"
							:value.sync="pendingSearch"
							:label="t('employees', 'Search pending users')" />
					</div>

					<NcNoteCard type="info" class="directory-help">
						{{ t('employees', 'Synchronization reads Nextcloud and Contacts without changing them. Existing Employees data and manual edits are preserved.') }}
					</NcNoteCard>

					<div class="directory-actions">
						<NcButton @click="previewContactsOrganization()">
							{{ t('employees', 'Preview Contacts organization data') }}
						</NcButton>
						<NcButton type="primary"
							:disabled="selectedPendingUsers.length === 0 || loadingEmployees"
							@click="createSelectedEmployees">
							{{ t('employees', 'Import selected from Nextcloud') }} ({{ selectedPendingUsers.length }})
						</NcButton>
					</div>

					<div v-if="loadingEmployees" class="loader-settings">
						<NcLoadingIcon :size="70" />
					</div>
					<div v-else>
						<div v-if="filteredUsuarios.length > 0" class="container list-container">
							<table class="grid Employee-table">
								<tr>
									<th>{{ t('employees', 'Select') }}</th>
									<th class="header__cell header__cell--avatar">
										&nbsp;
									</th>
									<th>{{ t('employees', 'Name') }}</th>
									<th class="employee-id-column">
										{{ t('employees', 'User') }}
									</th>
									<th>{{ t('employees', 'Options') }}</th>
								</tr>
								<tr v-for="item in filteredUsuarios" :key="item.uid" v-bind="$attrs">
									<td>
										<NcCheckboxRadioSwitch type="checkbox"
											:checked="selectedPendingUsers.includes(item.uid)"
											@update:checked="togglePendingUser(item.uid, $event)" />
									</td>
									<td class="row__cell row__cell--avatar">
										<NcAvatar :user="item.uid"
											:display-name="getUsuarioDisplayName(item)"
											:show-user-status-compact="false"
											:show-user-status="false" />
									</td>
									<td>
										<div class="employee-main-cell">
											<strong>{{ getUsuarioDisplayName(item) }}</strong>
											<span class="status-badge status-badge--pending">
												{{ t('employees', 'Pending employee record') }}
											</span>
										</div>
									</td>
									<td class="employee-id-column">
										<code>{{ item.uid }}</code>
									</td>
									<td>
										<div class="row-actions">
											<NcButton @click="ActivarUser(getUsuarioIndex(item))">
												{{ t('employees', 'Create employee record') }}
											</NcButton>
											<NcButton type="primary" @click="previewContactsOrganization(item.uid)">
												{{ t('employees', 'Create and fill from Contacts') }}
											</NcButton>
										</div>
									</td>
								</tr>
							</table>
						</div>
						<div v-else class="container empty-container">
							<NcEmptyContent :name="pendingSearch ? t('employees', 'No pending users match the search') : t('employees', 'No pending users')">
								<template #icon>
									<AccountPlus :size="20" />
								</template>
							</NcEmptyContent>
						</div>
					</div>
				</VTab>
			</VueTabs>
		</div>

		<!-- Dialog: permissions -->
		<NcModal v-if="showPermisosDialog"
			class="permisos-dialog-modal"
			size="large"
			:name="t('employees', 'User permissions')"
			@close="showPermisosDialog = false">
			<div class="permisos-dialog">
				<div class="permisos-user-card">
					<NcAvatar :user="selectedPermisosUser.uid"
						:display-name="selectedPermisosUser.displayname"
						:show-user-status-compact="false"
						:show-user-status="false" />
					<div>
						<strong>{{ selectedPermisosUser.displayname }}</strong>
						<span>{{ selectedPermisosUser.uid }}</span>
					</div>
				</div>

				<NcNoteCard type="info" class="permisos-help">
					{{ t('employees', 'Assign module permissions using controlled Nextcloud groups.') }}
				</NcNoteCard>

				<div v-if="loadingPermisos" class="permisos-loader">
					<NcLoadingIcon :size="44" />
				</div>

				<div v-else class="permisos-groups">
					<NcNoteCard v-if="hasAdminPermissionSelected" type="warning" class="permisos-admin-note">
						{{ t('employees', 'Administrator permission already includes all purchase permissions Additional purchase groups are not required.') }}
					</NcNoteCard>

					<NcCheckboxRadioSwitch v-for="group in permisosGruposDecorados"
						:key="group.id"
						:checked="selectedPermisosGroups.includes(group.id)"
						:disabled="group.disabled"
						type="switch"
						@update:checked="togglePermisoGroup(group.id, $event)">
						<span class="permission-option" :class="{ 'permission-option--disabled': group.disabled }">
							<strong>{{ group.label }}</strong>
							<small>{{ group.id }}</small>
							<span class="permission-description">
								{{ group.description }}
							</span>
							<em v-if="group.restriction">
								{{ group.restriction }}
							</em>
						</span>
					</NcCheckboxRadioSwitch>

					<NcEmptyContent v-if="permisosGrupos.length === 0"
						:name="t('employees', 'No permission groups found')">
						<template #icon>
							<AccountGroup :size="20" />
						</template>
					</NcEmptyContent>
				</div>

				<div class="permisos-actions">
					<NcButton @click="showPermisosDialog = false">
						{{ t('employees', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="loadingPermisos || !selectedPermisosUid"
						@click="savePermisosUsuario">
						{{ t('employees', 'Save permissions') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<NcModal v-if="showContactsPreview"
			size="large"
			:name="t('employees', 'Contacts organization preview')"
			@close="showContactsPreview = false">
			<div class="contacts-preview-dialog">
				<h2>{{ t('employees', 'Contacts organization preview') }}</h2>
				<p>{{ t('employees', 'Review the values before creating or updating employee records. This operation never changes Contacts.') }}</p>
				<div v-if="loadingContacts" class="permisos-loader">
					<NcLoadingIcon :size="44" />
				</div>
				<table v-else class="grid contacts-preview-table">
					<tr>
						<th>{{ t('employees', 'Select') }}</th>
						<th>{{ t('employees', 'Employee') }}</th>
						<th>{{ t('employees', 'Department') }}</th>
						<th>{{ t('employees', 'Position') }}</th>
						<th>{{ t('employees', 'Team') }}</th>
						<th>{{ t('employees', 'Manager') }}</th>
					</tr>
					<tr v-for="contact in contactsPreview" :key="contact.uid">
						<td>
							<NcCheckboxRadioSwitch type="checkbox"
								:checked="selectedContacts.includes(contact.uid)"
								:disabled="!contact.importable"
								@update:checked="toggleContact(contact.uid, $event)" />
						</td>
						<td><strong>{{ contact.display_name }}</strong><br><code>{{ contact.uid }}</code></td>
						<td>{{ contact.department || '—' }}</td>
						<td>{{ contact.position || '—' }}</td>
						<td>{{ contact.team || '—' }}</td>
						<td>{{ contact.manager_name || '—' }}</td>
					</tr>
				</table>
				<div class="permisos-actions">
					<NcButton @click="showContactsPreview = false">
						{{ t('employees', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="selectedContacts.length === 0 || loadingContacts"
						@click="importSelectedContacts">
						{{ t('employees', 'Create and fill selected employees') }} ({{ selectedContacts.length }})
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- Dialog: deactivate -->
		<NcDialog :open.sync="showDeactiveUserDialog"
			:name="t('employees', 'Confirmation')"
			:message="t('employees', 'Are you sure you want to disable the employee record for {name}?', { name: selected.name || '' })"
			:buttons="buttons" />

		<!-- Dialog: delete -->
		<NcDialog :open.sync="showEliminarUserDialog"
			:name="t('employees', 'Are you sure you want to delete?')"
			:message="t('employees', 'This action will delete all employee information for {name}.', { name: selected.name || '' })"
			:buttons="ButtonsEliminarUser" />
	</div>
</template>

<script>
// Icons
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import AccountOff from 'vue-material-design-icons/AccountOff.vue'
import AccountPlus from 'vue-material-design-icons/AccountPlus.vue'

// Components & utils
import {
	NcActions,
	NcActionButton,
	NcLoadingIcon,
	NcAvatar,
	NcDialog,
	NcEmptyContent,
	NcButton,
	NcCheckboxRadioSwitch,
	NcNoteCard,
	NcTextField,
	NcModal,
} from '@nextcloud/vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

const COMPRAS_GROUPS = [
	'purchases_solicitantes',
	'purchases_autorizadores',
	'purchases_admin',
	'purchases_contabilidad',
]

const COMPRAS_ADMIN_GROUP = 'purchases_admin'
const GLOBAL_ADMIN_GROUP = 'admin'

export default {
	name: 'EmployeesSettings',
	components: {
		NcAvatar,
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		AccountGroup,
		Delete,
		VueTabs,
		VTab,
		AccountOff,
		NcDialog,
		AccountPlus,
		NcEmptyContent,
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcTextField,
		NcModal,
	},

	data() {
		return {
			showDeactiveUserDialog: false,
			showEliminarUserDialog: false,
			showPermisosDialog: false,
			selected: [],
			selectedPermisosUser: {
				uid: '',
				displayname: '',
			},
			loading: true,
			Empleados: [],
			Usuarios: [],
			Desactivados: [],
			map: {},
			selectArray: [],
			ButtonsEliminarUser: [
				{
					label: t('employees', 'OK'),
					type: 'primary',
					callback: () => { this.EliminarUser(this.selected.index) },
				},
			],
			buttons: [
				{
					label: t('employees', 'OK'),
					type: 'primary',
					callback: () => { this.DeactiveUser(this.selected.index) },
				},
			],
			loadingEmployees: false,
			permisosGrupos: [],
			selectedPermisosUid: '',
			selectedPermisosGroups: [],
			loadingPermisos: false,
			activeSearch: '',
			inactiveSearch: '',
			pendingSearch: '',
			selectedPendingUsers: [],
			showContactsPreview: false,
			contactsPreview: [],
			selectedContacts: [],
			loadingContacts: false,
			directorySyncStatus: null,
			syncingDirectory: false,
		}
	},

	computed: {
		filteredEmpleados() {
			return this.filterUsers(this.Empleados, this.activeSearch, this.getEmpleadoDisplayName)
		},

		filteredDesactivados() {
			return this.filterUsers(this.Desactivados, this.inactiveSearch, this.getEmpleadoDisplayName)
		},

		filteredUsuarios() {
			return this.filterUsers(this.Usuarios, this.pendingSearch, this.getUsuarioDisplayName)
		},
		permisosGruposDecorados() {
			return this.permisosGrupos.map((group) => {
				return {
					...group,
					description: group.description || this.getPermisoDescription(group),
					restriction: this.getPermisoRestriction(group),
					disabled: this.isPermissionDisabled(group.id),
				}
			})
		},

		hasAdminPermissionSelected() {
			return this.selectedPermisosGroups.includes(GLOBAL_ADMIN_GROUP)
				|| this.selectedPermisosGroups.includes(COMPRAS_ADMIN_GROUP)
		},
	},

	async mounted() {
		this.getall()
		this.loadDirectorySyncStatus()
	},

	methods: {
		t,
		async loadDirectorySyncStatus() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/directory/status'))
				const payload = this.getPayload(response)
				this.directorySyncStatus = payload.data || payload
			} catch (error) {
				showError(t('employees', 'Could not load directory synchronization status: {error}', { error: String(error) }))
			}
		},
		async syncDirectoryNow() {
			this.syncingDirectory = true
			try {
				const response = await axios.post(generateUrl('/apps/employees/directory/sync'))
				const payload = this.getPayload(response)
				const result = payload.data || payload
				showSuccess(t('employees', 'Directory synchronization completed. Created: {count}; requires completion: {incomplete}.', {
					count: this.syncCreatedCount(result),
					incomplete: (result.incomplete || []).length,
				}))
				await Promise.all([this.getall(), this.loadDirectorySyncStatus()])
			} catch (error) {
				showError(t('employees', 'Directory synchronization failed: {error}', { error: String(error) }))
			} finally {
				this.syncingDirectory = false
			}
		},
		syncCreatedCount(result) {
			return Object.values(result?.created || {}).reduce((sum, value) => sum + Number(value || 0), 0)
		},
		formatSyncDate(value) {
			const date = new Date(value)
			return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
		},
		// Fetch all lists
		async getall() {
			try {
				await axios.get(generateUrl('/apps/employees/GetUserLists'))
					.then(
						(response) => {
							this.Usuarios = []
							this.Empleados = response?.data?.ocs?.data.Empleados || []
							this.Desactivados = response?.data?.ocs?.data.Desactivados || []

							this.map = {}

							this.Empleados.forEach(empleado => {
								this.map[empleado.id_user] = true
							})

							this.Desactivados.forEach(empleado => {
								this.map[empleado.id_user] = true
							})

							this.Usuarios = (response?.data?.ocs?.data.Users || []).filter(user => !this.map[user.uid])

							if (this.permisosGrupos.length === 0) {
								this.loadPermisosGrupos()
							}

							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('employees', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		DeactiveUserDialog(index, name) {
			this.selected.index = index
			this.selected.name = name
			this.showDeactiveUserDialog = true
		},

		EliminarUserDialog(index, name) {
			this.selected.index = index
			this.selected.name = name
			this.showEliminarUserDialog = true
		},

		async ActivarUsuario(index) {
			try {
				await axios.post(generateUrl('/apps/employees/ActivarUsuario'), {
					id_employees: this.Desactivados[index].id_employees,
				}).then(
					() => { this.getall() },
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('employees', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async EliminarUser(index) {
			this.showEliminarUserDialog = false
			try {
				await axios.post(generateUrl('/apps/employees/EliminarEmpleado'), {
					id_employees: this.Desactivados[index].id_employees,
					id_user: this.Desactivados[index].id_user,
				}).then(
					() => { this.getall() },
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('employees', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async DeactiveUser(index) {
			try {
				await axios.post(generateUrl('/apps/employees/DesactivarEmpleado'), {
					id_employees: this.Empleados[index].id_employees,
				}).then(
					() => { this.getall() },
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('employees', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async ActivarUser(index) {
			const user = this.Usuarios[index]
			if (!user) return
			this.selectedPendingUsers = [user.uid]
			await this.createSelectedEmployees()
		},

		togglePendingUser(uid, checked) {
			const selected = new Set(this.selectedPendingUsers)
			if (checked) selected.add(uid)
			else selected.delete(uid)
			this.selectedPendingUsers = [...selected]
		},

		async createSelectedEmployees() {
			if (this.selectedPendingUsers.length === 0) return
			this.loadingEmployees = true
			try {
				const response = await axios.post(generateUrl('/apps/employees/directory/users/import'), {
					uids: this.selectedPendingUsers,
				})
				const payload = this.getPayload(response)
				const result = payload.data || payload
				if ((result.failed || 0) > 0) {
					showError(t('employees', 'Created {created} employee records; {failed} failed.', {
						created: result.created || 0,
						failed: result.failed || 0,
					}))
				} else {
					showSuccess(t('employees', 'Created {count} employee records from Nextcloud.', {
						count: result.created || 0,
					}))
				}
				this.selectedPendingUsers = []
				await this.getall()
			} catch (err) {
				showError(t('employees', 'Could not create employee records: {error}', { error: String(err) }))
			} finally {
				this.loadingEmployees = false
			}
		},

		async previewContactsOrganization(uid = '') {
			this.showContactsPreview = true
			this.loadingContacts = true
			this.selectedContacts = []
			try {
				const response = await axios.get(generateUrl('/apps/employees/directory/contacts/preview'))
				const payload = this.getPayload(response)
				this.contactsPreview = payload?.data?.contacts || payload?.contacts || []
				if (uid) {
					const contact = this.contactsPreview.find(item => item.uid === uid && item.importable)
					if (contact) {
						this.selectedContacts = [uid]
					} else {
						showError(t('employees', 'No importable Contacts entry matches user {uid}.', { uid }))
					}
				}
			} catch (err) {
				this.contactsPreview = []
				showError(t('employees', 'Could not load Contacts organization data: {error}', { error: String(err) }))
			} finally {
				this.loadingContacts = false
			}
		},

		toggleContact(uid, checked) {
			const selected = new Set(this.selectedContacts)
			if (checked) selected.add(uid)
			else selected.delete(uid)
			this.selectedContacts = [...selected]
		},

		async importSelectedContacts() {
			if (this.selectedContacts.length === 0) return
			this.loadingContacts = true
			try {
				const response = await axios.post(generateUrl('/apps/employees/directory/contacts/import'), {
					uids: this.selectedContacts,
				})
				const payload = this.getPayload(response)
				const result = payload.data || payload
				if ((result.failed || 0) > 0) {
					showError(t('employees', 'Imported {imported} Contacts entries; {failed} failed.', {
						imported: result.imported || 0,
						failed: result.failed || 0,
					}))
				} else {
					showSuccess(t('employees', 'Imported {count} employees and organization records from Contacts.', {
						count: result.imported || 0,
					}))
				}
				this.showContactsPreview = false
				this.selectedContacts = []
				await this.getall()
			} catch (err) {
				showError(t('employees', 'Could not import Contacts organization data: {error}', { error: String(err) }))
			} finally {
				this.loadingContacts = false
			}
		},

		async openPermisosDialog(user) {
			const uid = this.getEmployeeUid(user)

			if (!uid) {
				showError(t('employees', 'User id was not found'))
				return
			}

			this.selectedPermisosUser = {
				uid,
				displayname: this.getEmpleadoDisplayName(user),
			}
			this.selectedPermisosUid = uid
			this.selectedPermisosGroups = []
			this.showPermisosDialog = true

			await this.loadPermisosGrupos()

			await this.loadPermisosUsuario()
		},

		async loadPermisosGrupos() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/permisos/grupos'))
				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || 'No se pudieron cargar los grupos.')
				}

				this.permisosGrupos = payload.data || []
			} catch (err) {
				showError(t('employees', 'Error loading permission groups: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		async loadPermisosUsuario() {
			if (!this.selectedPermisosUid) {
				this.selectedPermisosGroups = []
				return
			}

			this.loadingPermisos = true

			try {
				const response = await axios.get(
					generateUrl('/apps/employees/permisos/usuario/{uid}', {
						uid: this.selectedPermisosUid,
					}),
				)

				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || 'No se pudieron cargar los permisos.')
				}

				this.selectedPermisosGroups = this.normalizeSelectedPermisosGroups(payload.data.groups || [])
			} catch (err) {
				showError(t('employees', 'Error loading user permissions: {error}', { error: String(err) }))
				console.error(err)
			} finally {
				this.loadingPermisos = false
			}
		},

		togglePermisoGroup(groupId, checked) {
			const shouldEnable = Boolean(checked)

			if (shouldEnable && this.isPermissionDisabled(groupId)) {
				return
			}

			let groups = [...this.selectedPermisosGroups]

			if (!shouldEnable) {
				groups = groups.filter(id => id !== groupId)
				this.selectedPermisosGroups = this.normalizeSelectedPermisosGroups(groups)
				return
			}

			groups.push(groupId)
			this.selectedPermisosGroups = this.normalizeSelectedPermisosGroups(groups)
		},

		async savePermisosUsuario() {
			if (!this.selectedPermisosUid) {
				showError(t('employees', 'Select a user first'))
				return
			}

			this.loadingPermisos = true

			try {
				const groups = this.normalizeSelectedPermisosGroups(this.selectedPermisosGroups)
				const response = await axios.post(
					generateUrl('/apps/employees/permisos/usuario/{uid}', {
						uid: this.selectedPermisosUid,
					}),
					{
						groups,
					},
				)

				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || 'No se pudieron guardar los permisos.')
				}

				this.selectedPermisosGroups = this.normalizeSelectedPermisosGroups(payload.data.groups || groups)
				this.showPermisosDialog = false
				showSuccess(t('employees', 'Permissions updated'))
			} catch (err) {
				showError(t('employees', 'Error saving permissions: {error}', { error: String(err) }))
				console.error(err)
			} finally {
				this.loadingPermisos = false
			}
		},

		getPayload(response) {
			return response?.data?.ocs?.data || response?.data
		},

		getEmployeeUid(user) {
			return user?.id_user || user?.uid || ''
		},

		getEmpleadoDisplayName(user) {
			return user?.displayname || user?.DisplayName || user?.name || user?.id_user || user?.uid || ''
		},

		getUsuarioDisplayName(user) {
			try {
				return JSON.parse(user.data)?.displayname?.value || user.displayname || user.uid
			} catch (e) {
				return user.displayname || user.uid
			}
		},

		filterUsers(users, search, displayNameGetter) {
			const query = String(search || '').trim().toLowerCase()

			if (!query) {
				return users
			}

			return users.filter((user) => {
				const uid = this.getEmployeeUid(user) || user?.uid || ''
				const displayName = displayNameGetter(user)

				return `${displayName} ${uid}`.toLowerCase().includes(query)
			})
		},

		getEmpleadoIndex(item) {
			const uid = this.getEmployeeUid(item)
			return this.Empleados.findIndex(empleado => this.getEmployeeUid(empleado) === uid)
		},

		getDesactivadoIndex(item) {
			const uid = this.getEmployeeUid(item)
			return this.Desactivados.findIndex(empleado => this.getEmployeeUid(empleado) === uid)
		},

		getUsuarioIndex(item) {
			return this.Usuarios.findIndex(user => user.uid === item.uid)
		},
		normalizeSelectedPermisosGroups(groups) {
			const normalized = [...new Set(groups.filter(Boolean))]

			if (normalized.includes(GLOBAL_ADMIN_GROUP)) {
				return normalized.filter((id) => {
					return id === GLOBAL_ADMIN_GROUP || !COMPRAS_GROUPS.includes(id)
				})
			}

			if (normalized.includes(COMPRAS_ADMIN_GROUP)) {
				return normalized.filter((id) => {
					return id === COMPRAS_ADMIN_GROUP || !COMPRAS_GROUPS.includes(id)
				})
			}

			return normalized
		},

		isPermissionDisabled(groupId) {
			if (this.selectedPermisosGroups.includes(GLOBAL_ADMIN_GROUP)) {
				return COMPRAS_GROUPS.includes(groupId)
			}

			if (this.selectedPermisosGroups.includes(COMPRAS_ADMIN_GROUP)) {
				return COMPRAS_GROUPS.includes(groupId) && groupId !== COMPRAS_ADMIN_GROUP
			}

			return false
		},

		getPermisoDescription(group) {
			const groupId = typeof group === 'string' ? group : group?.id
			const moduleName = typeof group === 'string' ? '' : group?.module
			const permissionName = typeof group === 'string' ? '' : group?.permission

			const descriptions = {
				admin: t('employees', 'Global Nextcloud administrator. This role already has full access and should be assigned only when strictly necessary.'),
				purchases_admin: t('employees', 'Full control of the purchases module: view all requests, create requests, select requester, approve, reject and process purchases.'),
				purchases_solicitantes: t('employees', 'Can access the purchases module, create own purchase requests, edit drafts, send them for approval and follow their own requests.'),
				purchases_autorizadores: t('employees', 'Can view purchase requests from all users and approve or reject requests pending approval.'),
				purchases_contabilidad: t('employees', 'Can view purchase requests from all users for accounting review and tracking. Cannot approve or reject requests.'),
				hr: t('employees', 'Can manage employee-related information in the employees module. This does not grant purchase approval permissions.'),
				clients_admin: t('employees', 'Can create, edit, delete, import and export customers.'),
				clients_view: t('employees', 'Can view customers without editing the customer catalog.'),
			}

			if (descriptions[groupId]) {
				return descriptions[groupId]
			}

			if (moduleName && permissionName) {
				return t(
					'employees',
					'Grants {permission} access to the {module} module.',
					{
						permission: permissionName,
						module: moduleName,
					},
				)
			}

			return t('employees', 'Controlled group used to grant access to a specific module or workflow.')
		},

		getPermisoRestriction(group) {
			const groupId = typeof group === 'string' ? group : group?.id
			const restricted = typeof group === 'string' ? false : Boolean(group?.restricted)

			const restrictions = {
				admin: t('employees', 'Do not combine with purchase groups unless there is a specific reason.'),
				purchases_admin: t('employees', 'Includes requester, approver and accounting purchase permissions. Other purchase groups will be ignored.'),
				purchases_solicitantes: t('employees', 'Does not allow viewing requests from other users.'),
				purchases_autorizadores: t('employees', 'Does not allow selecting another requester when creating a request.'),
				purchases_contabilidad: t('employees', 'Read-only for approvals: approval and rejection actions are hidden.'),
				hr: t('employees', 'Independent from purchase permissions.'),
				clients_admin: t('employees', 'Administrative customer permission. Assign only to users who should maintain the customer catalog.'),
				clients_view: t('employees', 'Read-only customer permission.'),
			}

			if (restrictions[groupId]) {
				return restrictions[groupId]
			}

			if (restricted) {
				return t('employees', 'Restricted permission. Assign only when necessary.')
			}

			return ''
		},
	},
}
</script>

<style>
.employees-settings {
	color: var(--color-main-text);
}

/* Board title */
.settings-header {
	display: flex;
	align-items: flex-start;
	gap: 14px;
	margin: 0 20px 8px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.settings-header__icon,
.stat-card__icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.settings-header__icon {
	width: 52px;
	height: 52px;
}

.settings-header__content {
	min-width: 0;
}

.section-label {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.board-title {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: bold;
}

.settings-description {
	max-width: 820px;
	margin: 8px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.4;
}

/* Centered loading */
.center-screen {
	display: flex;
	justify-content: center;
	align-items: center;
	text-align: center;
	min-height: 100vh;
	background: var(--color-main-background);
}

/* Container */
.container {
	padding-left: 20px;
	padding-right: 20px;
}

.stats-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(160px, 1fr));
	gap: 12px;
	padding: 8px 20px 16px;
}

.stat-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 1px 4px rgb(from var(--color-box-shadow) r g b / 0.04);
}

.stat-card__icon {
	width: 42px;
	height: 42px;
}

.stat-card__label {
	display: block;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 6px;
}

.stat-card strong {
	font-size: 28px;
	line-height: 1;
}

.tab-toolbar {
	display: flex;
	align-items: flex-end;
	justify-content: space-between;
	gap: 16px;
	padding: 16px 20px 8px;
}

.tab-toolbar h3 {
	margin: 0;
	font-size: 18px;
}

.tab-toolbar p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.4;
}

.tab-search {
	width: min(320px, 100%);
	flex: 0 0 min(320px, 100%);
}

.list-container {
	max-height: calc(80vh - 10rem);
	overflow-y: auto;
}

.empty-container {
	padding-top: 24px;
}

.contacts-preview-table {
	width: 100%;
	margin-top: 16px;
	border-collapse: collapse;
}

.contacts-preview-table th,
.contacts-preview-table td {
	padding: 10px;
	border-bottom: 1px solid var(--color-border);
	vertical-align: top;
	text-align: left;
}

.employees-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	border-radius: var(--border-radius-large);
	overflow: hidden;
	background: var(--color-main-background);
}

.employees-table th {
	position: sticky;
	top: 0;
	z-index: 1;
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-align: left;
	text-transform: uppercase;
}

.employees-table th,
.employees-table td {
	padding: 12px 14px;
	border-bottom: 1px solid var(--color-border);
	vertical-align: middle;
}

.employees-table tr:last-child td {
	border-bottom: 0;
}

.header__cell--avatar,
.row__cell--avatar {
	width: 56px;
}

.employee-main-cell {
	display: flex;
	flex-direction: column;
	gap: 3px;
}

.employee-main-cell span {
	width: fit-content;
}

.status-badge {
	display: inline-flex;
	align-items: center;
	min-height: 22px;
	padding: 2px 8px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 700;
}

.status-badge--active {
	background: var(--color-success);
	color: var(--color-success-text);
}

.status-badge--disabled {
	background: var(--color-warning);
	color: var(--color-warning-text);
}

.status-badge--pending {
	background: var(--color-primary-element-light, var(--color-background-hover));
	color: var(--color-primary-element);
}

.employee-id-column code {
	padding: 3px 6px;
	border-radius: var(--border-radius-small);
	background: var(--color-background-dark);
	font-size: 12px;
}
.directory-help {
	margin: 12px 0;
}

.directory-sync-card {
	margin: 0 0 20px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.directory-sync-card__content,
.directory-sync-card__status {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}

.directory-sync-card__content h3 {
	margin: 0 0 4px;
}

.directory-sync-card__content p {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.directory-sync-card__status {
	justify-content: flex-start;
	flex-wrap: wrap;
	margin-top: 14px;
	padding-top: 12px;
	border-top: 1px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.directory-actions,
.row-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.directory-actions {
	justify-content: flex-end;
	margin: 12px 0;
}

.contacts-preview-dialog {
	max-height: min(78vh, 820px);
	padding: 8px 12px;
	overflow: auto;
}

.contacts-preview-dialog h2 {
	margin: 0 0 8px;
}

.permisos-dialog-modal {
	--permissions-modal-width: min(980px, calc(100vw - 48px));
}

.permisos-dialog {
	max-height: min(78vh, 780px);
	padding: 4px 4px 0;
	overflow-y: auto;
}

.permisos-user-card {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 14px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.permisos-user-card div {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.permisos-user-card strong {
	color: var(--color-main-text);
	font-size: 16px;
}

.permisos-user-card span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	overflow-wrap: anywhere;
}

.permisos-help {
	margin: 0 0 18px;
}

.permisos-loader {
	display: flex;
	justify-content: center;
	padding: 28px 0;
}

.permisos-groups {
	padding: 0px 10px 0px 5px;
}

.permisos-admin-note {
	grid-column: 1 / -1;
	margin: 0 0 4px;
}

.permisos-groups .checkbox-radio-switch {
	align-items: flex-start;
	min-height: 100%;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.permisos-groups .checkbox-radio-switch:hover {
	background: var(--color-background-hover);
}

.permission-option {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 0;
	line-height: 1.35;
}

.permission-option strong {
	color: var(--color-main-text);
	font-size: 14px;
}

.permission-option small {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	overflow-wrap: anywhere;
}

.permission-description {
	margin-top: 2px;
	color: var(--color-main-text);
	font-size: 13px;
	line-height: 1.4;
}

.permission-option em {
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-style: normal;
	line-height: 1.35;
}

.permission-option--disabled {
	opacity: .55;
}

.permisos-actions {
	position: sticky;
	right: 0;
	bottom: 0;
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 22px;
	padding: 14px 0 4px;
	border-top: 1px solid var(--color-border);
	background: var(--color-main-background);
}
@media (max-width: 800px) {
	.settings-header,
	.tab-toolbar {
		align-items: stretch;
		flex-direction: column;
	}

	.stats-grid {
		grid-template-columns: 1fr;
	}

	.tab-search {
		flex-basis: auto;
		width: 100%;
	}

	.employee-id-column {
		display: none;
	}

	.employees-table th,
	.employees-table td {
		padding: 10px;
	}
	.permisos-dialog-modal {
		--permissions-modal-width: calc(100vw - 24px);
	}
	.permisos-groups {
		grid-template-columns: 1fr;
	}
	.permisos-dialog {
		width: calc(100vw - 40px);
		max-height: 78vh;
	}
	.directory-actions,
	.row-actions {
		align-items: stretch;
		flex-direction: column;
	}
	.contacts-preview-table {
		display: block;
		overflow-x: auto;
	}
}
</style>
