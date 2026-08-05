<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input
							v-model="query"
							type="text"
							:placeholder="t('employees', 'Search teams...')">
					</div>
					<div class="filters-container">
						<NcButton
							class="filter-icon-button"
							type="tertiary"
							:title="t('employees', 'Filters')"
							@click.stop="toggleFilters">
							<template #icon>
								<FilterVariant :size="20" />
							</template>
							{{ t('employees') }}
							<span v-if="hideEmpty" class="filter-badge">1</span>
						</NcButton>

						<div v-if="showFilters" class="filter-dropdown" @click.stop>
							<div class="filter-section">
								<p class="filter-section-label">
									{{ t('employees', 'Sort') }}
								</p>
								<select v-model="sortOrder">
									<option value="asc">
										A-Z
									</option>
									<option value="desc">
										Z-A
									</option>
								</select>
							</div>

							<hr class="filter-divider">

							<div class="filter-section">
								<label>
									<input v-model="hideEmpty" type="checkbox">
									{{ t('employees', 'Hide empty') }}
								</label>
							</div>
						</div>
					</div>
					<div class="button-container">
						<NcActions
							:open="button"
							@click="toggle">
							<template #icon>
								<Cog :size="20" />
							</template>

							<NcActionButton @click="AgregarNew()">
								<template #icon>
									<AccountMultiplePlusOutline :size="20" />
								</template>
								{{ t('employees', 'Add new team') }}
							</NcActionButton>

							<NcActionButton @click="Exportar()">
								<template #icon>
									<DatabaseExport :size="20" />
								</template>
								{{ t('employees', 'Export list / template') }}
							</NcActionButton>

							<NcActionSeparator />

							<NcActionButton :disabled="true"
								@click="$refs.file.click()">
								<template #icon>
									<Upload :size="20" />
								</template>
								{{ t('employees', 'Import from template') }}
							</NcActionButton>
						</NcActions>
					</div>
				</div>
			</div>
		</div>

		<VirtualList
			ref="scroller"
			class="contacts-list"
			data-key="id_team"
			:data-sources="filteredList"
			:data-component="TeamsListItem"
			:estimate-size="60"
			:extra-props="{ reloadBus }" />

		<input
			ref="file"
			type="file"
			style="display: none"
			accept=".xlsx"
			@change="importar()">

		<NcModal
			v-if="modal"
			ref="modalRef"
			:name="t('employees', 'Add new team')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<NcTextField
						:value.sync="nombre_equipo"
						:label="t('employees', 'Team name')" />
					<br>
					<NcSelect
						v-model="selected_user"
						:input-label="t('employees', 'Manager team')"
						:options="optionsGestor"
						:user-select="true" />
					<br>
					<NcButton
						class="center"
						:aria-label="t('employees', 'Save changes')"
						type="primary"
						@click="crearEquipo()">
						{{ t('employees', 'Save changes') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</AppContentList>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

// Iconos
import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
import AccountMultiplePlusOutline from 'vue-material-design-icons/AccountMultiplePlusOutline.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'

import {
	NcAppContentList as AppContentList,
	NcActions,
	NcActionButton,
	NcActionSeparator,
	NcModal,
	NcTextField,
	NcButton,
	NcSelect,
} from '@nextcloud/vue'
import TeamsListItem from './TeamsListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'

export default {
	name: 'TeamsFullList',

	components: {
		AppContentList,
		VirtualList,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		Cog,
		FilterVariant,
		Upload,
		DatabaseExport,
		AccountMultiplePlusOutline,
		NcModal,
		NcTextField,
		NcButton,
		NcSelect,
	},

	props: {
		list: { type: Array, required: true },
		contacts: { type: Array, required: true },
		searchQuery: { type: String, default: '' },
		reloadBus: { type: Object, required: true },
	},

	data() {
		return {
			query: '',
			modal: false,
			button: false,
			nombre_equipo: '',
			optionsGestor: [], // Usuarios para elegir jefe
			selected_user: null, // Usuario seleccionado como jefe
			TeamsListItem,
			sortOrder: 'asc',
			hideEmpty: false,
			showFilters: false,
		}
	},

	computed: {
		filteredList() {
			let Team = this.contacts.filter(item => this.matchSearch(item?.name ?? ''))

			if (this.hideEmpty) {
				Team = Team.filter(
					item => Number(item.employee_count) > 0,
				)
			}

			Team.sort((a, b) => {
				const firstName = a?.name ?? ''
				const secondName = b?.name ?? ''

				if (this.sortOrder === 'asc') {
					return firstName.localeCompare(secondName)
				}

				return secondName.localeCompare(firstName)
			})

			return Team
		},
	},

	watch: {
		modal(newVal, oldVal) {
			// Si abres/cerras, podrías recargar data auxiliar si hiciera falta
			if (newVal !== oldVal && !newVal) {
				// Al cerrar, limpia selección
				this.nombre_equipo = ''
				this.selected_user = null
			}
		},
	},

	mounted() {
		this.query = this.searchQuery
		this._onClickOutside = (event) => {
			const wrap = this.$el.querySelector('.filters-container')
			if (wrap && !wrap.contains(event.target)) {
				this.showFilters = false
			}
		}
		document.addEventListener('click', this._onClickOutside)
	},

	beforeDestroy() {
		document.removeEventListener('click', this._onClickOutside)
	},

	methods: {
		// Exponer t en plantilla
		t,

		matchSearch(name) {
			const q = this.query.trim().toLowerCase()
			if (!q) return true
			return String(name).toLowerCase().includes(q)
		},

		async cargarUsuariosParaJefe() {
			// reutilizamos el endpoint global de Settings para traer Users
			const resp = await axios.get(generateUrl('/apps/employees/GetConfigurations'))
			this.optionsGestor = resp.data?.Users ?? []
		},

		async Exportar() {
			this.toggle()
			try {
				const { data } = await axios.get(
					generateUrl('/apps/employees/ExportListTeams'),
					{ responseType: 'blob' },
				)
				const url = URL.createObjectURL(new Blob([data], { type: 'application/vnd.ms-excel' }))
				const link = document.createElement('a')
				link.href = url
				link.setAttribute('download', 'Team.xlsx')
				document.body.appendChild(link)
				link.click()
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async importar() {
			this.toggle()
			const formData = new FormData()
			formData.append('equipofileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(
					generateUrl('/apps/employees/ImportListTeams'),
					formData,
					{ headers: { 'Content-Type': 'multipart/form-data' } },
				)
				this.$root.$emit('getall')
				this.$root.$emit('reload')
				this.$root.$emit('send-data-team', {})
				showSuccess(t('employees', 'Se actualizo la base de datos exitosamente'))
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		async AgregarNew() {
			this.toggle()
			try {
				await this.cargarUsuariosParaJefe()
				this.modal = true
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		closeModal() {
			this.modal = false
		},

		toggle() {
			this.button = !this.button
		},

		toggleFilters() {
			this.showFilters = !this.showFilters
		},

		async crearEquipo() {
			if (!this.nombre_equipo) {
				showError(t('employees', 'Por favor, complete los campos requeridos'))
				return
			}
			if (!this.selected_user || !this.selected_user.id) {
				showError(t('employees', 'Seleccione un jefe de equipo'))
				return
			}

			try {
				await axios.post(generateUrl('/apps/employees/crearEquipo'), {
					name: this.nombre_equipo,
					jefe: this.selected_user.id,
				})
				showSuccess(t('employees', 'Equipo creado exitosamente'))
				this.$root.$emit('reload')
				this.nombre_equipo = ''
				this.selected_user = null
				this.modal = false
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.container-search {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    grid-template-areas: "input filters button";
    align-items: center;
    gap: 6px 4px;
}
.filters-container {
    position: relative;
    display: inline-flex;
    align-items: center;
    grid-area: filters;
    margin: 0;
    overflow: visible;
}

.filter-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 18px;
	height: 18px;
	padding: 0 5px;
	margin-left: 4px;
	border-radius: 999px;
	background-color: var(--color-primary);
	color: #fff;
	font-size: 11px;
	font-weight: 600;
}

.filter-dropdown {
	position: absolute;
	top: calc(100% + 6px);
	right: 0;
	z-index: 100000;
	width: 190px;
	box-sizing: border-box;
	padding: 6px 0;
	overflow: hidden;
	border: 1px solid rgba(0, 0, 0, 0.28);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.filter-section {
	display: flex;
	flex-direction: column;
	gap: 4px;
	box-sizing: border-box;
	width: 100%;
	padding: 6px 10px;
}

.filter-section-label {
	margin: 0 0 4px;
	color: var(--color-text-maxcontrast);
	font-size: 10px;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.filter-section select {
	width: 100%;
	height: 28px;
	box-sizing: border-box;
	padding: 1px 22px 1px 7px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 12px;
}

.filter-section label {
	display: inline-flex;
	align-items: center;
	min-height: 26px;
	gap: 5px;
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1;
}

.filter-section input[type='checkbox'] {
	width: 13px;
	height: 13px;
	margin: 0;
}

.filter-divider {
	margin: 2px 0;
	border: none;
	border-top: 1px solid var(--color-border);
}

// Make virtual scroller scrollable
.contacts-list {
	max-height: calc(100vh - var(--header-height) - 48px);
	overflow: auto;
}

// Add empty header to contacts-list that solves overlapping of contacts with app-navigation-toogle
.contacts-list__header {
	min-height: 48px;
}

// Search field
.search-contacts-field {
	padding: 5px 10px 5px 50px;
	margin-top: 4px;

	> input {
		width: 100%;
	}
}

.content-list {
	overflow-y: auto;
	padding: 0 4px;
}

.input-container {
	grid-area: input;
}
.input-container input {
	width: 100%;
}
.button-container {
	grid-area: button;
}
.button-container button {
	width: 100%;
}

.modal__content {
	margin: 50px;
}
.modal__content h2 {
	text-align: center;
}
.form-group {
	margin: calc(var(--default-grid-baseline) * 4) 0;
	display: flex;
	flex-direction: column;
	align-items: flex-start;
}

.filter-icon-button {
	min-width: unset !important;
	padding-left: 4px !important;
	padding-right: 4px !important;
}
</style>
