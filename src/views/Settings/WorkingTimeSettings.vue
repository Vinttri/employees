<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div class="settings-page">
		<div class="settings-header">
			<p class="page-eyebrow">
				{{ t('employees', 'Working time') }}
			</p>
			<h2>{{ t('employees', 'Time & Absence Configuration') }}</h2>
			<p class="page-description">
				{{ t('employees', 'Manage anniversaries, absence types and holidays used across the system.') }}
			</p>
		</div>

		<div class="settings-grid">
			<!-- ── Anniversaries ── -->
			<div class="settings-card">
				<div class="card-header">
					<div class="card-title-wrap">
						<div class="card-icon">
							<CalendarStar :size="20" />
						</div>
						<div>
							<p class="card-eyebrow">
								{{ t('employees', 'Seniority') }}
							</p>
							<h3>{{ t('employees', 'Anniversaries') }}</h3>
						</div>
					</div>
					<NcActions>
						<NcActionButton :close-after-click="true" @click="showAddAniversario">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('employees', 'Add anniversary') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="$refs.file.click()">
							<template #icon>
								<Import :size="20" />
							</template>
							{{ t('employees', 'Import list') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="Exportar()">
							<template #icon>
								<Export :size="20" />
							</template>
							{{ t('employees', 'Export / template') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="vaciar()">
							<template #icon>
								<Delete :size="20" />
							</template>
							{{ t('employees', 'Clear table') }}
						</NcActionButton>
					</NcActions>
				</div>

				<div class="card-table-wrap">
					<table class="data-table">
						<thead>
							<tr>
								<th>{{ t('employees', 'Anniversary') }}</th>
								<th>{{ t('employees', 'Days off') }}</th>
								<th class="col-actions" />
							</tr>
						</thead>
						<tbody>
							<tr v-if="Aniversarios.length === 0">
								<td colspan="3" class="empty-row">
									{{ t('employees', 'No anniversaries defined yet.') }}
								</td>
							</tr>
							<tr v-for="item in Aniversarios" :key="item.number_anniversary">
								<td>
									<span class="badge">{{ item.number_anniversary }}</span>
								</td>
								<td>{{ item.days }} {{ t('employees', 'days') }}</td>
								<td class="col-actions">
									<NcActions>
										<NcActionButton :close-after-click="true" @click="editAniversario(item)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											{{ t('employees', 'Edit') }}
										</NcActionButton>
										<NcActionButton :close-after-click="true" @click="deleteAniversario(item.number_anniversary)">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('employees', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- ── Absence types ── -->
			<div class="settings-card">
				<div class="card-header">
					<div class="card-title-wrap">
						<div class="card-icon">
							<FileDocumentOutline :size="20" />
						</div>
						<div>
							<p class="card-eyebrow">
								{{ t('employees', 'Absences') }}
							</p>
							<h3>{{ t('employees', 'Absence types') }}</h3>
						</div>
					</div>
					<NcActions>
						<NcActionButton :close-after-click="true" @click="showAddTipo">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('employees', 'Add type') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="$refs.fileTipo.click()">
							<template #icon>
								<Import :size="20" />
							</template>
							{{ t('employees', 'Import list') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="ExportarTipo()">
							<template #icon>
								<Export :size="20" />
							</template>
							{{ t('employees', 'Export / template') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="vaciarTipo()">
							<template #icon>
								<Delete :size="20" />
							</template>
							{{ t('employees', 'Clear table') }}
						</NcActionButton>
					</NcActions>
				</div>

				<div class="card-table-wrap">
					<table class="data-table">
						<thead>
							<tr>
								<th>{{ t('employees', 'Name') }}</th>
								<th>{{ t('employees', 'Description') }}</th>
								<th class="col-center">
									{{ t('employees', 'File') }}
								</th>
								<th class="col-center">
									{{ t('employees', 'Billable') }}
								</th>
								<th class="col-center">
									{{ t('employees', 'Private') }}
								</th>
								<th class="col-actions" />
							</tr>
						</thead>
						<tbody>
							<tr v-if="TipoAusencias.length === 0">
								<td colspan="6" class="empty-row">
									{{ t('employees', 'No absence types defined yet.') }}
								</td>
							</tr>
							<tr v-for="item in TipoAusencias" :key="item.id">
								<td class="col-name">
									{{ item.name }}
								</td>
								<td class="col-desc">
									{{ item.description }}
								</td>
								<td class="col-center">
									<span :class="item.request_file == 1 ? 'pill pill--yes' : 'pill pill--no'">
										{{ item.request_file == 1 ? t('employees', 'Yes') : t('employees', 'No') }}
									</span>
								</td>
								<td class="col-center">
									<span :class="item.billable == 1 ? 'pill pill--yes' : 'pill pill--no'">
										{{ item.billable == 1 ? t('employees', 'Yes') : t('employees', 'No') }}
									</span>
								</td>
								<td class="col-center">
									<span :class="item.private > 0 ? 'pill pill--yes' : 'pill pill--no'">
										{{ item.private > 0 ? t('employees', 'Yes') : t('employees', 'No') }}
									</span>
								</td>
								<td class="col-actions">
									<NcActions>
										<NcActionButton :close-after-click="true" @click="editTipo(item)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											{{ t('employees', 'Edit') }}
										</NcActionButton>
										<NcActionButton :close-after-click="true" @click="deleteTipo(item.absence_type_id)">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('employees', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- ── Boarding catalog ── -->
			<div class="settings-card">
				<div class="card-header">
					<div class="card-title-wrap">
						<div class="card-icon">
							<AccountArrowRightOutline :size="20" />
						</div>
						<div>
							<p class="card-eyebrow">
								{{ t('employees', 'Checklist') }}
							</p>
							<h3>{{ t('employees', 'Boarding') }}</h3>
						</div>
					</div>
					<NcActions>
						<NcActionButton :close-after-click="true" @click="showAddBoardingItem">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('employees', 'Add item') }}
						</NcActionButton>
					</NcActions>
				</div>

				<div class="card-table-wrap">
					<table class="data-table">
						<thead>
							<tr>
								<th>{{ t('employees', 'Name') }}</th>
								<th class="col-center">
									{{ t('employees', 'Type') }}
								</th>
								<th class="col-actions" />
							</tr>
						</thead>
						<tbody>
							<tr v-if="BoardingCatalogo.length === 0">
								<td colspan="3" class="empty-row">
									{{ t('employees', 'No OnboardingItem items defined yet.') }}
								</td>
							</tr>
							<tr v-for="item in BoardingCatalogo" :key="item.id_boarding">
								<td class="col-name">
									{{ item.name }}
								</td>
								<td class="col-center">
									<span :class="Number(item.on) === 1 ? 'pill pill--yes' : 'pill pill--no'">
										{{ Number(item.on) === 1 ? t('employees', 'OnBoarding') : t('employees', 'OffBoarding') }}
									</span>
								</td>
								<td class="col-actions">
									<NcActions>
										<NcActionButton :close-after-click="true" @click="editBoardingItem(item)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											{{ t('employees', 'Edit') }}
										</NcActionButton>
										<NcActionButton :close-after-click="true" @click="deleteBoardingItem(item.id_boarding)">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('employees', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- ── Holidays ── -->
			<div class="settings-card">
				<div class="card-header">
					<div class="card-title-wrap">
						<div class="card-icon">
							<CalendarMultiple :size="20" />
						</div>
						<div>
							<p class="card-eyebrow">
								{{ t('employees', 'Calendar') }}
							</p>
							<h3>{{ t('employees', 'Holidays') }}</h3>
						</div>
					</div>
					<NcActions>
						<NcActionButton :close-after-click="true" @click="showAddFestivo">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('employees', 'Add holiday') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="$refs.fileFestivo.click()">
							<template #icon>
								<Import :size="20" />
							</template>
							{{ t('employees', 'Import list') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="exportarFestivos()">
							<template #icon>
								<Export :size="20" />
							</template>
							{{ t('employees', 'Export / template') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="vaciarFestivos()">
							<template #icon>
								<Delete :size="20" />
							</template>
							{{ t('employees', 'Clear table') }}
						</NcActionButton>
					</NcActions>
				</div>

				<div class="card-table-wrap">
					<table class="data-table">
						<thead>
							<tr>
								<th>{{ t('employees', 'Name') }}</th>
								<th>{{ t('employees', 'Date') }}</th>
								<th class="col-center">
									{{ t('employees', 'Official') }}
								</th>
								<th class="col-actions" />
							</tr>
						</thead>
						<tbody>
							<tr v-if="Festivos.length === 0">
								<td colspan="4" class="empty-row">
									{{ t('employees', 'No holidays defined yet.') }}
								</td>
							</tr>
							<tr v-for="item in Festivos" :key="item.id_holiday">
								<td class="col-name">
									{{ item.name }}
								</td>
								<td>
									<span class="date-chip">{{ item.date }}</span>
								</td>
								<td class="col-center">
									<span :class="item.official == 1 ? 'pill pill--yes' : 'pill pill--no'">
										{{ item.official == 1 ? t('employees', 'Yes') : t('employees', 'No') }}
									</span>
								</td>
								<td class="col-actions">
									<NcActions>
										<NcActionButton :close-after-click="true" @click="editFestivo(item)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											{{ t('employees', 'Edit') }}
										</NcActionButton>
										<NcActionButton v-if="item.official != 1" :close-after-click="true" @click="deleteFestivo(item.id_holiday)">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('employees', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- hidden file inputs -->
		<input ref="file"
			type="file"
			style="display:none"
			accept=".xlsx"
			@change="importar()">
		<input ref="fileTipo"
			type="file"
			style="display:none"
			accept=".xlsx"
			@change="importarTipo()">
		<input ref="fileFestivo"
			type="file"
			style="display:none"
			accept=".xlsx"
			@change="importarFestivos()">

		<!-- ── Modal: Add/Edit anniversary ── -->
		<NcModal
			v-if="modalAddAniversario"
			ref="modalRef"
			:name="editingAniversario ? t('employees', 'Edit anniversary') : t('employees', 'Add anniversary')"
			@close="closeModalAniversario">
			<div class="modal-body">
				<div class="modal-header-section">
					<p class="card-eyebrow">
						{{ t('employees', 'Seniority') }}
					</p>
					<h2>{{ editingAniversario ? t('employees', 'Edit anniversary') : t('employees', 'New anniversary rule') }}</h2>
					<p>{{ t('employees', 'Define how many days off are granted at each anniversary year.') }}</p>
				</div>
				<div class="form-grid">
					<NcTextField :label="t('employees', 'Anniversary number')" :value.sync="NumeroAniversario" />
					<NcTextField :label="t('employees', 'Days off')" :value.sync="DiasAniversario" />
				</div>
				<div class="modal-actions">
					<NcButton @click="closeModalAniversario">
						{{ t('employees', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!NumeroAniversario || !DiasAniversario" @click="guardarAniversario">
						{{ t('employees', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── Modal: Add/Edit absence type ── -->
		<NcModal
			v-if="modalAddTipo"
			ref="modalRef"
			:name="editingTipo ? t('employees', 'Edit absence type') : t('employees', 'Add absence type')"
			@close="closeModalTipo">
			<div class="modal-body">
				<div class="modal-header-section">
					<p class="card-eyebrow">
						{{ t('employees', 'Absences') }}
					</p>
					<h2>{{ editingTipo ? t('employees', 'Edit absence type') : t('employees', 'New absence type') }}</h2>
					<p>{{ t('employees', 'Define a new category of absence employees can request.') }}</p>
				</div>
				<div class="form-grid span-2">
					<NcTextField class="span-2" :label="t('employees', 'Name')" :value.sync="NombreTipo" />
					<NcTextField class="span-2" :label="t('employees', 'Description')" :value.sync="DescripcionTipo" />
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="RequestArchivoTipo" type="switch" />
						<div>
							<p class="switch-label">
								{{ t('employees', 'Request file') }}
							</p>
							<p class="switch-desc">
								{{ t('employees', 'Employee must attach a document when requesting this absence.') }}
							</p>
						</div>
					</div>
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="request_bonus_vacation" type="switch" />
						<div>
							<p class="switch-label">
								{{ t('employees', 'Vacation bonus') }}
							</p>
							<p class="switch-desc">
								{{ t('employees', 'This absence type triggers vacation bonus calculation.') }}
							</p>
						</div>
					</div>
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="billable" type="switch" />
						<div>
							<p class="switch-label">
								{{ t('employees', 'Billable') }}
							</p>
							<p class="switch-desc">
								{{ t('employees', 'This absence type is deducted from the employee\'s available days.') }}
							</p>
						</div>
					</div>
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="isPrivate" type="switch" />
						<div>
							<p class="switch-label">
								{{ t('employees', 'Private') }}
							</p>
							<p class="switch-desc">
								{{ t('employees', 'Only admins and HR can see and request this absence type.') }}
							</p>
						</div>
					</div>
				</div>
				<div class="modal-actions">
					<NcButton @click="closeModalTipo">
						{{ t('employees', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!NombreTipo || !DescripcionTipo" @click="guardarTipo">
						{{ t('employees', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── Modal: Add/Edit holiday ── -->
		<NcModal
			v-if="modalFestivo"
			:name="editingFestivo ? t('employees', 'Edit holiday') : t('employees', 'Add holiday')"
			@close="closeModalFestivo">
			<div class="modal-body">
				<div class="modal-header-section">
					<p class="card-eyebrow">
						{{ t('employees', 'Calendar') }}
					</p>
					<h2>{{ editingFestivo ? t('employees', 'Edit holiday') : t('employees', 'New holiday') }}</h2>
					<p>{{ t('employees', 'Public holidays are excluded from working day calculations.') }}</p>
					<p v-if="editingFestivo && editingFestivo.official == 1" class="official-warning">
						{{ t('employees', 'This is an official holiday. If its date depends on a weekday rule (e.g. "third Monday of March"), your edit may be overwritten automatically next January 1st.') }}
					</p>
				</div>
				<div class="form-grid">
					<NcTextField class="span-2" :label="t('employees', 'Holiday name')" :value.sync="festivoNombre" />
					<div class="span-2">
						<NcTextField
							class="span-2"
							type="date"
							:label="t('employees', 'Date (day and month only)')"
							:value.sync="festivoFecha" />
						<p class="field-hint">
							{{ t('employees', 'The year is ignored — the holiday repeats every year.') }}
						</p>
					</div>
				</div>
				<div class="modal-actions">
					<NcButton @click="closeModalFestivo">
						{{ t('employees', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!festivoNombre || !festivoFecha" @click="guardarFestivo">
						{{ t('employees', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── Modal: Add/Edit OnboardingItem item ── -->
		<NcModal
			v-if="modalAddBoarding"
			:name="editingBoardingItem ? t('employees', 'Edit OnboardingItem item') : t('employees', 'Add OnboardingItem item')"
			@close="closeModalBoarding">
			<div class="modal-body">
				<div class="modal-header-section">
					<p class="card-eyebrow">
						{{ t('employees', 'Checklist') }}
					</p>
					<h2>{{ editingBoardingItem ? t('employees', 'Edit OnboardingItem item') : t('employees', 'New OnboardingItem item') }}</h2>
					<p>{{ t('employees', 'Items appear in the employee\'s OnBoarding or OffBoarding checklist.') }}</p>
				</div>
				<div class="form-grid">
					<NcTextField class="span-2" :label="t('employees', 'Item name')" :value.sync="nombreBoarding" />
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="onBoardingItem" type="switch" />
						<div>
							<p class="switch-label">
								{{ onBoardingItem ? t('employees', 'OnBoarding') : t('employees', 'OffBoarding') }}
							</p>
							<p class="switch-desc">
								{{ t('employees', 'Whether this item belongs to the onboarding or offboarding checklist.') }}
							</p>
						</div>
					</div>
				</div>
				<div class="modal-actions">
					<NcButton @click="closeModalBoarding">
						{{ t('employees', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!nombreBoarding" @click="guardarBoardingItem">
						{{ t('employees', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Import from 'vue-material-design-icons/Import.vue'
import Export from 'vue-material-design-icons/Export.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import CalendarStar from 'vue-material-design-icons/CalendarStar.vue'
import CalendarMultiple from 'vue-material-design-icons/CalendarMultiple.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import AccountArrowRightOutline from 'vue-material-design-icons/AccountArrowRightOutline.vue'

import {
	NcActions,
	NcActionButton,
	NcModal,
	NcTextField,
	NcButton,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'
import { ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'WorkingTimeSettings',
	components: {
		NcActions,
		NcActionButton,
		NcModal,
		NcTextField,
		NcButton,
		NcCheckboxRadioSwitch,
		Plus,
		Import,
		Export,
		Delete,
		Pencil,
		CalendarStar,
		CalendarMultiple,
		FileDocumentOutline,
		AccountArrowRightOutline,
	},

	data() {
		return {
			// ── Anniversaries ──
			modalAddAniversario: false,
			editingAniversario: null,
			modalRef: ref(null),
			Aniversarios: [],
			NumeroAniversario: null,
			DiasAniversario: null,

			// ── Absence types ──
			modalAddTipo: false,
			editingTipo: null,
			TipoAusencias: [],
			NombreTipo: null,
			DescripcionTipo: null,
			RequestArchivoTipo: false,
			request_bonus_vacation: false,
			billable: false,
			isPrivate: false,

			// ── Holidays ──
			Festivos: [],
			modalFestivo: false,
			editingFestivo: null,
			festivoNombre: '',
			festivoFecha: '',

			// ── Boarding catalog ──
			BoardingCatalogo: [],
			modalAddBoarding: false,
			editingBoardingItem: null,
			nombreBoarding: '',
			onBoardingItem: true,
		}
	},

	mounted() {
		this.getAniversarios()
		this.getType()
		this.getFestivos()
		this.getBoardingCatalogo()
	},

	methods: {
		t,

		// ────────────────────────────────────────────
		// Anniversaries
		// ────────────────────────────────────────────
		showAddAniversario() {
			this.editingAniversario = null
			this.NumeroAniversario = null
			this.DiasAniversario = null
			this.modalAddAniversario = true
		},

		editAniversario(item) {
			this.editingAniversario = item
			this.NumeroAniversario = item.number_anniversary
			this.DiasAniversario = item.days
			this.modalAddAniversario = true
		},

		closeModalAniversario() {
			this.modalAddAniversario = false
			this.editingAniversario = null
			this.NumeroAniversario = null
			this.DiasAniversario = null
		},

		async getAniversarios() {
			try {
				await axios.get(generateUrl('/apps/employees/Getaniversarios'))
					.then(
						(response) => { this.Aniversarios = response?.data?.ocs?.data },
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('employees', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async guardarAniversario() {
			try {
				if (this.editingAniversario) {
					await axios.post(generateUrl('/apps/employees/modificarAniversario'), {
						number_anniversary: this.editingAniversario.number_anniversary,
						nuevo_numero_aniversario: this.NumeroAniversario,
						days: parseFloat(this.DiasAniversario),
					})
				} else {
					await axios.post(generateUrl('/apps/employees/AgregarNewAniversario'), {
						number_anniversary: this.NumeroAniversario,
						days: this.DiasAniversario,
					})
				}
				showSuccess(t('employees', 'Anniversary saved'))
				this.closeModalAniversario()
				this.getAniversarios()
			} catch (err) {
				showError(t('employees', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async deleteAniversario(numeroAniversario) {
			try {
				await axios.post(generateUrl('/apps/employees/deleteAniversario'), {
					number_anniversary: numeroAniversario,
				})
				showSuccess(t('employees', 'Anniversary deleted'))
				this.getAniversarios()
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async vaciar() {
			try {
				await axios.get(generateUrl('/apps/employees/VaciarAniversarios'))
					.then(
						() => {
							this.getAniversarios()
							showSuccess(t('employees', 'Anniversaries table cleared'))
						},
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('employees', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		Exportar() {
			axios.get(generateUrl('/apps/employees/ExportListAniversarios'), { responseType: 'blob' }).then(
				(response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'anniversaries.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => {
					showError(t('employees', 'An error occurred {error}', { error: String(err) }))
				},
			)
		},

		async importar() {
			const formData = new FormData()
			formData.append('fileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/employees/ImportListAniversarios'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.getAniversarios()
				showSuccess(t('employees', 'Database updated successfully'))
			} catch (err) {
				showError(t('employees', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		// ────────────────────────────────────────────
		// Absence types
		// ────────────────────────────────────────────
		showAddTipo() {
			this.editingTipo = null
			this.NombreTipo = null
			this.DescripcionTipo = null
			this.RequestArchivoTipo = false
			this.request_bonus_vacation = false
			this.billable = false
			this.isPrivate = false
			this.modalAddTipo = true
		},

		editTipo(item) {
			this.editingTipo = item
			this.NombreTipo = item.name
			this.DescripcionTipo = item.description
			this.RequestArchivoTipo = item.request_file === 1
			this.request_bonus_vacation = item.request_bonus_vacation === 1
			this.billable = item.billable === 1
			this.isPrivate = item.private > 0
			this.modalAddTipo = true
		},

		closeModalTipo() {
			this.modalAddTipo = false
			this.editingTipo = null
			this.NombreTipo = null
			this.DescripcionTipo = null
			this.RequestArchivoTipo = false
			this.request_bonus_vacation = false
			this.billable = false
			this.isPrivate = false
		},

		async getType() {
			try {
				await axios.get(generateUrl('/apps/employees/getType'))
					.then(
						(response) => { this.TipoAusencias = response.data },
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('employees', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async guardarTipo() {
			try {
				if (this.editingTipo) {
					await axios.post(generateUrl('/apps/employees/modificarTipo'), {
						id: this.editingTipo.absence_type_id,
						name: this.NombreTipo,
						description: this.DescripcionTipo,
						request_file: this.RequestArchivoTipo ? 1 : 0,
						request_bonus_vacation: this.request_bonus_vacation ? 1 : 0,
						billable: this.billable ? 1 : 0,
						private: this.isPrivate ? 1 : 0,
					})
				} else {
					await axios.post(generateUrl('/apps/employees/AgregarNewTipo'), {
						name: this.NombreTipo,
						description: this.DescripcionTipo,
						request_file: this.RequestArchivoTipo ? 1 : 0,
						request_bonus_vacation: this.request_bonus_vacation ? 1 : 0,
						billable: this.billable ? 1 : 0,
						private: this.isPrivate ? 1 : 0,
					})
				}
				showSuccess(t('employees', 'Absence type saved'))
				this.closeModalTipo()
				this.getType()
			} catch (err) {
				showError(t('employees', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async deleteTipo(id) {
			try {
				await axios.post(generateUrl('/apps/employees/deleteTipo'), { id })
				showSuccess(t('employees', 'Absence type deleted'))
				this.getType()
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async vaciarTipo() {
			try {
				await axios.get(generateUrl('/apps/employees/VaciarTipo'))
					.then(
						() => {
							this.getType()
							showSuccess(t('employees', 'Absence types table cleared'))
						},
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('employees', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		ExportarTipo() {
			axios.get(generateUrl('/apps/employees/ExportarTipo'), { responseType: 'blob' }).then(
				(response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'tipos_ausencias.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => {
					showError(t('employees', 'An error occurred {error}', { error: String(err) }))
				},
			)
		},

		async importarTipo() {
			const formData = new FormData()
			formData.append('fileXLSX', this.$refs.fileTipo.files[0])
			try {
				await axios.post(generateUrl('/apps/employees/importarTipo'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.getType()
				showSuccess(t('employees', 'Database updated successfully'))
			} catch (err) {
				showError(t('employees', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		// ────────────────────────────────────────────
		// Holidays
		// ────────────────────────────────────────────
		async getFestivos() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/getFestivos'))
				this.Festivos = response?.data?.ocs?.data ?? response?.data ?? []
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		showAddFestivo() {
			this.editingFestivo = null
			this.festivoNombre = ''
			this.festivoFecha = ''
			this.modalFestivo = true
		},

		editFestivo(item) {
			this.editingFestivo = item
			this.festivoNombre = item.name
			this.festivoFecha = '2000-' + item.date
			this.modalFestivo = true
		},

		closeModalFestivo() {
			this.modalFestivo = false
			this.editingFestivo = null
		},

		async guardarFestivo() {
			try {
				if (this.editingFestivo) {
					await axios.post(generateUrl('/apps/employees/modificarFestivo'), {
						id_holiday: this.editingFestivo.id_holiday,
						name: this.festivoNombre,
						date: this.festivoFecha,
					})
				} else {
					await axios.post(generateUrl('/apps/employees/crearFestivo'), {
						name: this.festivoNombre,
						date: this.festivoFecha,
					})
				}
				showSuccess(t('employees', 'Holiday saved'))
				this.closeModalFestivo()
				this.getFestivos()
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async deleteFestivo(id) {
			try {
				await axios.post(generateUrl('/apps/employees/deleteFestivo'), { id_holiday: id })
				showSuccess(t('employees', 'Holiday deleted'))
				this.getFestivos()
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async importarFestivos() {
			const formData = new FormData()
			formData.append('festivosfileXLSX', this.$refs.fileFestivo.files[0])
			try {
				await axios.post(generateUrl('/apps/employees/importarFestivos'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				showSuccess(t('employees', 'Database updated successfully'))
				this.getFestivos()
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		exportarFestivos() {
			axios.get(generateUrl('/apps/employees/exportarFestivos'), { responseType: 'blob' }).then(
				(response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'Holiday.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => { showError(String(err)) },
			)
		},

		async vaciarFestivos() {
			try {
				await axios.get(generateUrl('/apps/employees/vaciarFestivos'))
				showSuccess(t('employees', 'Holidays table cleared'))
				this.getFestivos()
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		// ────────────────────────────────────────────
		// Boarding catalog
		// ────────────────────────────────────────────
		async getBoardingCatalogo() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/getBoarding'))
				this.BoardingCatalogo = response?.data?.ocs?.data ?? response?.data ?? []
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		showAddBoardingItem() {
			this.editingBoardingItem = null
			this.nombreBoarding = ''
			this.onBoardingItem = true
			this.modalAddBoarding = true
		},

		editBoardingItem(item) {
			this.editingBoardingItem = item
			this.nombreBoarding = item.name
			this.onBoardingItem = Number(item.on) === 1
			this.modalAddBoarding = true
		},

		closeModalBoarding() {
			this.modalAddBoarding = false
			this.editingBoardingItem = null
			this.nombreBoarding = ''
			this.onBoardingItem = true
		},

		async guardarBoardingItem() {
			try {
				if (this.editingBoardingItem) {
					await axios.post(generateUrl('/apps/employees/modificarBoarding'), {
						id_boarding: this.editingBoardingItem.id_boarding,
						name: this.nombreBoarding,
						on: this.onBoardingItem ? 1 : 0,
					})
				} else {
					await axios.post(generateUrl('/apps/employees/crearBoarding'), {
						name: this.nombreBoarding,
						on: this.onBoardingItem ? 1 : 0,
					})
				}
				showSuccess(t('employees', 'Boarding item saved'))
				this.closeModalBoarding()
				this.getBoardingCatalogo()
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async deleteBoardingItem(id) {
			try {
				await axios.post(generateUrl('/apps/employees/deleteBoarding'), { id_boarding: id })
				showSuccess(t('employees', 'Boarding item deleted'))
				this.getBoardingCatalogo()
			} catch (err) {
				showError(t('employees', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style scoped lang="scss">
/* ── Page layout ── */
.settings-page {
	display: flex;
	flex-direction: column;
	gap: 24px;
	padding: 24px;
	max-width: 1200px;
}

.settings-header {
	display: flex;
	flex-direction: column;
	gap: 4px;

	h2 {
		margin: 4px 0 6px;
		font-size: 1.5rem;
		font-weight: 700;
		color: var(--color-main-text);
	}
}

.page-eyebrow {
	font-size: 0.72rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: var(--color-primary-element);
	margin: 0;
}

.page-description {
	font-size: 0.875rem;
	color: var(--color-text-maxcontrast);
	margin: 0;
}

/* ── Cards grid ── */
.settings-grid {
	display: grid;
	grid-template-columns: 0.85fr 1.15fr;
	grid-auto-rows: 1fr;
	gap: 16px;
	align-items: stretch;

	@media (max-width: 1024px) {
		grid-template-columns: 1fr;
	}
}

/* ── Card ── */
.settings-card {
	display: flex;
	flex-direction: column;
	height: 100%;
	border-radius: var(--border-radius-large);
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	overflow: hidden;
}

.card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 16px 12px 16px 16px;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-background-soft);
}

.card-title-wrap {
	display: flex;
	align-items: center;
	gap: 12px;

	h3 {
		margin: 0;
		font-size: 0.95rem;
		font-weight: 600;
		color: var(--color-main-text);
	}
}

.card-eyebrow {
	font-size: 0.65rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: var(--color-primary-element);
	margin: 0 0 1px;
}

.card-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	flex-shrink: 0;
}

/* ── Table ── */
.card-table-wrap {
	overflow-x: auto;
	max-height: calc(60vh - 4rem);
	overflow-y: auto;
}

.data-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.85rem;

	thead tr {
		background: var(--color-background-soft);
		border-bottom: 1px solid var(--color-border);
	}

	th {
		padding: 10px 14px;
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--color-text-maxcontrast);
		text-align: left;
		white-space: nowrap;
	}

	tbody tr {
		border-bottom: 1px solid var(--color-border);
		transition: background 0.1s ease;

		&:last-child {
			border-bottom: none;
		}

		&:hover {
			background: var(--color-background-hover);
		}
	}

	td {
		padding: 10px 14px;
		color: var(--color-main-text);
		vertical-align: middle;
	}
}

.col-center {
	text-align: center !important;
}

.col-name {
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 140px;
}

.col-desc {
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 140px;
}

.col-actions {
	width: 44px;
	text-align: center;
}

.empty-row {
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: 24px 14px !important;
}

/* ── Badges / chips ── */
.badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 28px;
	height: 24px;
	padding: 0 8px;
	border-radius: 999px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	font-size: 0.75rem;
	font-weight: 700;
}

.pill {
	display: inline-flex;
	align-items: center;
	padding: 2px 10px;
	border-radius: 999px;
	font-size: 0.7rem;
	font-weight: 600;

	&--yes {
		background: var(--color-success);
		color: var(--color-success-text);
	}

	&--no {
		background: var(--color-background-soft);
		color: var(--color-text-maxcontrast);
		border: 1px solid var(--color-border);
	}
}

.date-chip {
	display: inline-flex;
	align-items: center;
	padding: 2px 8px;
	border-radius: 6px;
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);
	font-size: 0.8rem;
	font-family: monospace;
	color: var(--color-main-text);
}

.official-warning {
	margin: 6px 0 0 !important;
	padding: 8px 12px;
	border-radius: var(--border-radius);
	background: var(--color-warning);
	color: var(--color-warning-text);
	font-size: 0.8rem !important;
}

/* ── Modals ── */
.modal-body {
	display: flex;
	flex-direction: column;
	gap: 20px;
	padding: 24px;
}

.modal-header-section {
	display: flex;
	flex-direction: column;
	gap: 4px;

	h2 {
		margin: 4px 0 2px;
		font-size: 1.15rem;
		font-weight: 700;
		color: var(--color-main-text);
	}

	p {
		margin: 0;
		font-size: 0.875rem;
		color: var(--color-text-maxcontrast);
	}
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 12px;

	.span-2 {
		grid-column: span 2;
	}
}

.switch-card {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 12px 14px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);
}

.switch-label {
	margin: 0 0 2px;
	font-size: 0.875rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.switch-desc {
	margin: 0;
	font-size: 0.75rem;
	color: var(--color-text-maxcontrast);
}

.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
