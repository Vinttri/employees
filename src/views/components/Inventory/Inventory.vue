<template>
	<NcAppContent
		class="inventario-page"
		:name="t('employees', 'IT Inventory')">
		<div class="inventario-header">
			<div class="inventario-heading">
				<h2>{{ pageTitle }}</h2>
				<p>{{ pageSubtitle }}</p>
			</div>
			<NcButton
				v-if="maintenanceCapabilities.moduleEnabled && maintenanceCapabilities.canViewMaintenance"
				type="secondary"
				@click="$router.push({ name: 'Maintenance' })">
				<template #icon>
					<CalendarMonth :size="20" />
				</template>
				{{ t('employees', 'Maintenance calendar') }}
			</NcButton>

			<NcActions
				class="inventario-actions"
				:force-menu="true"
				:disabled="loading">
				<template #icon>
					<Plus :size="20" />
				</template>

				<NcActionButton
					:disabled="tab === 'soporte' && !selectedEquipo"
					@click="openCreateModal">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ primaryButtonText }}
				</NcActionButton>

				<NcActionButton @click="downloadImportTemplate">
					<template #icon>
						<Download :size="20" />
					</template>
					{{ t('employees', 'Download CSV template') }}
				</NcActionButton>

				<NcActionButton
					:disabled="tab === 'soporte' && !selectedEquipo"
					@click="openImportModal">
					<template #icon>
						<Upload :size="20" />
					</template>
					{{ t('employees', 'Import CSV') }}
				</NcActionButton>

				<NcActionButton
					:disabled="currentRows.length === 0"
					@click="exportCurrentTab">
					<template #icon>
						<Download :size="20" />
					</template>
					{{ t('employees', 'Export CSV') }}
				</NcActionButton>

				<NcActionButton
					v-if="tab !== 'modelos'"
					@click="openModelos">
					<template #icon>
						<Database :size="20" />
					</template>
					{{ t('employees', 'Manage models') }}
				</NcActionButton>

				<NcActionButton
					v-if="tab === 'modelos'"
					@click="setTab('Team')">
					<template #icon>
						<Laptop :size="20" />
					</template>
					{{ t('employees', 'Back to devices') }}
				</NcActionButton>
			</NcActions>
		</div>

		<div class="inventario-summary" aria-hidden="true">
			<div
				v-for="item in summaryItems"
				:key="item.id"
				class="summary-item">
				<component :is="item.icon" :size="20" />
				<span>{{ item.label }}</span>
				<strong>{{ item.value }}</strong>
			</div>
		</div>

		<div
			class="inventario-tabs"
			role="tablist"
			:aria-label="t('employees', 'Inventory sections')">
			<button
				v-for="item in tabs"
				:key="item.id"
				type="button"
				role="tab"
				:aria-selected="tab === item.id"
				:class="{ active: tab === item.id }"
				@click="setTab(item.id)">
				<component :is="item.icon" :size="20" />
				<span>{{ item.name }}</span>
			</button>
		</div>

		<div class="inventario-toolbar">
			<div class="search-field">
				<NcTextField
					:value.sync="search"
					:label="t('employees', 'Search')"
					@update:value="scheduleSearch"
					@keyup.enter="applyFilters">
					<template #icon>
						<Magnify :size="20" />
					</template>
				</NcTextField>
			</div>

			<template v-if="tab === 'Team'">
				<label class="compact-filter">
					<span>{{ t('employees', 'Status') }}</span>
					<select v-model="filters.status" @change="applyFilters">
						<option value="">{{ t('employees', 'All statuses') }}</option>
						<option value="active">{{ t('employees', 'Active') }}</option>
						<option value="asignado">{{ t('employees', 'Assigned') }}</option>
						<option value="mantenimiento">{{ t('employees', 'Maintenance') }}</option>
						<option value="inactivo">{{ t('employees', 'Inactive') }}</option>
						<option value="baja">{{ t('employees', 'Retired') }}</option>
					</select>
				</label>

				<label class="compact-filter">
					<span>{{ t('employees', 'Assignment') }}</span>
					<select v-model="filters.asignacion" @change="applyFilters">
						<option value="">{{ t('employees', 'All devices') }}</option>
						<option value="asignado">{{ t('employees', 'Assigned') }}</option>
						<option value="sin_asignar">{{ t('employees', 'Unassigned') }}</option>
					</select>
				</label>

				<label class="compact-filter">
					<span>{{ t('employees', 'Employee') }}</span>
					<select v-model="filters.idEmployee" @change="applyFilters">
						<option value="">{{ t('employees', 'All employees') }}</option>
						<option v-for="empleado in empleadosFiltro"
							:key="empleado.id_employee"
							:value="String(empleado.id_employee)">
							{{ empleado.displayname }}
						</option>
					</select>
				</label>

				<label class="compact-filter">
					<span>{{ t('employees', 'Model') }}</span>
					<select v-model="filters.idModel" @change="applyFilters">
						<option value="">{{ t('employees', 'All models') }}</option>
						<option v-for="model in modelosCatalogo"
							:key="model.id_model"
							:value="String(model.id_model)">
							{{ modeloLabel(model) }}
						</option>
					</select>
				</label>

				<NcButton v-if="hasActiveFilters" type="tertiary" @click="clearFilters">
					<template #icon>
						<FilterOff :size="20" />
					</template>
					{{ t('employees', 'Clear filters') }}
				</NcButton>
			</template>

			<NcButton
				:disabled="loading"
				:aria-label="t('employees', 'Refresh inventory')"
				@click="reload">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Refresh v-else :size="20" />
				</template>
				{{ t('employees', 'Refresh') }}
			</NcButton>
		</div>

		<div class="inventario-card">
			<div v-if="loading" class="loading-state">
				<NcLoadingIcon :size="48" />
			</div>

			<template v-else>
				<!-- MODELOS -->
				<div v-if="tab === 'modelos'" class="table-wrap">
					<table v-if="modelos.length > 0" class="inventario-table">
						<thead>
							<tr>
								<th>{{ t('employees', 'Brand') }}</th>
								<th>{{ t('employees', 'Model') }}</th>
								<th>{{ t('employees', 'CPU') }}</th>
								<th>{{ t('employees', 'RAM') }}</th>
								<th>{{ t('employees', 'Storage') }}</th>
								<th>{{ t('employees', 'Type') }}</th>
								<th>{{ t('employees', 'Touch') }}</th>
								<th>{{ t('employees', 'Actions') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="model in modelos"
								:key="model.id_model">
								<td class="strong-cell">
									{{ displayValue(model.brand) }}
								</td>
								<td>{{ displayValue(model.model) }}</td>
								<td>{{ displayValue(model.processor) }}</td>
								<td>{{ displayValue(model.ram) }}</td>
								<td>{{ displayValue(model.disk_drive) }}</td>
								<td>{{ displayValue(model.type) }}</td>
								<td>
									<span
										class="boolean-pill"
										:class="{ active: isTruthy(model.touch) }">
										<Check v-if="isTruthy(model.touch)" :size="16" />
										<Close v-else :size="16" />
										{{ isTruthy(model.touch) ? t('employees', 'Yes') : t('employees', 'No') }}
									</span>
								</td>
								<td class="actions-cell">
									<NcButton
										size="small"
										:aria-label="t('employees', 'Edit model')"
										@click="openEditModelo(model)">
										<template #icon>
											<Pencil :size="18" />
										</template>
										{{ t('employees', 'Edit') }}
									</NcButton>
								</td>
							</tr>
						</tbody>
					</table>

					<NcEmptyContent
						v-else
						:name="t('employees', 'No models found')" />
				</div>

				<!-- EQUIPOS -->
				<div v-if="tab === 'Team'" class="table-wrap">
					<p class="inventory-focus-announcement" aria-live="polite">
						{{ focusedDeviceId ? t('employees', 'The requested device is highlighted in the inventory table.') : '' }}
					</p>
					<table v-if="Team.length > 0" class="inventario-table inventario-table--devices">
						<thead>
							<tr>
								<th>{{ t('employees', 'Device name') }}</th>
								<th>{{ t('employees', 'System name') }}</th>
								<th>{{ t('employees', 'Serial number') }}</th>
								<th>{{ t('employees', 'Model') }}</th>
								<th>{{ t('employees', 'Status') }}</th>
								<th>{{ t('employees', 'Assigned employee') }}</th>
								<th>{{ t('employees', 'Actions') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="equipo in Team"
								:key="equipo.id_team"
								:data-device-id="equipo.id_team"
								:class="{ 'inventory-device-highlight': focusedDeviceId === Number(equipo.id_team) }"
								:aria-current="focusedDeviceId === Number(equipo.id_team) ? 'true' : null"
								tabindex="-1">
								<td class="strong-cell">
									{{ displayValue(equipo.device_name) }}
								</td>
								<td>{{ displayValue(equipo.system_name) }}</td>
								<td>{{ displayValue(equipo.serial_number) }}</td>
								<td>{{ modeloName(equipo) }}</td>
								<td>
									<span
										class="status-pill"
										:class="statusClass(equipo.status)">
										{{ inventoryStatusLabel(equipo.status) || displayValue(equipo.status) }}
									</span>
								</td>
								<td>
									<div v-if="equipo.employee_uid" class="employee-cell">
										<NcAvatar
											:user="equipo.employee_uid"
											:display-name="empleadoAsignadoName(equipo)"
											:show-user-status="false"
											:show-user-status-compact="false"
											:size="36"
											disable-menu />
										<div>
											<strong>{{ empleadoAsignadoName(equipo) }}</strong>
											<small>{{ equipo.employee_uid }}</small>
										</div>
									</div>
									<div v-else class="employee-cell employee-cell--empty">
										<span class="neutral-avatar"><AccountOutline :size="22" /></span>
										<strong>{{ t('employees', 'Unassigned') }}</strong>
									</div>
								</td>
								<td class="actions-cell">
									<div class="row-actions">
										<NcButton
											size="small"
											type="primary"
											:aria-label="t('employees', 'View device')"
											@click="openViewEquipo(equipo)">
											<template #icon>
												<Eye :size="18" />
											</template>
											{{ t('employees', 'View') }}
										</NcButton>

										<NcActions :aria-label="t('employees', 'Device actions')">
											<NcActionButton close-after-click @click="openEditEquipo(equipo)">
												<template #icon>
													<Pencil :size="18" />
												</template>
												{{ t('employees', 'Edit') }}
											</NcActionButton>
											<NcActionButton close-after-click @click="openHistory(equipo)">
												<template #icon>
													<History :size="18" />
												</template>
												{{ t('employees', 'History') }}
											</NcActionButton>
											<NcActionButton close-after-click @click="selectEquipoSoporte(equipo)">
												<template #icon>
													<Wrench :size="18" />
												</template>
												{{ t('employees', 'Register support') }}
											</NcActionButton>
										</NcActions>
									</div>
								</td>
							</tr>
						</tbody>
					</table>

					<div v-if="Team.length > 0" class="inventory-pagination">
						<span>{{ paginationLabel }}</span>
						<div>
							<NcButton :disabled="loading || pageOffset === 0" @click="previousPage">
								{{ t('employees', 'Previous') }}
							</NcButton>
							<NcButton :disabled="loading || pageOffset + pageLimit >= totalTeams" @click="nextPage">
								{{ t('employees', 'Next') }}
							</NcButton>
						</div>
					</div>

					<NcEmptyContent
						v-else
						:name="hasActiveFilters ? t('employees', 'No devices match the current filters') : t('employees', 'No devices have been registered')" />
				</div>

				<!-- SOPORTE -->
				<div v-if="tab === 'soporte'" class="table-wrap">
					<div v-if="selectedEquipo" class="selected-equipo">
						<div>
							<span>{{ t('employees', 'Selected device') }}</span>
							<strong>{{ displayValue(selectedEquipo.device_name) }}</strong>
						</div>
						<span>{{ displayValue(selectedEquipo.serial_number) }}</span>
					</div>

					<NcEmptyContent
						v-if="!selectedEquipo"
						:name="t('employees', 'Select a device to view support history')" />

					<table v-else-if="soporte.length > 0" class="inventario-table">
						<thead>
							<tr>
								<th>{{ t('employees', 'Date') }}</th>
								<th>{{ t('employees', 'Action') }}</th>
								<th>{{ t('employees', 'Current user') }}</th>
								<th>{{ t('employees', 'Support user') }}</th>
								<th>{{ t('employees', 'Details') }}</th>
								<th>{{ t('employees', 'Duration') }}</th>
								<th>{{ t('employees', 'Time report') }}</th>
								<th>{{ t('employees', 'Actions') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="item in soporte"
								:key="item.id_support">
								<td>{{ displayValue(item.date) }}</td>
								<td class="strong-cell">
									{{ displayValue(item.action) }}
								</td>
								<td>{{ displayValue(item.current_user) }}</td>
								<td>{{ displayValue(item.user_support) }}</td>
								<td>{{ displayValue(item.details) }}</td>
								<td>{{ supportDurationLabel(item.duration_minutes) }}</td>
								<td>{{ item.id_report ? `#${item.id_report}` : t('employees', 'Pending synchronization') }}</td>
								<td>
									<NcButton :title="t('employees', 'Edit')" @click="openEditSoporte(item)">
										<template #icon>
											<Pencil :size="18" />
										</template>
									</NcButton>
								</td>
							</tr>
						</tbody>
					</table>

					<NcEmptyContent
						v-else
						:name="t('employees', 'No support records found')" />
				</div>
			</template>
		</div>

		<!-- MODAL CREAR -->
		<NcModal
			v-if="showModal"
			class="inventario-nc-modal"
			:name="modalTitle"
			@close="closeModal">
			<div class="inventario-modal">
				<!-- Crear model -->
				<div v-if="tab === 'modelos'" class="form-grid">
					<NcTextField
						:value.sync="formModelo.brand"
						:label="t('employees', 'Brand')" />

					<NcTextField
						:value.sync="formModelo.model"
						:label="t('employees', 'Model')" />

					<NcTextField
						:value.sync="formModelo.processor"
						:label="t('employees', 'CPU')" />

					<NcTextField
						:value.sync="formModelo.ram"
						:label="t('employees', 'RAM')" />

					<NcTextField
						:value.sync="formModelo.disk_drive"
						:label="t('employees', 'Storage')" />

					<NcTextField
						:value.sync="formModelo.type"
						:label="t('employees', 'Type')" />

					<NcCheckboxRadioSwitch v-model="formModelo.touch">
						{{ t('employees', 'Touch screen') }}
					</NcCheckboxRadioSwitch>
				</div>

				<!-- Crear equipo -->
				<div v-if="tab === 'Team'" class="form-grid">
					<div class="select-field">
						<label>{{ t('employees', 'Model') }}</label>

						<select v-model="formEquipo.id_model" :disabled="isReadonly">
							<option value="">
								{{ t('employees', 'Select a model') }}
							</option>

							<option
								v-for="model in modelosCatalogo"
								:key="model.id_model"
								:value="model.id_model">
								{{ modeloLabel(model) }}
							</option>
						</select>
					</div>

					<NcTextField
						:value.sync="formEquipo.device_name"
						:disabled="isReadonly"
						:label="t('employees', 'Device name')" />

					<NcTextField
						:value.sync="formEquipo.system_name"
						:disabled="isReadonly"
						:label="t('employees', 'System name')" />

					<NcTextField
						:value.sync="formEquipo.serial_number"
						:disabled="isReadonly"
						:label="t('employees', 'Serial number')" />

					<div class="select-field">
						<label>{{ t('employees', 'Status') }}</label>

						<select v-model="formEquipo.status" :disabled="isReadonly">
							<option value="active">
								{{ t('employees', 'Active') }}
							</option>
							<option value="asignado">
								{{ t('employees', 'Assigned') }}
							</option>
							<option value="mantenimiento">
								{{ t('employees', 'Maintenance') }}
							</option>
							<option value="inactivo">
								{{ t('employees', 'Inactive') }}
							</option>
							<option value="baja">
								{{ t('employees', 'Retired') }}
							</option>
						</select>
					</div>

					<NcTextArea
						class="form-field--full"
						:value.sync="formEquipo.info"
						:disabled="isReadonly"
						:label="t('employees', 'Information')" />

					<div v-if="isReadonly" class="readonly-field form-field--full">
						<span>{{ t('employees', 'Assigned employee') }}</span>
						<strong>{{ empleadoAsignadoName(editingItem || {}) }}</strong>
						<small v-if="editingItem && editingItem.employee_uid">{{ editingItem.employee_uid }}</small>
					</div>
				</div>

				<!-- Crear soporte -->
				<div v-if="tab === 'soporte'" class="form-grid form-grid--single">
					<p v-if="selectedEquipo" class="modal-context">
						{{ displayValue(selectedEquipo.device_name) }} -
						{{ displayValue(selectedEquipo.serial_number) }}
					</p>

					<label class="native-field">
						<span>{{ t('employees', 'Category') }}</span>
						<select v-model="formSoporte.categoria">
							<option value="mantenimiento">{{ t('employees', 'Maintenance') }}</option>
							<option value="reparacion">{{ t('employees', 'Repair') }}</option>
							<option value="diagnostico">{{ t('employees', 'Diagnostics') }}</option>
							<option value="configuracion">{{ t('employees', 'Configuration') }}</option>
							<option value="otro">{{ t('employees', 'Other') }}</option>
						</select>
					</label>
					<label class="native-field">
						<span>{{ t('employees', 'Priority') }}</span>
						<select v-model="formSoporte.priority">
							<option value="baja">{{ t('employees', 'Low') }}</option>
							<option value="media">{{ t('employees', 'Medium') }}</option>
							<option value="alta">{{ t('employees', 'High') }}</option>
							<option value="critica">{{ t('employees', 'Critical') }}</option>
						</select>
					</label>
					<label class="native-field">
						<span>{{ t('employees', 'Support date') }}</span>
						<input v-model="formSoporte.date" type="datetime-local">
					</label>
					<SupportDurationFields v-model="formSoporte.duration_minutes" />

					<div class="readonly-grid">
						<div class="readonly-field">
							<span>{{ t('employees', 'Current user') }}</span>
							<strong>{{ displayValue(formSoporte.current_user) }}</strong>
						</div>

						<div class="readonly-field">
							<span>{{ t('employees', 'Support user') }}</span>
							<strong>{{ displayValue(formSoporte.user_support) }}</strong>
						</div>
					</div>

					<NcTextArea
						:value.sync="formSoporte.details"
						:label="t('employees', 'Details')" />
				</div>

				<div class="inventario-modal-actions">
					<NcButton @click="closeModal">
						{{ isReadonly ? t('employees', 'Close') : t('employees', 'Cancel') }}
					</NcButton>

					<NcButton
						v-if="!isReadonly"
						type="primary"
						:disabled="loading || (tab === 'soporte' && !validSupportForm)"
						@click="saveModal">
						{{ editMode ? t('employees', 'Update') : t('employees', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<NcModal
			v-if="showHistoryModal"
			class="inventario-nc-modal"
			size="large"
			:name="t('employees', 'Device history')"
			@close="closeHistory">
			<div class="inventario-modal history-modal">
				<div v-if="historyDevice" class="history-device-heading">
					<Laptop :size="24" />
					<div>
						<strong>{{ displayValue(historyDevice.device_name) }}</strong>
						<span>{{ displayValue(historyDevice.serial_number) }}</span>
					</div>
				</div>

				<div v-if="historyLoading && historyEntries.length === 0" class="loading-state">
					<NcLoadingIcon :size="42" />
				</div>

				<div v-else-if="historyError" class="history-error" role="alert">
					<p>{{ historyError }}</p>
					<NcButton @click="loadHistory(true)">
						{{ t('employees', 'Try again') }}
					</NcButton>
				</div>

				<NcEmptyContent
					v-else-if="historyEntries.length === 0"
					:name="t('employees', 'No history records found')" />

				<ol v-else class="history-list">
					<li v-for="entry in historyEntries" :key="entry.id" class="history-entry">
						<div class="history-entry__header">
							<span class="history-type">{{ movementTypeLabel(entry.type_movement) }}</span>
							<time :datetime="entry.date">{{ formatDateTime(entry.date) }}</time>
						</div>
						<dl class="history-details">
							<div v-if="entry.actor_name || entry.actor_uid">
								<dt>{{ t('employees', 'Actor') }}</dt>
								<dd>{{ entry.actor_name || entry.actor_uid }}</dd>
							</div>
							<div v-if="entry.employee_previous_name || entry.employee_previous_uid">
								<dt>{{ t('employees', 'Previous employee') }}</dt>
								<dd>{{ entry.employee_previous_name || entry.employee_previous_uid }}</dd>
							</div>
							<div v-if="entry.employee_new_name || entry.employee_new_uid">
								<dt>{{ t('employees', 'New employee') }}</dt>
								<dd>{{ entry.employee_new_name || entry.employee_new_uid }}</dd>
							</div>
							<div v-if="entry.status_previous">
								<dt>{{ t('employees', 'Previous status') }}</dt>
								<dd>{{ inventoryStatusLabel(entry.status_previous) }}</dd>
							</div>
							<div v-if="entry.status_new">
								<dt>{{ t('employees', 'New status') }}</dt>
								<dd>{{ inventoryStatusLabel(entry.status_new) }}</dd>
							</div>
						</dl>
						<p v-if="entry.description" class="history-description">
							{{ entry.description }}
						</p>
						<ul v-if="historyChanges(entry).length" class="history-changes">
							<li v-for="change in historyChanges(entry)" :key="change.field">
								<strong>{{ fieldLabel(change.field) }}:</strong>
								<span>{{ displayValue(change.anterior) }} → {{ displayValue(change.nuevo) }}</span>
							</li>
						</ul>
					</li>
				</ol>

				<div v-if="historyEntries.length < historyTotal && !historyError" class="history-load-more">
					<NcButton :disabled="historyLoading" @click="loadHistory(false)">
						<template #icon>
							<NcLoadingIcon v-if="historyLoading" :size="20" />
						</template>
						{{ t('employees', 'Load more') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- MODAL IMPORTAR -->
		<NcModal
			v-if="showImportModal"
			class="inventario-nc-modal"
			:name="importModalTitle"
			@close="closeImportModal">
			<div class="inventario-modal">
				<div class="import-box">
					<p class="modal-context">
						{{ importHelpText }}
					</p>

					<div class="import-actions">
						<NcButton @click="downloadImportTemplate">
							<template #icon>
								<Download :size="20" />
							</template>
							{{ t('employees', 'Download template') }}
						</NcButton>

						<label class="file-input-button">
							<input
								type="file"
								accept=".csv,text/csv"
								@change="handleImportFile">
							<span>{{ t('employees', 'Select CSV file') }}</span>
						</label>
					</div>

					<div v-if="importErrors.length > 0" class="import-errors">
						<strong>{{ t('employees', 'Import errors') }}</strong>

						<ul>
							<li
								v-for="(error, index) in importErrors"
								:key="index">
								{{ error }}
							</li>
						</ul>
					</div>

					<div v-if="importRows.length > 0" class="import-preview">
						<strong>
							{{ t('employees', 'Preview') }}:
							{{ importRows.length }}
							{{ t('employees', 'records') }}
						</strong>

						<div class="table-wrap">
							<table class="inventario-table">
								<thead>
									<tr>
										<th
											v-for="column in importColumns"
											:key="column.key">
											{{ column.label }}
										</th>
									</tr>
								</thead>

								<tbody>
									<tr
										v-for="(row, rowIndex) in importRows.slice(0, 10)"
										:key="rowIndex">
										<td
											v-for="column in importColumns"
											:key="column.key">
											{{ displayValue(row[column.key]) }}
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<p v-if="importRows.length > 10" class="modal-context">
							{{ t('employees', 'Only the first 10 records are shown.') }}
						</p>
					</div>
				</div>

				<div class="inventario-modal-actions">
					<NcButton @click="closeImportModal">
						{{ t('employees', 'Cancel') }}
					</NcButton>

					<NcButton
						type="primary"
						:disabled="importing || importRows.length === 0 || importErrors.length > 0"
						@click="saveImport">
						<template #icon>
							<NcLoadingIcon v-if="importing" :size="20" />
							<Upload v-else :size="20" />
						</template>
						{{ t('employees', 'Import') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import {
	NcAppContent,
	NcButton,
	NcTextField,
	NcTextArea,
	NcModal,
	NcEmptyContent,
	NcLoadingIcon,
	NcCheckboxRadioSwitch,
	NcActions,
	NcActionButton,
	NcAvatar,
} from '@nextcloud/vue'

import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Database from 'vue-material-design-icons/Database.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import History from 'vue-material-design-icons/History.vue'
import Laptop from 'vue-material-design-icons/Laptop.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Wrench from 'vue-material-design-icons/Wrench.vue'
import Download from 'vue-material-design-icons/Download.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import AccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import FilterOff from 'vue-material-design-icons/FilterOff.vue'
import CalendarMonth from 'vue-material-design-icons/CalendarMonth.vue'

import inventoryService from '../../../services/inventoryService.js'
import { parseInventoryDeviceId } from '../../../utils/inventoryRoute.js'
import { inventoryStatusLabel } from '../../../utils/inventoryStatusLabel.js'
import { formatSupportDuration, isValidSupportDate } from '../../../utils/supportDuration.js'
import SupportDurationFields from '../../../components/Inventory/SupportDurationFields.vue'
import permissionsMixin from '../../../mixins/permissions.js'
import { maintenanceCapabilities } from '../../../utils/maintenanceFormatters.js'

function localDateTimeValue() {
	const now = new Date()
	now.setMinutes(now.getMinutes() - now.getTimezoneOffset())
	return now.toISOString().slice(0, 16)
}

export default {
	name: 'Inventory',

	components: {
		NcAppContent,
		NcButton,
		NcTextField,
		NcTextArea,
		NcModal,
		NcEmptyContent,
		NcLoadingIcon,
		NcCheckboxRadioSwitch,
		NcActions,
		NcActionButton,
		NcAvatar,
		AccountOutline,
		CalendarMonth,
		Check,
		Close,
		Database,
		Download,
		Eye,
		FilterOff,
		History,
		Laptop,
		Magnify,
		Plus,
		Pencil,
		Refresh,
		Upload,
		Wrench,
		SupportDurationFields,
	},
	mixins: [permissionsMixin],
	inject: { Settings: { default: () => ({}) } },

	data() {
		return {
			tab: 'Team',
			search: '',
			loading: false,
			showModal: false,
			editMode: false,
			modalMode: 'create',
			editingItem: null,

			modelos: [],
			modelosCatalogo: [],
			Team: [],
			soporte: [],
			empleadosFiltro: [],
			totalTeams: 0,
			pageLimit: 25,
			pageOffset: 0,
			filters: {
				status: '',
				asignacion: '',
				idEmployee: '',
				idModel: '',
			},
			searchTimer: null,

			selectedEquipo: null,
			focusedDeviceId: null,
			lastProcessedDeviceParam: null,
			focusDeviceTimer: null,
			skipNextTabReload: false,
			showHistoryModal: false,
			historyDevice: null,
			historyEntries: [],
			historyTotal: 0,
			historyLimit: 20,
			historyLoading: false,
			historyError: '',

			formModelo: {
				brand: '',
				model: '',
				processor: '',
				ram: '',
				disk_drive: '',
				type: '',
				touch: false,
			},

			formEquipo: {
				id_model: '',
				device_name: '',
				system_name: '',
				serial_number: '',
				status: 'active',
				info: '',
			},

			formSoporte: {
				categoria: 'mantenimiento',
				priority: 'media',
				details: '',
				date: localDateTimeValue(),
				duration_minutes: null,
				current_user: '',
				user_support: '',
			},
			showImportModal: false,
			importing: false,
			importRows: [],
			importErrors: [],
		}
	},

	computed: {
		maintenanceCapabilities() {
			return maintenanceCapabilities(this.permissions, this.Settings)
		},
		validSupportForm() {
			return this.formSoporte.details.trim() !== ''
				&& Number.isInteger(this.formSoporte.duration_minutes)
				&& this.formSoporte.duration_minutes > 0
				&& isValidSupportDate(this.formSoporte.date)
		},
		tabs() {
			return [
				{
					id: 'Team',
					name: t('employees', 'Devices'),
					icon: 'Laptop',
				},
				{
					id: 'soporte',
					name: t('employees', 'Support'),
					icon: 'Wrench',
				},
			]
		},

		summaryItems() {
			return [
				{
					id: 'modelos',
					label: t('employees', 'Models'),
					value: this.modelos.length,
					icon: 'Database',
				},
				{
					id: 'Team',
					label: t('employees', 'Devices'),
					value: this.totalTeams,
					icon: 'Laptop',
				},
				{
					id: 'soporte',
					label: t('employees', 'Support'),
					value: this.soporte.length,
					icon: 'History',
				},
			]
		},

		primaryButtonText() {
			if (this.tab === 'modelos') return t('employees', 'New model')
			if (this.tab === 'Team') return t('employees', 'New device')
			return t('employees', 'New support record')
		},

		modalTitle() {
			if (this.isReadonly) {
				return t('employees', 'Device details')
			}
			if (!this.editMode) {
				return this.primaryButtonText
			}

			if (this.tab === 'modelos') {
				return t('employees', 'Edit model')
			}

			if (this.tab === 'Team') {
				return t('employees', 'Edit device')
			}

			return t('employees', 'Edit support record')
		},
		isReadonly() {
			return this.modalMode === 'readonly'
		},
		hasActiveFilters() {
			return this.search.trim() !== ''
				|| Object.values(this.filters).some(value => value !== '')
		},
		paginationLabel() {
			if (this.totalTeams === 0) return ''
			const start = this.pageOffset + 1
			const end = Math.min(this.pageOffset + this.Team.length, this.totalTeams)
			return t('employees', 'Showing {start}–{end} of {total} devices', {
				start,
				end,
				total: this.totalTeams,
			})
		},
		currentRows() {
			if (this.tab === 'modelos') {
				return this.modelos
			}

			if (this.tab === 'Team') {
				return this.Team
			}

			if (this.tab === 'soporte') {
				return this.soporte
			}

			return []
		},

		importModalTitle() {
			if (this.tab === 'modelos') {
				return t('employees', 'Import models')
			}

			if (this.tab === 'Team') {
				return t('employees', 'Import devices')
			}

			return t('employees', 'Import support records')
		},

		importHelpText() {
			if (this.tab === 'modelos') {
				return t('employees', 'Import computer models from a CSV file.')
			}

			if (this.tab === 'Team') {
				return t('employees', 'Import devices from a CSV file. You can use id_model or brand + model.')
			}

			return t('employees', 'Import support records for the selected device.')
		},

		importColumns() {
			if (this.tab === 'modelos') {
				return [
					{ key: 'brand', label: 'brand', required: true },
					{ key: 'model', label: 'model', required: true },
					{ key: 'processor', label: 'processor', required: false },
					{ key: 'ram', label: 'ram', required: false },
					{ key: 'disk_drive', label: 'disk_drive', required: false },
					{ key: 'type', label: 'type', required: false },
					{ key: 'touch', label: 'touch', required: false },
				]
			}

			if (this.tab === 'Team') {
				return [
					{ key: 'id_model', label: 'id_model', required: false },
					{ key: 'brand', label: 'brand', required: false },
					{ key: 'model', label: 'model', required: false },
					{ key: 'device_name', label: 'device_name', required: true },
					{ key: 'system_name', label: 'system_name', required: false },
					{ key: 'serial_number', label: 'serial_number', required: false },
					{ key: 'status', label: 'status', required: false },
					{ key: 'info', label: 'info', required: false },
				]
			}

			return [
				{ key: 'categoria', label: 'categoria', required: true },
				{ key: 'priority', label: 'priority', required: true },
				{ key: 'current_user', label: 'current_user', required: false },
				{ key: 'user_support', label: 'user_support', required: false },
				{ key: 'details', label: 'details', required: true },
				{ key: 'date', label: 'date', required: true },
				{ key: 'duration_minutes', label: 'duration_minutes', required: true },
			]
		},
		pageTitle() {
			if (this.tab === 'modelos') {
				return t('employees', 'Device models')
			}

			if (this.tab === 'soporte') {
				return t('employees', 'Support history')
			}

			return t('employees', 'IT Inventory')
		},

		pageSubtitle() {
			if (this.tab === 'modelos') {
				return t('employees', 'Manage the model catalog used by inventory devices.')
			}

			if (this.tab === 'soporte') {
				return t('employees', 'Review and register support records for selected devices.')
			}

			return t('employees', '{total} devices · Computer equipment, assignments and device status.', {
				total: this.totalTeams,
			})
		},
	},

	watch: {
		tab() {
			if (this.skipNextTabReload) {
				this.skipNextTabReload = false
				return
			}
			this.reload()
		},
		'$route.query.deviceId'(value) {
			if (value === undefined || value === null || value === '') {
				this.lastProcessedDeviceParam = null
				return
			}
			this.handleRouteDevice(value)
		},
	},

	async mounted() {
		this.restoreFilters()
		await this.reload()
		await this.handleRouteDevice(this.$route.query.deviceId)
	},

	beforeDestroy() {
		if (this.focusDeviceTimer) clearTimeout(this.focusDeviceTimer)
		if (this.searchTimer) clearTimeout(this.searchTimer)
	},

	methods: {
		t,
		inventoryStatusLabel,

		async handleRouteDevice(value) {
			if (value === undefined || value === null || value === '') return
			const parameterKey = Array.isArray(value) ? JSON.stringify(value) : String(value)
			if (parameterKey === this.lastProcessedDeviceParam) return
			this.lastProcessedDeviceParam = parameterKey

			const deviceId = parseInventoryDeviceId(value)
			if (!deviceId) {
				showWarning(t('employees', 'The requested inventory device ID is invalid.'))
				return
			}

			try {
				this.loading = true
				const response = await inventoryService.getEquipo(deviceId)
				const requestedDevice = response?.data ?? response
				if (!requestedDevice || Number(requestedDevice.id_team) !== deviceId) {
					showWarning(t('employees', 'The requested inventory device was not found.'))
					return
				}

				const needsReload = this.tab !== 'Team'
					|| this.hasActiveFilters
					|| !this.Team.some(equipo => Number(equipo.id_team) === deviceId)
				if (this.tab !== 'Team') this.skipNextTabReload = true
				this.tab = 'Team'
				this.search = ''
				this.filters = { status: '', asignacion: '', idEmployee: '', idModel: '' }
				this.pageOffset = 0
				this.persistFilters()
				if (needsReload) await this.reload()
				if (!this.Team.some(equipo => Number(equipo.id_team) === deviceId)) {
					this.Team = [requestedDevice, ...this.Team]
				}
				this.highlightDevice(deviceId)
				await this.openViewEquipo(requestedDevice)
			} catch (error) {
				showWarning(t('employees', 'The requested inventory device could not be opened.'))
			} finally {
				this.loading = false
			}
		},

		highlightDevice(deviceId) {
			if (this.focusDeviceTimer) clearTimeout(this.focusDeviceTimer)
			this.focusedDeviceId = deviceId
			this.$nextTick(() => {
				const row = this.$el.querySelector(`[data-device-id="${deviceId}"]`)
				if (row) {
					row.focus({ preventScroll: true })
					row.scrollIntoView({ behavior: 'smooth', block: 'center' })
				}
			})
			this.focusDeviceTimer = setTimeout(() => {
				this.focusedDeviceId = null
				this.focusDeviceTimer = null
			}, 4000)
		},

		setTab(tab) {
			this.tab = tab
		},

		scheduleSearch() {
			if (this.searchTimer) clearTimeout(this.searchTimer)
			this.searchTimer = setTimeout(() => this.applyFilters(), 450)
		},

		applyFilters() {
			if (this.searchTimer) {
				clearTimeout(this.searchTimer)
				this.searchTimer = null
			}
			this.pageOffset = 0
			this.persistFilters()
			return this.reload()
		},

		clearFilters() {
			this.search = ''
			this.filters = { status: '', asignacion: '', idEmployee: '', idModel: '' }
			return this.applyFilters()
		},

		nextPage() {
			if (this.pageOffset + this.pageLimit >= this.totalTeams) return
			this.pageOffset += this.pageLimit
			this.persistFilters()
			this.reload()
		},

		previousPage() {
			this.pageOffset = Math.max(0, this.pageOffset - this.pageLimit)
			this.persistFilters()
			this.reload()
		},

		persistFilters() {
			try {
				sessionStorage.setItem('employees.inventory.filters', JSON.stringify({
					search: this.search,
					filters: this.filters,
					offset: this.pageOffset,
				}))
			} catch (error) {
				// El almacenamiento de sesión puede estar bloqueado; los filtros siguen funcionando en memoria.
			}
		},

		restoreFilters() {
			try {
				const stored = JSON.parse(sessionStorage.getItem('employees.inventory.filters') || 'null')
				if (!stored || typeof stored !== 'object') return
				this.search = typeof stored.search === 'string' ? stored.search : ''
				this.filters = {
					status: String(stored.filters?.status || ''),
					asignacion: String(stored.filters?.asignacion || ''),
					idEmployee: String(stored.filters?.idEmployee || ''),
					idModel: String(stored.filters?.idModel || ''),
				}
				this.pageOffset = Number.isSafeInteger(stored.offset) && stored.offset >= 0 ? stored.offset : 0
			} catch (error) {
				this.pageOffset = 0
			}
		},

		async reload() {
			try {
				this.loading = true

				if (this.tab === 'modelos') {
					const res = await inventoryService.getModelos({ search: this.search })
					this.modelos = this.normalizeCollection(res)
				}

				if (this.tab === 'Team') {
					const [teamsRes] = await Promise.all([
						inventoryService.getTeams({
							search: this.search.trim() || undefined,
							status: this.filters.status || undefined,
							asignacion: this.filters.asignacion || undefined,
							id_employee: this.filters.idEmployee ? Number(this.filters.idEmployee) : undefined,
							id_model: this.filters.idModel ? Number(this.filters.idModel) : undefined,
							limit: this.pageLimit,
							offset: this.pageOffset,
						}),
						this.loadModelosCatalogo(),
					])

					this.Team = this.normalizeCollection(teamsRes)
					this.totalTeams = Number(teamsRes?.total ?? this.Team.length)
					this.empleadosFiltro = Array.isArray(teamsRes?.filter_options?.Employee)
						? teamsRes.filter_options.Employee
						: this.empleadosFiltro
					if (this.pageOffset >= this.totalTeams && this.pageOffset > 0) {
						this.pageOffset = Math.max(0, Math.floor(Math.max(0, this.totalTeams - 1) / this.pageLimit) * this.pageLimit)
						return this.reload()
					}
				}

				if (this.tab === 'soporte' && this.selectedEquipo) {
					const res = await inventoryService.getSoporteEquipo(this.selectedEquipo.id_team)
					this.soporte = this.normalizeCollection(res)
				}
			} catch (error) {
				showError(t('employees', 'Error loading inventory: {error}', {
					error: String(error),
				}))
			} finally {
				this.loading = false
			}
		},

		normalizeCollection(response) {
			if (Array.isArray(response)) {
				return response
			}

			if (Array.isArray(response?.data)) {
				return response.data
			}

			return []
		},

		displayValue(value) {
			return value === null || value === undefined || value === '' ? '-' : value
		},

		isTruthy(value) {
			return value === true
				|| value === 'true'
				|| value === 1
				|| value === '1'
		},

		modeloName(equipo) {
			const model = [equipo.brand, equipo.model].filter(Boolean).join(' ')

			return model || this.displayValue(equipo.id_model)
		},

		statusClass(status) {
			const normalized = String(status || '').toLowerCase()

			return {
				'status-pill--success': ['active', 'active', 'asignado'].includes(normalized),
				'status-pill--warning': ['mantenimiento', 'support', 'soporte'].includes(normalized),
				'status-pill--muted': !normalized || ['inactivo', 'inactive', 'baja'].includes(normalized),
			}
		},

		async openCreateModal() {
			if (this.tab === 'soporte' && !this.selectedEquipo) {
				showError(t('employees', 'Select a device first.'))
				return
			}

			this.editMode = false
			this.modalMode = 'create'
			this.editingItem = null

			if (this.tab === 'modelos') {
				this.resetModelo()
			}

			if (this.tab === 'Team') {
				this.resetEquipo()
				await this.loadModelosCatalogo()

				if (this.modelosCatalogo.length === 0) {
					showError(t('employees', 'Create a model before registering a device.'))
					return
				}
			}

			if (this.tab === 'soporte') {
				this.resetSoporte()
			}

			this.showModal = true
		},
		closeModal() {
			this.showModal = false
			this.editMode = false
			this.modalMode = 'create'
			this.editingItem = null
		},

		async saveModal() {
			if (this.isReadonly || this.loading) return
			this.loading = true
			try {
				if (this.tab === 'modelos') {
					if (this.editMode && this.editingItem?.id_model) {
						await this.updateModelo(this.editingItem.id_model, this.formModelo)
						showSuccess(t('employees', 'Model updated successfully.'))
					} else {
						await inventoryService.crearModelo(this.formModelo)
						showSuccess(t('employees', 'Model created successfully.'))
					}

					this.resetModelo()
				}

				if (this.tab === 'Team') {
					const payload = {
						...this.formEquipo,
						id_model: this.formEquipo.id_model ? Number(this.formEquipo.id_model) : null,
					}

					if (this.editMode && this.editingItem?.id_team) {
						await this.updateEquipo(this.editingItem.id_team, payload)
						showSuccess(t('employees', 'Device updated successfully.'))
					} else {
						await inventoryService.crearEquipo(payload)
						showSuccess(t('employees', 'Device created successfully.'))
					}

					this.resetEquipo()
				}

				if (this.tab === 'soporte') {
					this.resetSoporte(false)
					if (this.editMode && this.editingItem?.id_support) {
						await inventoryService.actualizarSoporte({
							...this.formSoporte,
							id_support: this.editingItem.id_support,
						})
						showSuccess(t('employees', 'Support record updated successfully.'))
					} else {
						await inventoryService.createSupport({
							...this.formSoporte,
							id_team: this.selectedEquipo.id_team,
						})
						showSuccess(t('employees', 'Support record created successfully.'))
					}
					this.resetSoporte()
				}

				this.closeModal()
				await this.reload()
			} catch (error) {
				showError(t('employees', 'Error saving inventory data: {error}', {
					error: String(error),
				}))
			} finally {
				this.loading = false
			}
		},
		async selectEquipoSoporte(equipo) {
			this.selectedEquipo = equipo
			this.tab = 'soporte'
			await this.reload()
		},

		openEditModelo(model) {
			this.editMode = true
			this.modalMode = 'edit'
			this.editingItem = model

			this.formModelo = {
				brand: model.brand || '',
				model: model.model || '',
				processor: model.processor || '',
				ram: model.ram || '',
				disk_drive: model.disk_drive || '',
				type: model.type || '',
				touch: this.isTruthy(model.touch),
			}

			this.showModal = true
		},

		async openEditEquipo(equipo) {
			this.editMode = true
			this.modalMode = 'edit'
			this.editingItem = equipo

			await this.loadModelosCatalogo()
			this.populateEquipoForm(equipo)
			this.showModal = true
		},

		async openViewEquipo(equipo) {
			this.editMode = false
			this.modalMode = 'readonly'
			this.editingItem = equipo
			await this.loadModelosCatalogo()
			this.populateEquipoForm(equipo)
			this.showModal = true
		},

		populateEquipoForm(equipo) {
			this.formEquipo = {
				id_model: equipo.id_model ? String(equipo.id_model) : '',
				device_name: equipo.device_name || '',
				system_name: equipo.system_name || '',
				serial_number: equipo.serial_number || '',
				status: equipo.status || 'active',
				info: equipo.info || '',
			}
		},

		async openHistory(equipo) {
			this.historyDevice = equipo
			this.historyEntries = []
			this.historyTotal = 0
			this.historyError = ''
			this.showHistoryModal = true
			await this.loadHistory(true)
		},

		closeHistory() {
			this.showHistoryModal = false
			this.historyDevice = null
			this.historyEntries = []
			this.historyTotal = 0
			this.historyError = ''
		},

		async loadHistory(reset = false) {
			if (!this.historyDevice || this.historyLoading) return
			if (reset) {
				this.historyEntries = []
				this.historyTotal = 0
			}
			this.historyLoading = true
			this.historyError = ''
			try {
				const response = await inventoryService.getHistoryEquipo(this.historyDevice.id_team, {
					limit: this.historyLimit,
					offset: this.historyEntries.length,
				})
				const entries = this.normalizeCollection(response)
				this.historyEntries = reset ? entries : [...this.historyEntries, ...entries]
				this.historyTotal = Number(response?.total ?? this.historyEntries.length)
			} catch (error) {
				this.historyError = t('employees', 'The device history could not be loaded. You can try again.')
			} finally {
				this.historyLoading = false
			}
		},

		movementTypeLabel(type) {
			const labels = {
				alta: t('employees', 'Registered'),
				asignacion: t('employees', 'Assignment'),
				reasignacion: t('employees', 'Reassignment'),
				desasignacion: t('employees', 'Unassignment'),
				cambio_estado: t('employees', 'Status change'),
				actualizacion: t('employees', 'Update'),
				mantenimiento: t('employees', 'Maintenance'),
				reparacion: t('employees', 'Repair'),
				baja: t('employees', 'Retirement'),
				note: t('employees', 'Note'),
			}
			return labels[type] || type || t('employees', 'Movement')
		},

		formatDateTime(value) {
			if (!value) return ''
			const date = new Date(String(value).replace(' ', 'T'))
			return Number.isNaN(date.getTime())
				? String(value)
				: new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
		},

		historyChanges(entry) {
			if (!entry?.changes || typeof entry.changes !== 'object' || Array.isArray(entry.changes)) return []
			return Object.entries(entry.changes).map(([field, values]) => ({
				field,
				anterior: values?.anterior,
				nuevo: values?.nuevo,
			}))
		},

		fieldLabel(field) {
			const labels = {
				id_model: t('employees', 'Model'),
				device_name: t('employees', 'Device name'),
				system_name: t('employees', 'System name'),
				serial_number: t('employees', 'Serial number'),
				info: t('employees', 'Information'),
				categoria_soporte: t('employees', 'Support category'),
				prioridad_soporte: t('employees', 'Support priority'),
			}
			return labels[field] || field
		},

		resetModelo() {
			this.formModelo = {
				brand: '',
				model: '',
				processor: '',
				ram: '',
				disk_drive: '',
				type: '',
				touch: false,
			}
		},

		resetEquipo() {
			this.formEquipo = {
				id_model: '',
				device_name: '',
				system_name: '',
				serial_number: '',
				status: 'active',
				info: '',
			}
		},

		resetSoporte(resetFields = true) {
			this.formSoporte = {
				categoria: resetFields ? 'mantenimiento' : this.formSoporte.categoria,
				priority: resetFields ? 'media' : this.formSoporte.priority,
				details: resetFields ? '' : this.formSoporte.details,
				date: resetFields ? localDateTimeValue() : this.formSoporte.date,
				duration_minutes: resetFields ? null : this.formSoporte.duration_minutes,
				current_user: this.getSelectedEquipoUser(),
				user_support: this.getCurrentSupportUser(),
			}
		},
		openEditSoporte(item) {
			const actionParts = String(item.action || '').split('·').map(value => value.trim())
			this.editMode = true
			this.modalMode = 'edit'
			this.editingItem = item
			this.formSoporte = {
				categoria: actionParts[0] || 'otro',
				priority: actionParts[1] || 'media',
				details: item.details || '',
				date: String(item.date || '').replace(' ', 'T').slice(0, 16),
				duration_minutes: item.duration_minutes == null ? null : Number(item.duration_minutes),
				current_user: item.current_user || '',
				user_support: item.user_support || '',
			}
			this.showModal = true
		},
		supportDurationLabel(value) {
			return value == null ? t('employees', 'Duration pending') : formatSupportDuration(Number(value))
		},
		async loadModelosCatalogo(force = false) {
			if (!force && this.modelosCatalogo.length > 0) {
				return
			}

			const res = await inventoryService.getModelos({})
			this.modelosCatalogo = this.normalizeCollection(res)
		},

		modeloLabel(model) {
			return [
				model.brand,
				model.model,
				model.processor,
				model.ram,
				model.disk_drive,
			]
				.filter(Boolean)
				.join(' - ')
		},

		async openImportModal() {
			this.importRows = []
			this.importErrors = []

			if (this.tab === 'Team') {
				await this.loadModelosCatalogo()
			}

			if (this.tab === 'soporte' && !this.selectedEquipo) {
				showError(t('employees', 'Select a device first.'))
				return
			}

			this.showImportModal = true
		},

		closeImportModal() {
			this.showImportModal = false
			this.importRows = []
			this.importErrors = []
		},

		handleImportFile(event) {
			const file = event.target.files?.[0]

			if (!file) {
				return
			}

			const reader = new FileReader()

			reader.onload = () => {
				try {
					const rows = this.parseCsv(String(reader.result || ''))
					this.importRows = this.prepareImportRows(rows)
					this.importErrors = this.validateImportRows(this.importRows)
				} catch (error) {
					this.importRows = []
					this.importErrors = [String(error)]
				}
			}

			reader.readAsText(file, 'UTF-8')
		},

		parseCsv(text) {
			const delimiter = this.detectCsvDelimiter(text)
			const rows = []
			let row = []
			let value = ''
			let inQuotes = false

			for (let i = 0; i < text.length; i += 1) {
				const char = text[i]
				const nextChar = text[i + 1]

				if (char === '"' && inQuotes && nextChar === '"') {
					value += '"'
					i += 1
					continue
				}

				if (char === '"') {
					inQuotes = !inQuotes
					continue
				}

				if (char === delimiter && !inQuotes) {
					row.push(value.trim())
					value = ''
					continue
				}

				if ((char === '\n' || char === '\r') && !inQuotes) {
					if (char === '\r' && nextChar === '\n') {
						i += 1
					}

					row.push(value.trim())

					if (row.some(cell => cell !== '')) {
						rows.push(row)
					}

					row = []
					value = ''
					continue
				}

				value += char
			}

			row.push(value.trim())

			if (row.some(cell => cell !== '')) {
				rows.push(row)
			}

			if (rows.length < 2) {
				throw new Error(t('employees', 'The CSV file does not contain records.'))
			}

			const headers = rows.shift().map(header => this.normalizeCsvKey(header))

			return rows.map(item => {
				const record = {}

				headers.forEach((header, index) => {
					record[header] = item[index] ?? ''
				})

				return record
			})
		},

		detectCsvDelimiter(text) {
			const firstLine = String(text || '').split(/\r?\n/).find(line => line.trim() !== '') || ''
			const commas = (firstLine.match(/,/g) || []).length
			const semicolons = (firstLine.match(/;/g) || []).length

			return semicolons > commas ? ';' : ','
		},

		normalizeCsvKey(value) {
			return String(value || '')
				.trim()
				.toLowerCase()
				.normalize('NFD')
				.replace(/[\u0300-\u036f]/g, '')
				.replace(/\s+/g, '_')
				.replace(/[^a-z0-9_]/g, '')
		},

		prepareImportRows(rows) {
			return rows.map(row => {
				if (this.tab === 'modelos') {
					return {
						brand: row.brand || '',
						model: row.model || '',
						processor: row.processor || row.cpu || '',
						ram: row.ram || '',
						disk_drive: row.disk_drive || row.almacenamiento || row.storage || '',
						type: row.type || '',
						touch: this.isTruthy(row.touch) ? '1' : '0',
					}
				}

				if (this.tab === 'Team') {
					return {
						id_model: row.id_model || this.findModeloId(row),
						brand: row.brand || '',
						model: row.model || '',
						device_name: row.device_name || row.dispositivo || row.device_name || '',
						system_name: row.system_name || row.hostname || row.system_name || '',
						serial_number: row.serial_number || row.serial || row.service_tag || '',
						status: row.status || 'active',
						info: row.info || row.information || '',
					}
				}

				return {
					action: row.action || row.action || '',
					current_user: row.current_user || '',
					user_support: row.user_support || '',
					details: row.details || row.details || '',
				}
			})
		},

		validateImportRows(rows) {
			const errors = []

			rows.forEach((row, index) => {
				const line = index + 2

				this.importColumns
					.filter(column => column.required)
					.forEach(column => {
						if (!row[column.key]) {
							errors.push(t('employees', 'Line {line}: missing required field {field}', {
								line,
								field: column.key,
							}))
						}
					})

				if (this.tab === 'Team' && !row.id_model) {
					errors.push(t('employees', 'Line {line}: model not found. Use id_model or valid brand + model.', {
						line,
					}))
				}
			})

			return errors
		},

		findModeloId(row) {
			const brand = String(row.brand || '').trim().toLowerCase()
			const model = String(row.model || '').trim().toLowerCase()

			if (!brand || !model) {
				return ''
			}

			const found = this.modelosCatalogo.find(item => {
				return String(item.brand || '').trim().toLowerCase() === brand
			&& String(item.model || '').trim().toLowerCase() === model
			})

			return found?.id_model || ''
		},

		async saveImport() {
			try {
				this.importing = true

				if (this.tab === 'modelos') {
					for (const row of this.importRows) {
						await inventoryService.crearModelo({
							brand: row.brand,
							model: row.model,
							processor: row.processor,
							ram: row.ram,
							disk_drive: row.disk_drive,
							type: row.type,
							touch: this.isTruthy(row.touch),
						})
					}
				}

				if (this.tab === 'Team') {
					for (const row of this.importRows) {
						await inventoryService.crearEquipo({
							id_model: row.id_model ? Number(row.id_model) : null,
							device_name: row.device_name,
							system_name: row.system_name,
							serial_number: row.serial_number,
							status: row.status || 'active',
							info: row.info,
						})
					}
				}

				if (this.tab === 'soporte') {
					for (const row of this.importRows) {
						await inventoryService.createSupport({
							id_team: this.selectedEquipo.id_team,
							categoria: row.categoria,
							priority: row.priority,
							details: row.details,
							date: row.date,
							duration_minutes: Number(row.duration_minutes),
						})
					}
				}

				showSuccess(t('employees', 'Import completed successfully.'))

				this.closeImportModal()
				await this.reload()
			} catch (error) {
				showError(t('employees', 'Error importing inventory data: {error}', {
					error: String(error),
				}))
			} finally {
				this.importing = false
			}
		},

		exportCurrentTab() {
			const columns = this.getExportColumns()
			const csv = this.toCsv(this.currentRows, columns)
			const date = new Date().toISOString().slice(0, 10)

			this.downloadTextFile(csv, `inventario_${this.tab}_${date}.csv`)
		},

		downloadImportTemplate() {
			const columns = this.importColumns.map(column => column.key)
			const example = this.getImportExampleRow()
			const csv = this.toCsv([example], columns)

			this.downloadTextFile(csv, `plantilla_${this.tab}.csv`)
		},

		getExportColumns() {
			if (this.tab === 'modelos') {
				return [
					'id_model',
					'brand',
					'model',
					'processor',
					'ram',
					'disk_drive',
					'type',
					'touch',
				]
			}

			if (this.tab === 'Team') {
				return [
					'id_team',
					'id_model',
					'brand',
					'model',
					'device_name',
					'system_name',
					'serial_number',
					'status',
					'employee_uid',
					'empleado_id',
					'info',
				]
			}

			return [
				'id_support',
				'id_team',
				'date',
				'action',
				'current_user',
				'user_support',
				'details',
			]
		},

		getImportExampleRow() {
			if (this.tab === 'modelos') {
				return {
					brand: 'Dell',
					model: 'Latitude 5420',
					processor: 'Intel Core i5',
					ram: '16 GB',
					disk_drive: '512 GB SSD',
					type: 'Laptop',
					touch: '0',
				}
			}

			if (this.tab === 'Team') {
				return {
					id_model: '',
					brand: 'Dell',
					model: 'Latitude 5420',
					device_name: 'LAP-001',
					system_name: 'CROWE-LAP-001',
					serial_number: 'ABC123456',
					status: 'active',
					info: 'Equipo disponible para asignación',
				}
			}

			return {
				categoria: 'mantenimiento',
				priority: 'media',
				current_user: this.getSelectedEquipoUser(),
				user_support: this.getCurrentSupportUser(),
				details: 'Limpieza general y revisión de actualizaciones',
				date: localDateTimeValue(),
				duration_minutes: 60,
			}
		},
		toCsv(rows, columns) {
			const header = columns.join(',')
			const body = rows.map(row => {
				return columns
					.map(column => this.escapeCsvValue(row[column]))
					.join(',')
			})

			return `\uFEFF${[header, ...body].join('\n')}`
		},

		escapeCsvValue(value) {
			const normalized = value === null || value === undefined ? '' : String(value)

			if (/[",\n\r]/.test(normalized)) {
				return `"${normalized.replace(/"/g, '""')}"`
			}

			return normalized
		},

		downloadTextFile(content, filename) {
			const blob = new Blob([content], {
				type: 'text/csv;charset=utf-8;',
			})

			const url = URL.createObjectURL(blob)
			const link = document.createElement('a')

			link.href = url
			link.download = filename
			document.body.appendChild(link)
			link.click()
			document.body.removeChild(link)

			URL.revokeObjectURL(url)
		},

		openModelos() {
			this.tab = 'modelos'
		},

		empleadoAsignadoName(equipo) {
			if (equipo.empleado_displayname) {
				return equipo.empleado_displayname
			}

			if (equipo.displayname) {
				return equipo.displayname
			}

			if (equipo.employee_uid) {
				return equipo.employee_uid
			}

			if (equipo.id_user) {
				return equipo.id_user
			}

			if (equipo.id_user) {
				return equipo.id_user
			}

			if (equipo.empleado_id) {
				return `#${equipo.empleado_id}`
			}

			if (equipo.id_employee) {
				return `#${equipo.id_employee}`
			}

			return t('employees', 'Unassigned')
		},

		getSelectedEquipoUser() {
			if (!this.selectedEquipo) {
				return ''
			}

			const value = this.empleadoAsignadoName(this.selectedEquipo)

			return value === t('employees', 'Unassigned') ? '' : value
		},

		getCurrentSupportUser() {
			const currentUser = window?.OC?.getCurrentUser?.()

			return currentUser?.displayName
				|| currentUser?.uid
				|| currentUser?.id
				|| window?.OC?.currentUser
				|| ''
		},

		async updateModelo(idModel, data) {
			if (typeof inventoryService.actualizarModelo === 'function') {
				return inventoryService.actualizarModelo({ id_model: idModel, ...data })
			}

			if (typeof inventoryService.updateModelo === 'function') {
				return inventoryService.updateModelo(idModel, data)
			}

			return this.updateByHttp(`/apps/employees/ActualizarInventoryModelo/${idModel}`, data)
		},

		async updateEquipo(idTeam, data) {
			if (typeof inventoryService.actualizarEquipo === 'function') {
				return inventoryService.actualizarEquipo({ id_team: idTeam, ...data })
			}

			if (typeof inventoryService.updateEquipo === 'function') {
				return inventoryService.updateEquipo(idTeam, data)
			}

			return this.updateByHttp(`/apps/employees/ActualizarInventoryEquipo/${idTeam}`, data)
		},

		async updateByHttp(url, data) {
			try {
				const response = await axios.put(generateUrl(url), data)
				return response.data
			} catch (error) {
				if ([404, 405].includes(error?.response?.status)) {
					const response = await axios.post(generateUrl(url), data)
					return response.data
				}

				throw error
			}
		},
	},
}
</script>

<style scoped>
.inventario-page {
	width: 100%;
	overflow: auto;
}

.inventario-header {
	display: flex;
	gap: 16px;
	justify-content: space-between;
	align-items: flex-start;
	padding: 24px 24px 12px;
}

.inventario-heading {
	min-width: 0;
}

.inventario-heading h2 {
	margin: 0 0 4px;
	font-size: 24px;
	font-weight: 700;
	line-height: 1.25;
}

.inventario-heading p {
	margin: 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.4;
}

.inventario-summary {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 8px;
	padding: 0 24px 16px;
}

.summary-item {
	display: grid;
	grid-template-columns: 24px minmax(0, 1fr) auto;
	gap: 8px;
	align-items: center;
	min-height: 44px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

.summary-item strong {
	color: var(--color-main-text);
	font-size: 18px;
}

.inventario-tabs {
	display: flex;
	gap: 4px;
	padding: 0 24px;
	border-bottom: 1px solid var(--color-border);
}

.inventario-tabs button {
	display: inline-flex;
	gap: 8px;
	align-items: center;
	min-height: 44px;
	margin: 0;
	border: none;
	border-bottom: 2px solid transparent;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	font-weight: 600;
	padding: 0 14px;
}

.inventario-tabs button:hover,
.inventario-tabs button:focus-visible {
	background-color: var(--color-background-hover);
}

.inventario-tabs button.active {
	border-bottom-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.inventario-toolbar {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: flex-end;
	padding: 16px 24px;
}

.search-field {
	width: min(420px, 100%);
}

.compact-filter {
	display: grid;
	gap: 4px;
	min-width: 150px;
}

.compact-filter span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
}

.compact-filter select {
	min-height: 44px;
	max-width: 230px;
	padding: 6px 30px 6px 10px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}

.inventario-card {
	margin: 0 24px 24px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	overflow: hidden;
}

.table-wrap {
	overflow-x: auto;
}

.loading-state {
	display: grid;
	place-items: center;
	min-height: 240px;
}

.inventario-table {
	width: 100%;
	border-collapse: collapse;
	table-layout: fixed;
}

.inventario-table--devices {
	min-width: 1080px;
}

.inventario-table th,
.inventario-table td {
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	vertical-align: top;
	overflow-wrap: anywhere;
}

.inventario-table th {
	font-weight: 700;
	color: var(--color-text-maxcontrast);
	background-color: var(--color-background-hover);
}

.inventario-table tbody tr:hover {
	background-color: var(--color-background-hover);
}

.inventario-table tbody tr.inventory-device-highlight {
	background-color: var(--color-primary-element-light);
	box-shadow: inset 4px 0 0 var(--color-primary-element);
	transition: background-color 180ms ease, box-shadow 180ms ease;
}

.inventario-table tbody tr:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.inventory-focus-announcement {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}

.inventario-table tr:last-child td {
	border-bottom: none;
}

.strong-cell {
	font-weight: 700;
}

.actions-cell {
	width: 1%;
	white-space: nowrap;
}

.employee-cell {
	display: flex;
	gap: 10px;
	align-items: center;
	min-width: 180px;
}

.employee-cell > div {
	display: grid;
	min-width: 0;
}

.employee-cell strong,
.employee-cell small {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.employee-cell small {
	color: var(--color-text-maxcontrast);
}

.neutral-avatar {
	display: grid;
	place-items: center;
	width: 36px;
	height: 36px;
	border-radius: 50%;
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.inventory-pagination {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
	padding: 12px 16px;
	border-top: 1px solid var(--color-border);
}

.inventory-pagination > div {
	display: flex;
	gap: 8px;
}

.status-pill,
.boolean-pill {
	display: inline-flex;
	gap: 4px;
	align-items: center;
	min-height: 24px;
	padding: 2px 8px;
	border-radius: 999px;
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.status-pill--success,
.boolean-pill.active {
	background-color: var(--color-success);
	color: var(--color-primary-element-text);
}

.status-pill--warning {
	background-color: var(--color-warning);
	color: var(--color-main-text);
}

.status-pill--muted {
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.selected-equipo {
	display: flex;
	gap: 12px;
	justify-content: space-between;
	align-items: center;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	background-color: var(--color-background-hover);
}

.selected-equipo div {
	display: grid;
	gap: 2px;
}

.selected-equipo span {
	color: var(--color-text-maxcontrast);
}

.inventario-modal {
	box-sizing: border-box;
	width: min(960px, calc(100vw - 64px));
	max-height: calc(100vh - 120px);
	padding: 28px;
	display: flex;
	flex-direction: column;
	gap: 18px;
	overflow-x: hidden;
	overflow-y: auto;
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(260px, 1fr));
	gap: 16px;
	align-items: start;
}

.form-grid--single {
	grid-template-columns: 1fr;
}

.modal-context {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.inventario-modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 20px;
}

.history-device-heading {
	display: flex;
	gap: 12px;
	align-items: center;
	padding-bottom: 14px;
	border-bottom: 1px solid var(--color-border);
}

.history-device-heading div {
	display: grid;
}

.history-device-heading span,
.history-entry time {
	color: var(--color-text-maxcontrast);
}

.history-error {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
	padding: 14px;
	border: 1px solid var(--color-error);
	border-radius: var(--border-radius-large, 8px);
}

.history-error p {
	margin: 0;
}

.history-list {
	display: grid;
	gap: 12px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.history-entry {
	display: grid;
	gap: 10px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
}

.history-entry__header {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
}

.history-type {
	display: inline-flex;
	width: fit-content;
	padding: 3px 9px;
	border-radius: 999px;
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-weight: 700;
}

.history-details {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 8px 16px;
	margin: 0;
}

.history-details div {
	display: grid;
	gap: 2px;
}

.history-details dt {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.history-details dd,
.history-description {
	margin: 0;
}

.history-changes {
	display: grid;
	gap: 5px;
	margin: 0;
	padding: 10px 10px 10px 28px;
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-background-hover);
}

.history-changes li span {
	margin-left: 6px;
}

.history-load-more {
	display: flex;
	justify-content: center;
}

@media (max-width: 900px) {
	.inventario-summary {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 700px) {
	.inventario-header {
		align-items: stretch;
		flex-direction: column;
	}

	.inventario-actions {
		align-self: flex-start;
	}

	.inventario-toolbar,
	.selected-equipo {
		align-items: stretch;
		flex-direction: column;
	}

	.compact-filter,
	.compact-filter select {
		width: 100%;
		max-width: none;
	}

	.inventory-pagination,
	.history-entry__header {
		align-items: stretch;
		flex-direction: column;
	}

	.history-details {
		grid-template-columns: 1fr;
	}

	.inventario-tabs {
		overflow-x: auto;
	}
}

.select-field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.select-field label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.select-field select {
	width: 100%;
	min-height: 44px;
	padding: 8px 12px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}

.select-field select:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

/* stylelint-disable no-descending-specificity */
.native-field {
	display: grid;
	gap: 4px;
}

.native-field span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.native-field select,
.native-field input {
	width: 100%;
	min-height: 44px;
	padding: 8px 12px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}
/* stylelint-enable no-descending-specificity */
.inventario-nc-modal :deep(.modal-container) {
	width: min(980px, calc(100vw - 48px)) !important;
	max-width: min(980px, calc(100vw - 48px)) !important;
}

.inventario-nc-modal :deep(.modal-container__content) {
	width: 100%;
	max-width: none;
	overflow: visible;
}
.form-grid :deep(.input-field),
.form-grid :deep(.textarea) {
	min-width: 0;
}

.form-grid :deep(textarea) {
	min-height: 120px;
	resize: vertical;
}
.form-field--full {
	grid-column: 1 / -1;
}
.import-box {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.import-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	align-items: center;
}

.file-input-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 44px;
	padding: 0 16px;
	border-radius: var(--border-radius-pill, 999px);
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-weight: 700;
	cursor: pointer;
}

.file-input-button input {
	display: none;
}

.import-errors {
	padding: 12px;
	border: 1px solid var(--color-error);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-error-hover);
	color: var(--color-main-text);
}

.import-errors ul {
	margin: 8px 0 0;
	padding-left: 20px;
}

.import-preview {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.inventario-actions {
	flex-shrink: 0;
}

.inventario-header :deep(.button-vue) {
	white-space: nowrap;
}

.row-actions {
	display: inline-flex;
	gap: 8px;
	align-items: center;
	white-space: nowrap;
}

.readonly-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	grid-column: 1 / -1;
}

.readonly-field {
	display: grid;
	gap: 4px;
	min-height: 44px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-background-hover);
}

.readonly-field.readonly-field span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.readonly-field strong {
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
}

@media (max-width: 700px) {
	.readonly-grid {
		grid-template-columns: 1fr;
	}
}
</style>
