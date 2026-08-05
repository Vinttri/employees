<template>
	<NcAppContent :name="t('employees', 'Purchases')">
		<div class="purchases-page">
			<NcModal v-if="showForm"
				class="purchase-request-modal"
				size="large"
				:name="requestModalTitle"
				@close="closeRequestModal">
				<div class="purchase-modal">
					<div class="modal-header">
						<p class="section-label">
							{{ t('employees', 'Purchases module') }}
						</p>
						<h2>{{ requestModalTitle }}</h2>
						<p>
							{{ t('employees', 'Register the purchase request information and add at least one concept.')
							}}
						</p>
					</div>

					<div class="modal-block">
						<p class="section-label">
							{{ t('employees', 'Requester data') }}
						</p>

						<NcNoteCard type="info" class="section-note">
							{{ t('employees', 'Select the requester to complete the name, department, position and direct manager automatically.') }}
						</NcNoteCard>

						<div class="form-grid">
							<NcSelect v-if="canSelectRequester"
								v-model="selectedRequester"
								class="span-2"
								:input-label="t('employees', 'Requester')"
								:options="requesterOptions"
								:clearable="true"
								@input="fillRequesterData"
								@option:selected="fillRequesterData" />

							<div v-else class="requester-locked-card span-2">
								<NcAvatar :user="currentRequester?.uid || ''"
									:display-name="currentRequester?.displayname || form.requester_name || ''"
									:size="44"
									:show-user-status="false"
									:show-user-status-compact="false" />

								<div class="requester-locked-info">
									<strong>{{ form.requester_name || t('employees', 'Current user') }}</strong>
									<span>{{ t('employees', 'This request will be created using your employee profile.')
									}}</span>
								</div>
							</div>

							<NcTextField :value.sync="form.requester_name"
								:disabled="contextLoaded && !canSelectRequester"
								:label="t('employees', 'Name')" />

							<NcTextField :value.sync="form.requester_department"
								:disabled="contextLoaded && !canSelectRequester"
								:label="t('employees', 'Department')" />

							<NcTextField :value.sync="form.requester_position"
								:disabled="contextLoaded && !canSelectRequester"
								:label="t('employees', 'Position')" />

							<div class="manager-preview">
								<span class="field-label">
									{{ t('employees', 'Direct manager') }}
								</span>

								<div class="manager-card" :class="{ 'manager-card--empty': !form.jefe_directo_uid }">
									<NcAvatar v-if="form.jefe_directo_uid"
										:user="form.jefe_directo_uid"
										:display-name="form.direct_manager_name || form.jefe_directo_uid"
										:size="44"
										:show-user-status="false"
										:show-user-status-compact="false" />

									<NcAvatar v-else
										display-name="?"
										:size="44"
										:show-user-status="false"
										:show-user-status-compact="false" />

									<div class="manager-info">
										<strong>{{ form.direct_manager_name || t('employees', 'No direct manager selected') }}</strong>
										<span v-if="form.jefe_directo_uid">@{{ form.jefe_directo_uid }}</span>
										<span v-else>{{ t('employees', 'Select a requester first') }}</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="modal-block">
						<p class="section-label">
							{{ t('employees', 'Purchase data') }}
						</p>

						<NcNoteCard type="info" class="section-note">
							{{ t('employees', 'Describe what will be purchased, when it is needed and the business reason for the request.') }}
						</NcNoteCard>

						<div class="form-grid">
							<NcTextField required
								class="span-2"
								:value.sync="form.title"
								:label="t('employees', 'Title')" />

							<NcSelect v-model="selectedTipoCompra"
								:input-label="t('employees', 'Purchase type')"
								:options="tipoCompraOptions"
								:clearable="false" />

							<NcSelect v-model="selectedUsoCompra"
								:input-label="t('employees', 'Purchase use')"
								:options="usoCompraOptions"
								:clearable="false" />

							<NcSelect v-model="selectedPriority"
								:input-label="t('employees', 'Priority')"
								:options="priorityOptions"
								:clearable="false" />

							<NcSelect v-model="selectedCurrency"
								:input-label="t('employees', 'Currency')"
								:options="currencyOptions"
								:clearable="false" />

							<div class="date-field">
								<span class="field-label">
									{{ t('employees', 'Required date') }}
								</span>
								<NcDateTimePicker v-model="requiredDateValue"
									type="date"
									:placeholder="t('employees', 'Select a required date')" />
							</div>

							<div class="switch-field">
								<span>{{ t('employees', 'Warranty') }}</span>
								<NcCheckboxRadioSwitch :checked="Boolean(form.warranty)"
									type="switch"
									@update:checked="form.warranty = Boolean($event)">
									{{ form.warranty ? t('employees', 'Yes') : t('employees', 'No') }}
								</NcCheckboxRadioSwitch>
							</div>

							<NcTextArea class="span-2"
								resize="vertical"
								:value.sync="form.information"
								:label="t('employees', 'Information')" />

							<NcTextArea class="span-2"
								resize="vertical"
								:value.sync="form.reason"
								:label="t('employees', 'Reason')" />
						</div>
					</div>

					<div class="modal-section-head">
						<div>
							<p class="section-label">
								{{ t('employees', 'Requisition') }}
							</p>
							<h3>{{ t('employees', 'Requested products or services') }}</h3>
							<p class="section-description">
								{{ t('employees', 'Each requested product can include its supplier, delivery and technical specifications.') }}
							</p>
						</div>

						<NcButton @click="addDetalle">
							{{ t('employees', 'Add concept') }}
						</NcButton>
					</div>

					<NcNoteCard type="info" class="concepts-note">
						{{ t('employees', 'Add one card per product or service. VAT is calculated automatically at 16% based on the subtotal.') }}
					</NcNoteCard>

					<div class="concepts-list">
						<div v-for="(concepto, index) in form.details" :key="index" class="concept-card">
							<div class="concept-card-header">
								<div class="concept-heading">
									<div class="concept-number">
										{{ index + 1 }}
									</div>

									<div>
										<strong>{{ t('employees', 'Concept') }} {{ index + 1 }}</strong>
										<span>{{ formatMoney(getDetalleTotal(concepto)) }}</span>
									</div>
								</div>

								<NcButton :disabled="form.details.length === 1" @click="removeDetalle(index)">
									{{ t('employees', 'Remove') }}
								</NcButton>
							</div>

							<div class="concept-fields">
								<NcTextField class="span-2"
									:value.sync="concepto.description"
									:label="t('employees', 'Description')" />

								<NcTextField :value.sync="concepto.quantity"
									type="number"
									min="1"
									step="1"
									:label="t('employees', 'Quantity')" />

								<NcTextField :value.sync="concepto.unit" :label="t('employees', 'Unit')" />

								<NcTextField :value.sync="concepto.price_estimated"
									type="number"
									min="0"
									step="0.01"
									:label="t('employees', 'Price without VAT')" />

								<NcTextField :value="formatMoney(getDetalleIva(concepto))"
									:label="t('employees', 'VAT (16%)')"
									:disabled="true" />

								<NcTextField :value.sync="concepto.supplier_name"
									:label="t('employees', 'Supplier')" />

								<NcTextField :value.sync="concepto.attention" :label="t('employees', 'Attention')" />

								<NcTextField :value.sync="concepto.delivery" :label="t('employees', 'Delivery')" />

								<NcTextField :value.sync="concepto.brand_model"
									:label="t('employees', 'Brand / Model')" />

								<NcTextArea class="span-2"
									resize="vertical"
									:value.sync="concepto.specifications"
									:label="t('employees', 'Specifications')" />

								<div class="concept-summary span-2">
									<div>
										<span>{{ t('employees', 'Subtotal') }}</span>
										<strong>{{ formatMoney(getDetalleSubtotal(concepto)) }}</strong>
									</div>

									<div>
										<span>{{ t('employees', 'Total') }}</span>
										<strong>{{ formatMoney(getDetalleTotal(concepto)) }}</strong>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="modal-block">
						<p class="section-label">
							{{ t('employees', 'Administration') }}
						</p>

						<div class="form-grid">
							<NcTextField :value.sync="form.office_percentage" :label="t('employees', 'Office %')" />

							<NcTextField :value.sync="form.employee_percentage" :label="t('employees', 'Employee %')" />

							<NcSelect v-model="selectedTipoPago"
								:input-label="t('employees', 'Payment type')"
								:options="tipoPagoOptions"
								:clearable="true" />

							<NcTextField :value.sync="form.installments" :label="t('employees', 'Fortnights')" />

							<NcTextArea class="span-2"
								resize="vertical"
								:value.sync="form.admin_comments"
								:label="t('employees', 'Administration comments')" />
						</div>
					</div>

					<NcNoteCard type="info" class="purchase-total-card">
						<div class="purchase-total">
							<div>
								<span>{{ t('employees', 'Subtotal') }}</span>
								<strong>{{ formatMoney(totalEstimado) }}</strong>
							</div>

							<div>
								<span>{{ t('employees', 'VAT') }}</span>
								<strong>{{ formatMoney(totalIva) }}</strong>
							</div>

							<div>
								<span>{{ t('employees', 'Total') }}</span>
								<strong>{{ formatMoney(totalIncludingTax) }}</strong>
							</div>
						</div>
					</NcNoteCard>

					<div class="modal-actions">
						<NcButton @click="closeRequestModal">
							{{ t('employees', 'Cancel') }}
						</NcButton>

						<NcButton type="primary" :disabled="loading || !isFormValid" @click="crear">
							{{ loading ? t('employees', 'Saving...') : t('employees', 'Save request') }}
						</NcButton>
					</div>
				</div>
			</NcModal>

			<div class="purchases-layout">
				<section class="panel-card requests-panel">
					<div class="requests-header">
						<div>
							<p class="section-label">
								{{ t('employees', 'Tracking') }}
							</p>
							<h2>{{ listTitle }}</h2>
							<p class="requests-description">
								{{ listDescription }}
							</p>
						</div>

						<div class="header-actions">
							<NcButton :disabled="loading" :title="t('employees', 'Refresh')" @click="cargarSolicitudes">
								<template #icon>
									<Refresh :size="20" />
								</template>
								{{ t('employees', 'Refresh') }}
							</NcButton>

							<NcButton v-if="canCreatePurchaseRequest" type="primary" @click="toggleForm">
								<template #icon>
									<Plus :size="20" />
								</template>
								{{ t('employees', 'New request') }}
							</NcButton>
						</div>
					</div>

					<div class="filters-toolbar">
						<div class="filters">
							<NcSelect v-model="selectedEstadoFiltro"
								class="status-filter"
								:input-label="t('employees', 'Status filter')"
								:options="estadoFiltroOptions"
								:clearable="false" />

							<NcCheckboxRadioSwitch v-if="canToggleShowOnlyMine"
								:checked="showOnlyMine"
								type="switch"
								@update:checked="onToggleShowOnlyMine">
								{{ t('employees', 'Show only my requests') }}
							</NcCheckboxRadioSwitch>
						</div>
						<span class="result-count">{{ paginationLabel }}</span>
					</div>

					<NcNoteCard v-if="loadError" type="error" class="load-error">
						<div class="load-error-content">
							<span>{{ loadError }}</span>
							<NcButton @click="cargarSolicitudes">
								{{ t('employees', 'Try again') }}
							</NcButton>
						</div>
					</NcNoteCard>

					<div class="table-area" :class="{ 'table-area--loading': loading }">
						<div v-if="loading" class="table-loading" role="status">
							<NcLoadingIcon :size="32" />
							<span>{{ t('employees', 'Loading...') }}</span>
						</div>

						<NcEmptyContent v-if="!loading && solicitudes.length === 0"
							:name="t('employees', 'No purchase requests found')"
							:description="t('employees', 'Try changing the status filter or create a new request.')">
							<template #icon>
								<CartOutline />
							</template>
						</NcEmptyContent>

						<div v-else-if="solicitudes.length" class="table-scroll">
							<table class="purchases-table">
								<thead>
									<tr>
										<th>{{ t('employees', 'Folio') }}</th>
										<th>{{ t('employees', 'Title') }}</th>
										<th>{{ t('employees', 'Requester') }}</th>
										<th>{{ t('employees', 'Amount') }}</th>
										<th>{{ t('employees', 'Status') }}</th>
										<th>{{ t('employees', 'Document status') }}</th>
										<th>{{ t('employees', 'Date') }}</th>
										<th>{{ t('employees', 'Actions') }}</th>
									</tr>
								</thead>

								<tbody>
									<tr v-for="item in solicitudes"
										:key="item.id_request"
										class="request-row"
										@click="verDetalle(item.id_request)">
										<td><strong>{{ item.reference }}</strong></td>

										<td class="request-title" :title="item.title">
											{{ item.title }}
										</td>

										<td>{{ formatRequesterLabel(item) }}</td>

										<td>{{ formatMoney(item.amount_estimated, item.currency) }}</td>

										<td>
											<span :class="['badge', `status-${item.status}`]">
												{{ formatEstado(item.status) }}
											</span>
										</td>

										<td>
											<span :class="['badge', `document-${getEstadoDocumental(item)}`]">
												{{ formatEstadoDocumental(item) }}
											</span>
										</td>

										<td>{{ formatDateTime(item.created_at) }}</td>

										<td class="col-actions">
											<div class="row-actions table-actions" @click.stop>
												<NcButton :aria-label="t('employees', 'View request')"
													:title="t('employees', 'View request')"
													@click="verDetalle(item.id_request)">
													<template #icon>
														<EyeOutline :size="20" />
													</template>
												</NcButton>

												<NcActions :aria-label="t('employees', 'More actions')"
													:force-menu="true">
													<NcActionButton v-if="canEditRequest(item)"
														@click="editar(item.id_request)">
														<template #icon>
															<PencilOutline :size="20" />
														</template>
														{{ t('employees', 'Edit') }}
													</NcActionButton>

													<NcActionButton v-if="canCancelRequest(item)"
														@click="cancelar(item.id_request)">
														<template #icon>
															<DeleteOutline :size="20" />
														</template>
														{{ t('employees', 'Delete') }}
													</NcActionButton>

													<NcActionButton v-if="canSendRequest(item)"
														@click="enviar(item.id_request)">
														<template #icon>
															<SendOutline :size="20" />
														</template>
														{{ t('employees', 'Send') }}
													</NcActionButton>
												</NcActions>
											</div>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<div v-if="totalRequests > 0" class="pagination-bar">
						<span>{{ paginationLabel }}</span>
						<div>
							<NcButton :disabled="loading || currentPage === 1" @click="changePage(currentPage - 1)">
								{{ t('employees', 'Previous') }}
							</NcButton>
							<NcButton :disabled="loading || currentPage >= totalPages" @click="changePage(currentPage + 1)">
								{{ t('employees', 'Next') }}
							</NcButton>
						</div>
					</div>
				</section>
				<aside class="purchases-side-panel">
					<h3>{{ t('employees', 'Summary') }}</h3>
					<div class="stats-grid">
						<div class="stat-card">
							<div class="stat-icon">
								<CartOutline :size="22" />
							</div>
							<div>
								<span>{{ t('employees', 'Total requests') }}</span>
								<strong>{{ summary.total }}</strong>
							</div>
						</div>

						<div class="stat-card">
							<div class="stat-icon">
								<FileChartOutline :size="22" />
							</div>
							<div>
								<span>{{ t('employees', 'Pending approval') }}</span>
								<strong>{{ summary.pending }}</strong>
							</div>
						</div>

						<div class="stat-card">
							<div class="stat-icon">
								<FileChartOutline :size="22" />
							</div>
							<div>
								<span>{{ t('employees', 'Estimated amount') }}</span>
								<strong>{{ formatMoney(summary.estimatedAmount) }}</strong>
							</div>
						</div>
					</div>
				</aside>
			</div>
			<NcModal v-if="detalle"
				class="purchase-detail-modal"
				size="large"
				:name="detalle.solicitud.reference || t('employees', 'Purchase request detail')"
				@close="closeDetalle">
				<PurchaseRequestDetails
					:detail="detalle"
					:flow="approvalFlow"
					:flow-loading="approvalFlowLoading"
					:flow-error="approvalFlowError"
					:processing="actionModal.loading || loading"
					:permissions="purchasePermissions"
					:current-user-id="currentUserId"
					@export-pdf="abrirDocumento(detalle.solicitud.id_request)"
					@save-pdf="guardarDocumento(detalle.solicitud.id_request)"
					@upload-signed="seleccionarFirmado(detalle.solicitud.id_request)"
					@view-signed="abrirDocumentoFirmado(detalle.solicitud.id_request)"
					@edit="editarDetalle"
					@send="enviar(detalle.solicitud.id_request)"
					@approve="autorizar(detalle.solicitud.id_request)"
					@reject="rechazar(detalle.solicitud.id_request)"
					@cancel="cancelar(detalle.solicitud.id_request)"
					@close="closeDetalle" />
			</NcModal>
		</div>
		<NcModal v-if="actionModal.show"
			class="purchase-action-modal"
			:name="actionModal.title"
			@close="closeActionModal">
			<div class="action-modal">
				<div class="action-modal-header">
					<p class="section-label">
						{{ t('employees', 'Purchase action') }}
					</p>

					<h2>{{ actionModal.title }}</h2>

					<p>
						{{ actionModal.description }}
					</p>
				</div>

				<NcNoteCard :type="actionModal.noteType" class="action-modal-note">
					{{ actionModal.note }}
				</NcNoteCard>

				<NcTextArea class="action-modal-comment"
					resize="vertical"
					:value.sync="actionModal.comment"
					:label="actionModal.commentLabel" />

				<p v-if="actionModal.requireComment && actionModalIsInvalid" class="action-modal-error">
					{{ t('employees', 'A comment is required for this action.') }}
				</p>

				<div class="action-modal-actions">
					<NcButton :disabled="actionModal.loading" @click="closeActionModal">
						{{ t('employees', 'Cancel') }}
					</NcButton>

					<NcButton :type="actionModal.confirmType"
						:disabled="actionModal.loading || actionModalIsInvalid"
						@click="submitActionModal">
						{{ actionModal.loading ? t('employees', 'Processing...') : actionModal.confirmLabel }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import SendOutline from 'vue-material-design-icons/SendOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import CartOutline from 'vue-material-design-icons/CartOutline.vue'
import FileChartOutline from 'vue-material-design-icons/FileChartOutline.vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import {
	NcActionButton,
	NcActions,
	NcAppContent,
	NcAvatar,
	NcButton,
	NcCheckboxRadioSwitch,
	NcDateTimePicker,
	NcEmptyContent,
	NcLoadingIcon,
	NcModal,
	NcNoteCard,
	NcSelect,
	NcTextArea,
	NcTextField,
} from '@nextcloud/vue'

import { buildPurchaseListParams } from '../../../utils/purchasesFilters.js'
import {
	canApprovePurchaseFlow,
	canRejectPurchaseFlow,
	createSingleFlight,
} from '../../../utils/purchaseApprovalFlow.js'
import PurchaseRequestDetails from './PurchaseRequestDetails.vue'

import {
	actualizarSolicitud,
	autorizarSolicitud,
	cancelarSolicitud,
	crearSolicitud,
	enviarAutorizacion,
	listarSolicitudes,
	obtenerFlujoSolicitud,
	obtenerSolicitud,
	rechazarSolicitud,
	obtenerContextoPurchases,
	guardarDocumentoSolicitud,
	subirDocumentoFirmadoSolicitud,
} from '../../../services/purchasesService.js'

const IVA_RATE = 0.16
const SHOW_ONLY_MINE_KEY = 'employees.purchases.showOnlyMine'

function roundMoney(value) {
	return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100
}

export default {
	name: 'MyRequests',

	components: {
		NcActionButton,
		NcActions,
		NcAppContent,
		NcAvatar,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDateTimePicker,
		NcEmptyContent,
		NcLoadingIcon,
		NcNoteCard,
		NcSelect,
		NcTextArea,
		NcTextField,
		CartOutline,
		FileChartOutline,
		EyeOutline,
		PencilOutline,
		DeleteOutline,
		SendOutline,
		Plus,
		Refresh,
		NcModal,
		PurchaseRequestDetails,
	},

	data() {
		return {
			loading: false,
			showForm: false,
			showOnlyMine: true,
			hasSavedShowOnlyMinePreference: false,
			contextLoaded: false,
			currentUserId: '',
			solicitudes: [],
			loadError: '',
			requestSequence: 0,
			page: 1,
			pageSize: 20,
			totalRequests: 0,
			summary: {
				total: 0,
				pending: 0,
				estimatedAmount: 0,
			},
			detalle: null,
			approvalFlow: null,
			approvalFlowLoading: false,
			approvalFlowError: '',
			actionSingleFlight: createSingleFlight(),
			form: this.getEmptyForm(),
			priorityOptions: [
				{ id: 'baja', label: t('employees', 'Low') },
				{ id: 'normal', label: t('employees', 'Normal') },
				{ id: 'alta', label: t('employees', 'High') },
				{ id: 'urgente', label: t('employees', 'Urgent') },
			],
			currencyOptions: [
				{ id: 'MXN', label: 'MXN' },
				{ id: 'USD', label: 'USD' },
			],
			tipoCompraOptions: [
				{ id: 'refaccion', label: t('employees', 'Spare part') },
				{ id: 'equipo', label: t('employees', 'Equipment') },
				{ id: 'servicio', label: t('employees', 'Service') },
				{ id: 'software', label: t('employees', 'Software') },
				{ id: 'otro', label: t('employees', 'Other') },
			],
			usoCompraOptions: [
				{ id: 'empresa', label: t('employees', 'Company') },
				{ id: 'personal', label: t('employees', 'Personal') },
			],
			tipoPagoOptions: [
				{ id: 'contado', label: t('employees', 'Cash') },
				{ id: 'nomina', label: t('employees', 'Payroll discount') },
				{ id: 'transferencia', label: t('employees', 'Bank transfer') },
				{ id: 'otro', label: t('employees', 'Other') },
			],
			requesterOptions: [],
			selectedRequesterUid: '',
			areasCatalog: [],
			positionsCatalog: [],
			empleadosCatalog: [],
			estadoFiltroId: 'todos',
			estadoFiltroOptions: [
				{ id: 'todos', label: t('employees', 'All statuses') },
				{ id: 'borrador', label: t('employees', 'Draft') },
				{ id: 'pendiente_autorizacion', label: t('employees', 'Pending approval') },
				{ id: 'autorizada', label: t('employees', 'Approved') },
				{ id: 'rechazada', label: t('employees', 'Rejected') },
				{ id: 'cancelada', label: t('employees', 'Cancelled') },
			],
			actionModal: this.getEmptyActionModal(),
			editingSolicitudId: null,
			canSelectRequester: false,
			currentRequester: null,
			purchasePermissions: {
				can_create: false,
				can_view_all: false,
				can_approve: false,
				can_process_purchase: false,
				can_select_requester: false,
			},
			firmadoSolicitudId: null,
		}
	},

	computed: {
		canCreatePurchaseRequest() {
			return this.purchasePermissions.can_create
		},

		canApprovePurchaseRequest() {
			return this.purchasePermissions.can_approve
		},

		canProcessPurchaseRequest() {
			return this.purchasePermissions.can_process_purchase
		},

		listTitle() {
			return this.canToggleShowOnlyMine && !this.showOnlyMine
				? t('employees', 'Purchase requests')
				: t('employees', 'My requests')
		},

		listDescription() {
			return this.canToggleShowOnlyMine && !this.showOnlyMine
				? t('employees', 'Review purchase requests available to your role.')
				: t('employees', 'Review the status of your purchase requests.')
		},

		totalPages() {
			return Math.max(1, Math.ceil(this.totalRequests / this.pageSize))
		},

		currentPage() {
			return Math.min(this.page, this.totalPages)
		},

		paginationLabel() {
			if (this.totalRequests === 0) {
				return t('employees', '0 requests')
			}

			const start = (this.currentPage - 1) * this.pageSize + 1
			const end = Math.min(this.currentPage * this.pageSize, this.totalRequests)
			return t('employees', 'Showing {start}–{end} of {total} requests', {
				start,
				end,
				total: this.totalRequests,
			})
		},
		totalEstimado() {
			return this.form.details.reduce((total, item) => {
				return total + this.getDetalleSubtotal(item)
			}, 0)
		},

		totalIva() {
			return this.form.details.reduce((total, item) => {
				return total + this.getDetalleIva(item)
			}, 0)
		},

		totalIncludingTax() {
			return this.totalEstimado + this.totalIva
		},

		isFormValid() {
			const hasTitle = String(this.form.title || '').trim().length > 0
			const hasConcept = this.form.details.some((detalle) => {
				return String(detalle.description || '').trim().length > 0
			})

			return hasTitle && hasConcept
		},

		isEditingRequest() {
			return Boolean(this.editingSolicitudId)
		},

		requestModalTitle() {
			return this.isEditingRequest
				? t('employees', 'Edit purchase request')
				: t('employees', 'New purchase request')
		},

		selectedRequester: {
			get() {
				return this.requesterOptions.find((option) => {
					return option.uid === this.selectedRequesterUid
				}) || null
			},

			set(value) {
				this.selectedRequesterUid = value?.uid || ''
			},
		},

		selectedPriority: {
			get() {
				return this.priorityOptions.find((option) => {
					return option.id === this.form.priority
				}) || this.priorityOptions.find((option) => {
					return option.id === 'normal'
				})
			},

			set(value) {
				this.form.priority = value?.id || 'normal'
			},
		},

		selectedCurrency: {
			get() {
				return this.currencyOptions.find((option) => {
					return option.id === this.form.currency
				}) || this.currencyOptions.find((option) => {
					return option.id === 'MXN'
				})
			},

			set(value) {
				this.form.currency = value?.id || 'MXN'
			},
		},

		selectedTipoCompra: {
			get() {
				return this.tipoCompraOptions.find((option) => {
					return option.id === this.form.purchase_type
				}) || this.tipoCompraOptions[0]
			},

			set(value) {
				this.form.purchase_type = value?.id || 'refaccion'
			},
		},

		selectedUsoCompra: {
			get() {
				return this.usoCompraOptions.find((option) => {
					return option.id === this.form.purchase_use
				}) || this.usoCompraOptions[0]
			},

			set(value) {
				this.form.purchase_use = value?.id || 'empresa'
			},
		},

		selectedTipoPago: {
			get() {
				return this.tipoPagoOptions.find((option) => {
					return option.id === this.form.payment_type
				}) || null
			},

			set(value) {
				this.form.payment_type = value?.id || ''
			},
		},

		requiredDateValue: {
			get() {
				if (!this.form.date_required) {
					return null
				}

				const parsed = new Date(`${this.form.date_required}T00:00:00`)
				return Number.isNaN(parsed.getTime()) ? null : parsed
			},

			set(value) {
				if (!value) {
					this.form.date_required = ''
					return
				}

				const date = value instanceof Date ? value : new Date(value)

				if (Number.isNaN(date.getTime())) {
					this.form.date_required = ''
					return
				}

				this.form.date_required = date.toISOString().slice(0, 10)
			},
		},

		actionModalIsInvalid() {
			return this.actionModal.requireComment
				&& String(this.actionModal.comment || '').trim().length === 0
		},
		selectedEstadoFiltro: {
			get() {
				return this.estadoFiltroOptions.find((option) => {
					return option.id === this.estadoFiltroId
				}) || this.estadoFiltroOptions[0]
			},

			set(value) {
				this.estadoFiltroId = value?.id || 'todos'
				this.applyListFilters()
			},
		},
		canToggleShowOnlyMine() {
			return Boolean(
				this.purchasePermissions.can_view_all
				|| this.purchasePermissions.can_approve
				|| this.purchasePermissions.can_process_purchase
				|| this.purchasePermissions.can_select_requester,
			)
		},

	},

	async mounted() {
		this.showOnlyMine = this.getSavedShowOnlyMine()

		const canAccess = await this.cargarContextoPurchases()

		if (!canAccess) {
			return
		}

		await this.cargarCatalogosEmpleado()

		if (!this.canToggleShowOnlyMine) {
			this.showOnlyMine = true
		}

		if (this.canSelectRequester) {
			await this.cargarEmpleadosParaSolicitud()
		}

		await this.cargarSolicitudes()
	},

	methods: {
		t,

		getTodayDate() {
			const date = new Date()
			const year = date.getFullYear()
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const day = String(date.getDate()).padStart(2, '0')

			return `${year}-${month}-${day}`
		},

		async duplicarDetalleActual() {
			if (!this.detalle?.solicitud) {
				showError(t('employees', 'No request selected to duplicate.'))
				return
			}

			if (!this.canCreatePurchaseRequest) {
				showError(t('employees', 'You do not have permission to create purchase requests.'))
				return
			}

			const solicitud = { ...this.detalle.solicitud }
			const details = Array.isArray(this.detalle.details)
				? this.detalle.details.map((detalle) => ({ ...detalle }))
				: []

			const duplicatedForm = {
				...this.getEmptyForm(),
				id_employee: solicitud.id_employee || null,

				title: solicitud.title || '',
				description: solicitud.description || '',
				justification: solicitud.justification || '',
				currency: solicitud.currency || 'MXN',
				priority: solicitud.priority || 'normal',

				// Fecha actual para la nueva solicitud duplicada
				date_required: this.getTodayDate(),

				requester_name: solicitud.requester_name || '',
				requester_department: solicitud.requester_department || '',
				requester_position: solicitud.requester_position || '',
				direct_manager_name: solicitud.direct_manager_name || '',
				jefe_directo_uid: solicitud.jefe_directo_uid || '',

				purchase_type: solicitud.purchase_type || 'refaccion',
				warranty: Boolean(Number(solicitud.warranty || 0)),
				purchase_use: solicitud.purchase_use || 'empresa',
				information: solicitud.information || solicitud.description || '',
				reason: solicitud.reason || solicitud.justification || '',

				office_percentage: solicitud.office_percentage || '',
				employee_percentage: solicitud.employee_percentage || '',
				payment_type: solicitud.payment_type || '',
				installments: solicitud.installments || '',
				admin_comments: solicitud.admin_comments || '',

				details: details.length > 0
					? details.map((detalle) => ({
						description: detalle.description || '',
						quantity: Number(detalle.quantity || 1),
						unit: detalle.unit || 'pieza',
						price_estimated: Number(detalle.price_estimated || 0),
						notes: detalle.notes || '',
						supplier_name: detalle.supplier_name || '',
						attention: detalle.attention || '',
						delivery: detalle.delivery || '',
						brand_model: detalle.brand_model || '',
						specifications: detalle.specifications || '',
					}))
					: this.getEmptyForm().details,
			}

			// Importante: primero cerrar el modal de detalle.
			this.detalle = null

			// Esperar a que Vue quite el NcModal anterior del DOM.
			await this.$nextTick()

			// Esperar un frame extra por las transiciones/portal de NcModal.
			await new Promise((resolve) => requestAnimationFrame(resolve))

			// Ahora sí abrir el formulario como NUEVA solicitud.
			this.editingSolicitudId = null
			this.form = duplicatedForm
			this.showForm = true

			showSuccess(t('employees', 'Purchase request duplicated. Review it before saving.'))
		},

		getEmptyForm() {
			return {
				id_employee: null,

				title: '',
				description: '',
				justification: '',
				currency: 'MXN',
				priority: 'normal',
				date_required: '',

				requester_name: '',
				requester_department: '',
				requester_position: '',
				direct_manager_name: '',
				jefe_directo_uid: '',

				purchase_type: 'refaccion',
				warranty: false,
				purchase_use: 'empresa',
				information: '',
				reason: '',

				office_percentage: '',
				employee_percentage: '',
				payment_type: '',
				installments: '',
				admin_comments: '',

				details: [
					{
						description: '',
						quantity: 1,
						unit: 'pieza',
						price_estimated: 0,
						notes: '',
						supplier_name: '',
						attention: '',
						delivery: '',
						brand_model: '',
						specifications: '',
					},
				],
			}
		},

		toggleForm() {
			this.showForm = true
		},

		closeRequestModal() {
			if (this.loading) {
				return
			}

			this.showForm = false
			this.editingSolicitudId = null
			this.form = this.getEmptyForm()
		},

		addDetalle() {
			this.form.details.push({
				description: '',
				quantity: 1,
				unit: 'pieza',
				price_estimated: 0,
				notes: '',
				supplier_name: '',
				attention: '',
				delivery: '',
				brand_model: '',
				specifications: '',
			})
		},

		removeDetalle(index) {
			if (this.form.details.length === 1) {
				return
			}

			this.form.details.splice(index, 1)
		},

		getDetalleSubtotal(detalle) {
			const quantity = Number(detalle?.quantity || 0)
			const precio = Number(detalle?.price_estimated || 0)

			return roundMoney(quantity * precio)
		},

		getDetalleIva(detalle) {
			return roundMoney(this.getDetalleSubtotal(detalle) * IVA_RATE)
		},

		getDetalleTotal(detalle) {
			return roundMoney(this.getDetalleSubtotal(detalle) + this.getDetalleIva(detalle))
		},

		getApiPayload(response) {
			return response?.ocs?.data || response
		},

		async cargarSolicitudes() {
			const sequence = ++this.requestSequence
			this.loading = true
			this.loadError = ''

			try {
				const response = await listarSolicitudes(buildPurchaseListParams({
					showOnlyMine: this.showOnlyMine,
					canViewAll: this.canToggleShowOnlyMine,
					status: this.estadoFiltroId,
					page: this.page,
					pageSize: this.pageSize,
				}))

				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('employees', 'Could not load requests.'))
				}

				if (sequence !== this.requestSequence) return

				const result = payload.data || {}
				this.solicitudes = Array.isArray(result.items) ? result.items : []
				this.totalRequests = Number(result.pagination?.total || 0)
				this.summary = {
					total: Number(result.summary?.total || 0),
					pending: Number(result.summary?.pending || 0),
					estimatedAmount: Number(result.summary?.estimated_amount || 0),
				}
			} catch (error) {
				if (sequence !== this.requestSequence) return
				console.error(error)
				this.loadError = this.getErrorMessage(error, t('employees', 'Error loading requests.'))
			} finally {
				if (sequence === this.requestSequence) this.loading = false
			}
		},

		applyListFilters() {
			this.page = 1
			this.cargarSolicitudes()
		},

		changePage(page) {
			const nextPage = Math.max(1, Math.min(page, this.totalPages))
			if (nextPage === this.page) return
			this.page = nextPage
			this.cargarSolicitudes()
		},

		async crear() {
			this.loading = true

			const wasEditing = this.isEditingRequest

			try {
				let response

				if (wasEditing) {
					response = await actualizarSolicitud(this.editingSolicitudId, this.getRequestPayload())
				} else {
					response = await crearSolicitud(this.getRequestPayload())
				}

				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('employees', 'Could not save request.'))
				}

				this.form = this.getEmptyForm()
				this.showForm = false
				this.editingSolicitudId = null

				await this.cargarSolicitudes()

				showSuccess(
					wasEditing
						? t('employees', 'Purchase request updated successfully')
						: t('employees', 'Purchase request created successfully'),
				)
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('employees', 'Error saving request.')))
			} finally {
				this.loading = false
			}
		},

		async verDetalle(id) {
			this.loading = true
			this.approvalFlow = null
			this.approvalFlowError = ''

			try {
				const response = await obtenerSolicitud(id)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('employees', 'Could not load request details.'))
				}

				this.detalle = payload.data
				await this.cargarFlujoAprobacion(id)
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('employees', 'Error loading request details.')))
			} finally {
				this.loading = false
			}
		},

		async cargarFlujoAprobacion(id) {
			this.approvalFlowLoading = true
			this.approvalFlowError = ''

			try {
				const response = await obtenerFlujoSolicitud(id)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('employees', 'Could not load the approval flow.'))
				}

				this.approvalFlow = payload.data
			} catch (error) {
				console.error(error)
				this.approvalFlow = null
				this.approvalFlowError = this.getErrorMessage(
					error,
					t('employees', 'Could not load the approval flow.'),
				)
			} finally {
				this.approvalFlowLoading = false
			}
		},

		closeDetalle() {
			this.detalle = null
			this.approvalFlow = null
			this.approvalFlowError = ''
			this.approvalFlowLoading = false
		},
		editarDetalle() {
			const id = this.detalle?.solicitud?.id_request
			if (!id) {
				return
			}

			this.closeDetalle()
			this.editar(id)
		},

		async enviar(id) {
			this.loading = true

			try {
				const response = await enviarAutorizacion(id)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('employees', 'Could not send request for approval.'))
				}

				await this.cargarSolicitudes()
				await this.verDetalle(id)

				showSuccess(t('employees', 'Request sent for approval'))
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('employees', 'Error sending request.')))
			} finally {
				this.loading = false
			}
		},

		formatMoney(value, currency = 'MXN') {
			const number = Number(value || 0)

			return new Intl.NumberFormat('es-MX', {
				style: 'currency',
				currency: currency || 'MXN',
			}).format(number)
		},

		formatDateTime(value) {
			if (!value) {
				return '-'
			}

			const normalized = String(value).replace(' ', 'T')
			const date = new Date(normalized)

			if (Number.isNaN(date.getTime())) {
				return String(value)
			}

			return new Intl.DateTimeFormat('es-MX', {
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(date)
		},

		formatEstado(status) {
			const estados = {
				borrador: t('employees', 'Draft'),
				pendiente_autorizacion: t('employees', 'Pending approval'),
				autorizada: t('employees', 'Approved'),
				rechazada: t('employees', 'Rejected'),
				cancelada: t('employees', 'Cancelled'),
			}

			return estados[status] || status
		},

		formatRequesterLabel(item) {
			return item?.requester_name
				|| item?.requester_name
				|| item?.displayname
				|| item?.id_user
				|| '-'
		},

		getRequestPayload() {
			const firstDetalle = this.form.details[0] || {}
			const details = this.form.details.map((detalle) => {
				return {
					...detalle,
					tax_amount: this.getDetalleIva(detalle),
					total: this.getDetalleTotal(detalle),
				}
			})

			return {
				...this.form,
				details,
				date_required: this.form.date_required || null,
				description: this.form.information || this.form.description,
				justification: this.form.reason || this.form.justification,

				supplier_name: firstDetalle.supplier_name || '',
				attention: firstDetalle.attention || '',
				delivery: firstDetalle.delivery || '',
				brand_model: firstDetalle.brand_model || '',
				specifications: firstDetalle.specifications || '',

				total_excluding_tax: this.totalEstimado,
				tax_amount: this.totalIva,
				total_including_tax: this.totalIncludingTax,
			}
		},
		async cargarEmpleadosParaSolicitud() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetUserLists'))
				const data = response?.data?.ocs?.data || {}

				const Employee = Array.isArray(data.Empleados) ? data.Empleados : []
				const desactivados = Array.isArray(data.Desactivados) ? data.Desactivados : []
				const users = Array.isArray(data.Users) ? data.Users : []

				this.empleadosCatalog = [...Employee, ...desactivados]

				const empleadosOptions = Employee.map((empleado) => {
					return this.normalizarEmpleadoOption(empleado, false)
				})

				const desactivadosOptions = desactivados.map((empleado) => {
					return this.normalizarEmpleadoOption(empleado, true)
				})

				const usersOptions = users.map((user) => {
					let displayname = user.uid

					try {
						displayname = JSON.parse(user.data)?.displayname?.value || user.uid
					} catch (e) {
						displayname = user.displayname || user.uid
					}

					return {
						uid: user.uid,
						id_employee: null,
						label: displayname,
						displayname,
						departamento: '',
						cargo: '',
						jefe_directo: '',
						disabled: false,
						raw: user,
					}
				})

				const seen = {}

				this.requesterOptions = [...empleadosOptions, ...desactivadosOptions, ...usersOptions]
					.filter((item) => {
						if (!item.uid || seen[item.uid]) {
							return false
						}

						seen[item.uid] = true
						return true
					})
			} catch (error) {
				console.error(error)
				showError(t('employees', 'Could not load employees for requester data.'))
			}
		},

		async cargarCatalogosEmpleado() {
			try {
				const [areasResponse, positionsResponse] = await Promise.all([
					axios.get(generateUrl('/apps/employees/GetAreasFix')),
					axios.get(generateUrl('/apps/employees/GetPositionsFix')),
				])

				this.areasCatalog = areasResponse?.data?.ocs?.data || []
				this.positionsCatalog = positionsResponse?.data?.ocs?.data || []
			} catch (error) {
				console.error(error)
				showError(t('employees', 'Could not load departments and positions.'))
			}
		},

		normalizarEmpleadoOption(empleado, disabled = false) {
			const uid = empleado.id_user || empleado.id_user || empleado.uid || ''
			const displayname = empleado.displayname
				|| empleado.DisplayName
				|| empleado.nombre_completo
				|| empleado.name
				|| uid

			const departamento = this.getAreaLabel(
				empleado.id_department
				|| empleado.id_department
				|| empleado.departamento
				|| empleado.Departamento,
			)

			const cargo = this.getPuestoLabel(
				empleado.id_position
				|| empleado.id_position
				|| empleado.puesto
				|| empleado.Puesto,
			)

			const gerenteUid = empleado.id_manager
				|| empleado.id_manager
				|| empleado.gerente
				|| empleado.Gerente
				|| ''

			const jefeDirecto = empleado.direct_manager_name
				|| empleado.jefe_directo
				|| this.getEmpleadoDisplayNameByUid(gerenteUid)

			return {
				uid,
				id_employee: empleado.id_employees || empleado.id_employees || empleado.id_employee || null,
				label: disabled ? `${displayname} (${t('employees', 'Disabled')})` : displayname,
				displayname,
				departamento,
				cargo,
				jefe_directo: jefeDirecto,
				jefe_directo_uid: gerenteUid,
				gerente_uid: gerenteUid,
				disabled,
				raw: empleado,
			}
		},

		getAreaLabel(value) {
			if (value === null || value === undefined || value === '') {
				return ''
			}

			const area = this.areasCatalog.find((item) => {
				return String(item.value) === String(value)
					|| String(item.id) === String(value)
					|| String(item.label) === String(value)
			})

			return area?.label || String(value)
		},

		getPuestoLabel(value) {
			if (value === null || value === undefined || value === '') {
				return ''
			}

			const puesto = this.positionsCatalog.find((item) => {
				return String(item.value) === String(value)
					|| String(item.id) === String(value)
					|| String(item.label) === String(value)
			})

			return puesto?.label || String(value)
		},

		getEmpleadoDisplayNameByUid(uid) {
			if (!uid) {
				return ''
			}

			const empleado = this.empleadosCatalog.find((item) => {
				return String(item.id_user || item.id_user || item.uid || '') === String(uid)
			})

			return empleado?.displayname
				|| empleado?.DisplayName
				|| empleado?.nombre_completo
				|| empleado?.name
				|| uid
		},

		fillRequesterData(value) {
			const requester = value || this.selectedRequester

			if (!requester) {
				this.selectedRequesterUid = ''
				this.form.id_employee = null
				this.form.requester_name = ''
				this.form.requester_department = ''
				this.form.requester_position = ''
				this.form.direct_manager_name = ''
				this.form.jefe_directo_uid = ''
				return
			}

			this.applyRequesterData(requester)
		},
		abrirDocumento(id) {
			const url = generateUrl('/apps/employees/purchases/solicitudes/{id}/document', { id })
			window.open(url, '_blank', 'noopener,noreferrer')
		},
		canCancelRequest(item) {
			const status = String(item?.status || '')

			if (!['borrador', 'pendiente_autorizacion'].includes(status)) {
				return false
			}

			if (this.purchasePermissions.can_approve) {
				return true
			}

			return this.isMyRequest(item)
		},

		getEmptyActionModal() {
			return {
				show: false,
				type: '',
				id: null,
				title: '',
				description: '',
				note: '',
				noteType: 'info',
				commentLabel: '',
				confirmLabel: '',
				confirmType: 'primary',
				comment: '',
				requireComment: false,
				loading: false,
			}
		},

		openActionModal(type, id) {
			const configs = {
				approve: {
					title: t('employees', 'Approve purchase request'),
					description: t('employees', 'You are about to approve this purchase request.'),
					note: t('employees', 'This will complete your current approval stage and record the decision in the history.'),
					noteType: 'info',
					commentLabel: t('employees', 'Approval comment'),
					confirmLabel: t('employees', 'Approve'),
					confirmType: 'primary',
					comment: '',
					requireComment: false,
				},
				reject: {
					title: t('employees', 'Reject purchase request'),
					description: t('employees', 'You are about to reject this purchase request.'),
					note: t('employees', 'The rejection reason will be saved in the request history.'),
					noteType: 'warning',
					commentLabel: t('employees', 'Rejection reason'),
					confirmLabel: t('employees', 'Reject'),
					confirmType: 'error',
					comment: '',
					requireComment: true,
				},
				cancel: {
					title: t('employees', 'Cancel purchase request'),
					description: t('employees', 'You are about to cancel this purchase request.'),
					note: t('employees', 'The request will not be physically deleted. It will be marked as cancelled for audit/history purposes.'),
					noteType: 'warning',
					commentLabel: t('employees', 'Cancellation comment'),
					confirmLabel: t('employees', 'Cancel request'),
					confirmType: 'error',
					comment: t('employees', 'Request cancelled from purchases module.'),
					requireComment: false,
				},
			}

			const config = configs[type]

			if (!config) {
				return
			}

			this.actionModal = {
				...this.getEmptyActionModal(),
				...config,
				type,
				id,
				show: true,
			}
		},

		closeActionModal() {
			if (this.actionModal.loading) {
				return
			}

			this.actionModal = this.getEmptyActionModal()
		},

		async submitActionModal() {
			if (this.actionModalIsInvalid) {
				showError(t('employees', 'A comment is required for this action.'))
				return
			}

			return this.actionSingleFlight.run(async () => {
				const id = this.actionModal.id
				const type = this.actionModal.type
				const comment = String(this.actionModal.comment || '').trim()

				this.actionModal.loading = true
				this.loading = true

				try {
					let response
					let successMessage

					if (type === 'approve') {
						response = await autorizarSolicitud(id, comment)
						successMessage = t('employees', 'Approval recorded')
					} else if (type === 'reject') {
						response = await rechazarSolicitud(id, comment)
						successMessage = t('employees', 'Request rejected')
					} else if (type === 'cancel') {
						response = await cancelarSolicitud(
							id,
							comment || t('employees', 'Request cancelled from purchases module.'),
						)
						successMessage = t('employees', 'Purchase request cancelled')
					} else {
						throw new Error(t('employees', 'Invalid action.'))
					}

					const payload = this.getApiPayload(response)

					if (!payload.success) {
						throw new Error(payload.message || t('employees', 'Could not complete action.'))
					}

					if (type === 'cancel' && this.detalle?.solicitud?.id_request === id) {
						this.closeDetalle()
					}

					await this.cargarSolicitudes()

					if (type !== 'cancel' && this.detalle?.solicitud?.id_request === id) {
						await this.verDetalle(id)
					}

					this.actionModal = this.getEmptyActionModal()
					showSuccess(successMessage)
				} catch (error) {
					console.error(error)
					showError(this.getErrorMessage(error, t('employees', 'Error completing action.')))

					if (['approve', 'reject'].includes(type)
						&& this.detalle?.solicitud?.id_request === id) {
						await this.verDetalle(id)
						if (!canApprovePurchaseFlow(this.approvalFlow)
							&& !canRejectPurchaseFlow(this.approvalFlow)) {
							this.actionModal = this.getEmptyActionModal()
						}
					}
				} finally {
					this.actionModal.loading = false
					this.loading = false
				}
			})
		},
		autorizar(id) {
			if (!canApprovePurchaseFlow(this.approvalFlow)) {
				showError(t('employees', 'You can no longer approve this request.'))
				return
			}
			this.openActionModal('approve', id)
		},

		rechazar(id) {
			if (!canRejectPurchaseFlow(this.approvalFlow)) {
				showError(t('employees', 'You can no longer reject this request.'))
				return
			}
			this.openActionModal('reject', id)
		},

		cancelar(id) {
			this.openActionModal('cancel', id)
		},
		async editar(id) {
			this.loading = true

			try {
				const response = await obtenerSolicitud(id)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('employees', 'Could not load request details.'))
				}

				const solicitud = payload.data.solicitud || {}
				const details = Array.isArray(payload.data.details) ? payload.data.details : []

				this.editingSolicitudId = id

				this.form = {
					...this.getEmptyForm(),
					id_employee: solicitud.id_employee || null,

					title: solicitud.title || '',
					description: solicitud.description || '',
					justification: solicitud.justification || '',
					currency: solicitud.currency || 'MXN',
					priority: solicitud.priority || 'normal',
					date_required: solicitud.date_required || '',

					requester_name: solicitud.requester_name || '',
					requester_department: solicitud.requester_department || '',
					requester_position: solicitud.requester_position || '',
					direct_manager_name: solicitud.direct_manager_name || '',
					jefe_directo_uid: solicitud.jefe_directo_uid || '',

					purchase_type: solicitud.purchase_type || 'refaccion',
					warranty: Boolean(Number(solicitud.warranty || 0)),
					purchase_use: solicitud.purchase_use || 'empresa',
					information: solicitud.information || solicitud.description || '',
					reason: solicitud.reason || solicitud.justification || '',

					office_percentage: solicitud.office_percentage || '',
					employee_percentage: solicitud.employee_percentage || '',
					payment_type: solicitud.payment_type || '',
					installments: solicitud.installments || '',
					admin_comments: solicitud.admin_comments || '',

					details: details.length > 0
						? details.map((detalle) => ({
							description: detalle.description || '',
							quantity: Number(detalle.quantity || 1),
							unit: detalle.unit || 'pieza',
							price_estimated: Number(detalle.price_estimated || 0),
							notes: detalle.notes || '',
							supplier_name: detalle.supplier_name || '',
							attention: detalle.attention || '',
							delivery: detalle.delivery || '',
							brand_model: detalle.brand_model || '',
							specifications: detalle.specifications || '',
						}))
						: this.getEmptyForm().details,
				}

				this.showForm = true
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('employees', 'Error loading request for editing.')))
			} finally {
				this.loading = false
			}
		},

		async cargarContextoPurchases() {
			try {
				const response = await obtenerContextoPurchases()
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('employees', 'Could not load purchase context.'))
				}

				const permissions = payload.data?.permissions || {}

				const canSelectRequester = permissions.can_select_requester
					?? payload.data?.can_select_requester
					?? false
				const canApprove = permissions.can_approve
					?? payload.data?.can_approve
					?? false
				const canProcessPurchase = permissions.can_process_purchase
					?? payload.data?.can_process_purchase
					?? false
				const canViewAll = permissions.can_view_all
					?? payload.data?.can_view_all
					?? canSelectRequester
					?? canApprove
					?? canProcessPurchase
				const canCreate = permissions.can_create
					?? payload.data?.can_create
					?? true

				this.purchasePermissions = {
					can_create: Boolean(canCreate),
					can_view_all: Boolean(canViewAll),
					can_approve: Boolean(canApprove),
					can_process_purchase: Boolean(canProcessPurchase),
					can_select_requester: Boolean(canSelectRequester),
				}

				this.canSelectRequester = this.purchasePermissions.can_select_requester
				this.contextLoaded = true
				this.currentUserId = payload.data?.uid || payload.data?.user_id || ''

				if (!this.canToggleShowOnlyMine) {
					this.showOnlyMine = true
				} else if (!this.hasSavedShowOnlyMinePreference) {
					this.showOnlyMine = false
				}

				const requesterData = payload.data?.requester || null

				if (requesterData) {
					const requester = this.normalizarEmpleadoOption({
						...requesterData.raw,
						uid: requesterData.uid,
						id_user: requesterData.uid,
						id_employees: requesterData.id_employee,
						id_department: requesterData.id_department,
						id_position: requesterData.id_position,
						id_manager: requesterData.jefe_directo_uid,
						displayname: requesterData.requester_name,
						direct_manager_name: requesterData.direct_manager_name,
					}, false)

					this.currentRequester = requester

					if (!this.canSelectRequester) {
						this.applyRequesterData(requester)
					}
				}

				return true
			} catch (error) {
				this.contextLoaded = true

				const status = error?.response?.status
				const message = this.getErrorMessage(
					error,
					t('employees', 'You do not have permission to access the purchases module.'),
				)

				showError(message)

				if (status === 403) {
					this.redirectToDashboard()
					return false
				}

				console.error(error)
				return false
			}
		},
		applyRequesterData(requester) {
			this.selectedRequesterUid = requester.uid || ''
			this.form.id_employee = requester.id_employee || null
			this.form.requester_name = requester.displayname || ''
			this.form.requester_department = requester.departamento || ''
			this.form.requester_position = requester.cargo || ''
			this.form.direct_manager_name = requester.jefe_directo || ''
			this.form.jefe_directo_uid = requester.jefe_directo_uid || requester.gerente_uid || ''
		},
		getSavedShowOnlyMine() {
			this.hasSavedShowOnlyMinePreference = false

			if (typeof window === 'undefined') {
				return true
			}

			try {
				const value = window.localStorage.getItem(SHOW_ONLY_MINE_KEY)

				if (value === null) {
					return true
				}

				this.hasSavedShowOnlyMinePreference = true
				return value === 'true'
			} catch (error) {
				return true
			}
		},

		saveShowOnlyMine(value) {
			if (typeof window === 'undefined') {
				return
			}

			try {
				window.localStorage.setItem(SHOW_ONLY_MINE_KEY, String(Boolean(value)))
			} catch (error) {
				// localStorage puede fallar en modo private o contextos restringidos.
			}
		},

		onToggleShowOnlyMine(value) {
			this.showOnlyMine = Boolean(value)
			this.hasSavedShowOnlyMinePreference = true
			this.saveShowOnlyMine(this.showOnlyMine)
			this.applyListFilters()
		},

		isMyRequest(item) {
			const currentUserId = String(this.currentUserId || '')

			if (!currentUserId) {
				return true
			}

			const candidates = [
				item?.id_user,
				item?.created_by,
				item?.created_by_uid,
				item?.requester_uid,
				item?.solicitante_uid,
				item?.solicitante_id_user,
				item?.id_user_solicitante,
				item?.usuario_solicitante,
				item?.owner_uid,
				item?.uid,
				item?.user_id,
			].map((value) => String(value || ''))

			return candidates.includes(currentUserId)
		},
		redirectToDashboard() {
			if (this.$router && this.$router.currentRoute?.name !== 'Home') {
				this.$router.replace({ name: 'Home' })
				return
			}

			window.location.hash = '#/'
		},

		getErrorMessage(error, fallback) {
			return error?.response?.data?.ocs?.data?.message
				|| error?.response?.data?.message
				|| error?.message
				|| fallback
		},
		canEditRequest(item) {
			return String(item?.status || '') === 'borrador'
				&& (this.purchasePermissions.can_select_requester || this.isMyRequest(item))
		},

		canSendRequest(item) {
			return String(item?.status || '') === 'borrador'
				&& (this.purchasePermissions.can_select_requester || this.isMyRequest(item))
		},

		async guardarDocumento(id) {
			this.loading = true

			try {
				const response = await guardarDocumentoSolicitud(id)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('employees', 'Could not save PDF.'))
				}

				showSuccess(payload.message || t('employees', 'PDF saved successfully.'))

				await this.cargarSolicitudes()
				await this.verDetalle(id)
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('employees', 'Error saving PDF.')))
			} finally {
				this.loading = false
			}
		},
		seleccionarFirmado(id) {
			this.firmadoSolicitudId = id
			this.$refs.firmadoInput.value = ''
			this.$refs.firmadoInput.click()
		},

		async onFirmadoSelected(event) {
			const file = event.target.files?.[0] || null

			if (!file || !this.firmadoSolicitudId) {
				return
			}

			this.loading = true

			try {
				const response = await subirDocumentoFirmadoSolicitud(this.firmadoSolicitudId, file)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('employees', 'Could not upload signed document.'))
				}

				showSuccess(payload.message || t('employees', 'Signed document uploaded successfully.'))

				await this.cargarSolicitudes()
				await this.verDetalle(this.firmadoSolicitudId)
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('employees', 'Error uploading signed document.')))
			} finally {
				this.loading = false
				this.firmadoSolicitudId = null
			}
		},

		abrirDocumentoFirmado(id) {
			const url = generateUrl('/apps/employees/purchases/solicitudes/{id}/document/firmado', { id })
			window.open(url, '_blank', 'noopener,noreferrer')
		},
		getEstadoDocumental(solicitud) {
			if (!solicitud?.pdf_file_id) {
				return 'pendiente_pdf'
			}

			if (!solicitud?.signed_file_id) {
				return 'pendiente_firmado'
			}

			return 'completo'
		},

		formatEstadoDocumental(solicitud) {
			const status = this.getEstadoDocumental(solicitud)

			const labels = {
				pendiente_pdf: t('employees', 'Pending PDF'),
				pendiente_firmado: t('employees', 'Pending signed document'),
				completo: t('employees', 'Complete'),
			}

			return labels[status] || status
		},

		canSaveOfficialPdf(solicitud) {
			return String(solicitud?.status || '') === 'autorizada'
		},

		canUploadSignedDocument(solicitud) {
			return String(solicitud?.status || '') === 'autorizada'
		},

		canViewSignedDocument(solicitud) {
			return Boolean(solicitud?.signed_file_id)
		},
	},
}
</script>
<style scoped lang="scss">
.purchases-page {
	display: flex;
	flex-direction: column;
	gap: 16px;
	width: 100%;
	padding: 24px;
}

.purchases-layout {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(260px, 300px);
	gap: 16px;
	align-items: start;
	width: 100%;
}

.requests-panel {
	min-width: 0;
}

.purchases-side-panel {
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-width: 0;
}

.purchases-side-panel h3 {
	margin: 0;
	font-size: 18px;
}

.purchases-header {
	display: flex;
	flex-direction: column;
	align-items: stretch;
	justify-content: space-between;
	gap: 18px;
	min-height: 220px;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.header-title {
	min-width: 0;
}

.purchases-header h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 30px;
	font-weight: 800;
	line-height: 1.15;
}

.header-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-start;
	gap: 8px;
}

.requests-header,
.filters-toolbar,
.pagination-bar,
.load-error-content {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}

.requests-header {
	align-items: flex-start;
	margin-bottom: 18px;
}

.requests-header h2 {
	margin: 0;
	font-size: 28px;
	line-height: 1.2;
}

.requests-description {
	margin: 5px 0 0;
	color: var(--color-text-maxcontrast);
}

.filters-toolbar {
	margin-bottom: 16px;
	padding: 12px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.result-count,
.pagination-bar {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.load-error {
	margin-bottom: 14px;
}

.load-error-content {
	width: 100%;
}

.table-area {
	position: relative;
	min-height: 120px;
}

.table-loading {
	position: absolute;
	z-index: 2;
	top: 16px;
	left: 50%;
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: 999px;
	background: var(--color-main-background);
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
	transform: translateX(-50%);
}

.pagination-bar {
	margin-top: 16px;
}

.pagination-bar > div {
	display: flex;
	gap: 8px;
}

.stats-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 12px;
}

.stat-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.stat-icon,
.details-icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.stat-icon {
	width: 46px;
	height: 46px;
}

.stat-card span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
}

.stat-card strong {
	display: block;
	margin-top: 2px;
	color: var(--color-main-text);
	font-size: 22px;
	font-weight: 800;
}

.panel-card {
	width: 100%;
	box-sizing: border-box;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
	overflow: hidden;
}

.panel-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 18px;
}

.panel-header h3 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 28px;
	font-weight: 800;
	line-height: 1.15;
}

.row-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.table-actions {
	flex-wrap: nowrap;
	align-items: center;
	justify-content: flex-end;
	gap: 6px;
}

.col-actions {
	width: 96px;
	text-align: right;
	white-space: nowrap;
}

.filters,
.row-actions,
.details-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.table-scroll {
	width: 100%;
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.table-area--loading .table-scroll {
	opacity: 0.55;
}

.purchases-table {
	width: 100%;
	border-collapse: collapse;
}

.purchases-table th,
.purchases-table td {
	padding: 11px 12px;
	border-bottom: 1px solid var(--color-border);
	color: var(--color-main-text);
	text-align: left;
	vertical-align: middle;
}

.purchases-table th {
	background: var(--color-background-hover);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.purchases-table tbody tr:hover {
	background: var(--color-background-hover);
}

.request-row {
	cursor: pointer;
}

.request-title {
	max-width: 260px;
	overflow-wrap: anywhere;
}

.purchases-table tbody tr:last-child td {
	border-bottom: none;
}

.badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 24px;
	padding: 4px 10px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 700;
	white-space: nowrap;
}

.status-borrador {
	background: #e5e5e5;
	color: #222;
}

.status-pendiente_autorizacion {
	background: #fff0b3;
	color: #5f4500;
}

.status-autorizada {
	background: #d5f5d5;
	color: #115511;
}

.status-rechazada {
	background: #ffd8d8;
	color: #7a1111;
}

.status-cancelada {
	background: #ececec;
	color: #555;
}

.document-completo {
	border: 1px solid rgba(22, 163, 74, 0.25);
	background: rgba(22, 163, 74, 0.12);
	color: #15803d;
}

.document-pendiente_firmado {
	border: 1px solid rgba(234, 179, 8, 0.28);
	background: rgba(234, 179, 8, 0.14);
	color: #8a5700;
}

.document-pendiente_pdf {
	border: 1px solid rgba(100, 116, 139, 0.25);
	background: rgba(100, 116, 139, 0.14);
	color: #475569;
}

.empty-state {
	padding: 28px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.request-sections {
	display: flex;
	flex-direction: column;
	gap: 18px;
}

.request-section {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.request-section-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	padding: 14px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.request-section-header h4 {
	margin: 2px 0 0;
	color: var(--color-main-text);
	font-size: 20px;
	font-weight: 800;
}

.request-section-header p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.request-section--pending .request-section-header {
	border-color: var(--color-warning);
	background: var(--color-warning-hover);
}

.request-section--pending .purchases-table {
	border-left: 4px solid var(--color-warning);
}

.detail-panel {
	margin-bottom: 24px;
}

.details-header {
	display: flex;
	align-items: flex-start;
	gap: 14px;
	margin-bottom: 18px;
	min-width: 0;
}

.details-icon {
	width: 56px;
	height: 56px;
}

.details-title {
	flex: 1 1 auto;
	min-width: 0;
}

.details-title h2 {
	max-width: 100%;
	line-height: 1.2;
	overflow-wrap: anywhere;
}

.details-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 12px;
	width: 100%;
	margin-bottom: 18px;
}

.detail-card {
	min-width: 0;
	box-sizing: border-box;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.detail-card span {
	display: block;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.detail-card strong {
	max-width: 100%;
	margin: 0;
	color: var(--color-main-text);
	font-size: 14px;
	line-height: 1.5;
	overflow-wrap: anywhere;
	word-break: break-word;
}

.subsection {
	margin-top: 18px;
}

.section-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.section-head h3 {
	font-size: 18px;
}

.historial-list {
	margin: 0;
	padding: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	list-style: none;
	overflow: hidden;
}

.historial-list li {
	padding: 12px 14px;
	border-bottom: 1px solid var(--color-border);
}

.historial-list li:last-child {
	border-bottom: none;
}

.historial-list span,
.historial-list small {
	display: block;
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
}

.historial-list p {
	margin: 6px 0 0;
}

/* Modal de nueva solicitud */
.purchase-request-modal {
	:deep(.modal-container) {
		width: min(1180px, calc(100vw - 48px)) !important;
		max-width: min(1180px, calc(100vw - 48px)) !important;
		margin: 0 auto !important;
		box-sizing: border-box !important;
	}

	:deep(.modal-container__content),
	:deep(.modal__content),
	:deep(.modal-wrapper) {
		width: 100% !important;
		max-width: 100% !important;
		box-sizing: border-box !important;
	}
}

.purchase-modal {
	width: 100%;
	max-height: calc(100vh - 120px);
	box-sizing: border-box;
	padding: 28px;
	overflow-x: hidden;
	overflow-y: auto;
}

.modal-header {
	margin-bottom: 18px;
}

.modal-header h2,
.modal-section-head h3 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 700;
}

.modal-header p {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.4;
}

.purchase-modal .form-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 14px;
	margin-bottom: 16px;
}

.modal-block {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-bottom: 18px;
}

.purchase-modal .span-2 {
	grid-column: 1 / -1;
}

.section-note,
.concepts-note {
	margin: 0;
}

.date-field,
.switch-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.field-label,
.switch-field span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 700;
}

.modal-section-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin: 22px 0 12px;
}

.concepts-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.concept-card {
	width: 100%;
	box-sizing: border-box;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.concept-card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
}

.concept-heading {
	display: flex;
	align-items: center;
	gap: 12px;
}

.concept-heading strong,
.concept-heading span {
	display: block;
}

.concept-heading span {
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.concept-number {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	width: 34px;
	height: 34px;
	border-radius: 999px;
	background: var(--color-main-background);
	color: var(--color-primary-element);
	font-size: 13px;
	font-weight: 700;
}

.concept-fields {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	min-width: 0;
}

.concept-summary {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.concept-summary span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.concept-summary strong {
	display: block;
	margin-top: 4px;
	font-size: 16px;
}

.purchase-total-card {
	margin-top: 16px;
}

.purchase-total {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 16px;
}

.purchase-total div {
	padding: 4px 0;
}

.purchase-total span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 700;
	text-transform: uppercase;
}

.purchase-total strong {
	display: block;
	margin-top: 2px;
	color: var(--color-main-text);
	font-size: 22px;
	font-weight: 800;
}

.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 22px;
}

.manager-preview {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.manager-card {
	display: flex;
	align-items: center;
	gap: 12px;
	min-height: 52px;
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.manager-card--empty {
	background: var(--color-background-hover);
}

.manager-info {
	display: flex;
	flex-direction: column;
	min-width: 0;
	line-height: 1.25;
}

.manager-info strong {
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.manager-info span {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.requester-locked-card {
	display: flex;
	align-items: center;
	gap: 12px;
	min-height: 58px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.requester-locked-info {
	display: flex;
	flex-direction: column;
	min-width: 0;
	line-height: 1.3;
}

.requester-locked-info strong {
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.requester-locked-info span {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* Modal de detalle de solicitud */
.purchase-detail-modal {
	:deep(.modal-container) {
		width: min(1240px, calc(100vw - 56px)) !important;
		max-width: min(1240px, calc(100vw - 56px)) !important;
		margin: 0 auto !important;
		box-sizing: border-box !important;
	}

	:deep(.modal-container__content),
	:deep(.modal__content),
	:deep(.modal-wrapper) {
		width: 100% !important;
		max-width: 100% !important;
		box-sizing: border-box !important;
	}
}

.detail-modal {
	display: flex;
	flex-direction: column;
	gap: 22px;
	width: 100%;
	max-height: calc(100vh - 110px);
	box-sizing: border-box;
	padding: 30px;
	overflow-x: hidden;
	overflow-y: auto;
}

.detail-modal .details-header {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	align-items: flex-start;
	gap: 18px;
	padding: 18px;
	margin-bottom: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.detail-modal .details-icon {
	width: 60px;
	height: 60px;
	background: var(--color-main-background);
	color: var(--color-primary-element);
}

.detail-modal .details-title {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 0;
}

.action-modal-header p {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.45;
}

.action-modal-header h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 22px;
	font-weight: 800;
}

.detail-modal .details-title h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 800;
	line-height: 1.2;
	overflow-wrap: anywhere;
}

.detail-modal .details-title p {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.45;
}

.detail-modal .details-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.detail-modal .details-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 14px;
	margin-bottom: 0;
}

.detail-modal .detail-card {
	min-height: 88px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.detail-modal .detail-card span {
	display: block;
	margin-bottom: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 800;
	letter-spacing: .03em;
	text-transform: uppercase;
}

.detail-modal .detail-card strong {
	display: block;
	color: var(--color-main-text);
	font-size: 15px;
	font-weight: 700;
	line-height: 1.45;
	overflow-wrap: anywhere;
	word-break: break-word;
}

.detail-modal .subsection {
	padding: 18px;
	margin-top: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.detail-modal .section-head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
}

.detail-modal .section-head h3 {
	margin: 0;
	font-size: 19px;
	font-weight: 800;
}

.detail-modal .table-scroll {
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.detail-modal .table-scroll .purchases-table thead tr th,
.detail-modal .table-scroll .purchases-table tbody tr td {
	padding: 13px 14px;
	vertical-align: top;
}

.detail-modal .table-scroll .purchases-table thead tr th {
	font-size: 11px;
	letter-spacing: .03em;
}

.table-muted {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.4;
}

.detail-modal .historial-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	border: 0;
	border-radius: 0;
	background: transparent;
}

.detail-modal .historial-list li {
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.detail-modal .historial-list li:last-child {
	border-bottom: 1px solid var(--color-border);
}

.detail-modal .subsection .historial-list li strong {
	display: block;
	margin-bottom: 4px;
	color: var(--color-main-text);
	font-size: 14px;
}

.detail-modal .table-scroll .purchases-table tbody tr td strong {
	display: block;
	margin-bottom: 4px;

}

.detail-modal .historial-list span,
.detail-modal .historial-list small {
	display: block;
	margin-top: 3px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.detail-modal .historial-list p {
	margin: 8px 0 0;
	color: var(--color-main-text);
	line-height: 1.4;
}

/* Responsive */
	@media (max-width: 980px) {
	.purchases-page {
		padding: 14px;
	}

		.purchases-header,
		.panel-header,
		.requests-header,
		.filters-toolbar,
	.section-head,
	.details-header {
		align-items: stretch;
		flex-direction: column;
	}

		.header-actions,
	.filters,
	.details-actions {
			justify-content: flex-start;
		}

		.result-count {
			align-self: flex-start;
		}

	.stats-grid,
	.details-grid {
		grid-template-columns: 1fr;
	}

	.panel-card {
		padding: 16px;
	}

	.details-icon {
		width: 46px;
		height: 46px;
	}

	.details-title h2 {
		font-size: 20px;
	}
}

@media (max-width: 900px) {
	.purchases-table {
		min-width: 980px;
	}

	.purchases-layout {
		grid-template-columns: 1fr;
	}

	.purchases-side-panel {
		order: -1;
	}

	.purchases-header {
		min-height: auto;
	}

	.stats-grid {
		grid-template-columns: 1fr;
	}

	.panel-header {
		flex-direction: column;
	}

	.filters {
		justify-content: flex-start;
		width: 100%;
	}

	.purchase-request-modal {
		:deep(.modal-container) {
			width: min(96vw, 1180px) !important;
			max-width: min(96vw, 1180px) !important;
		}
	}

	.purchase-modal {
		max-height: calc(100vh - 80px);
		padding: 16px;
	}

	.purchase-modal .form-grid,
	.concept-fields {
		grid-template-columns: 1fr;
	}

	.purchase-modal .span-2 {
		grid-column: auto;
	}

	.modal-section-head,
	.purchase-total {
		align-items: stretch;
		flex-direction: column;
	}

	.concept-card-header,
	.concept-heading {
		align-items: flex-start;
		flex-direction: column;
	}

	.concept-summary {
		grid-template-columns: 1fr;
	}

	.purchase-total {
		grid-template-columns: 1fr;
	}

	.purchase-detail-modal {
		:deep(.modal-container) {
			width: min(96vw, 1240px) !important;
			max-width: min(96vw, 1240px) !important;
		}
	}

	.detail-modal {
		max-height: calc(100vh - 80px);
		padding: 16px;
		gap: 16px;
	}

	.detail-modal .details-header {
		grid-template-columns: 1fr;
		gap: 12px;
		padding: 14px;
	}

	.detail-modal .details-actions {
		justify-content: flex-start;
	}

	.detail-modal .details-grid {
		grid-template-columns: 1fr;
	}

	.detail-modal .subsection {
		padding: 14px;
	}

	.detail-modal .section-head {
		flex-direction: column;
	}

	.detail-modal .table-scroll .purchases-table thead tr th,
	.detail-modal .table-scroll .purchases-table tbody tr td {
		padding: 10px 12px;
	}
}

.status-filter {
	min-width: 230px;
}

.filters {
	align-items: center;
}

.purchase-action-modal {
	:deep(.modal-container) {
		width: min(560px, calc(100vw - 48px)) !important;
		max-width: min(560px, calc(100vw - 48px)) !important;
		margin: 0 auto !important;
		box-sizing: border-box !important;
	}

	:deep(.modal-container__content),
	:deep(.modal__content),
	:deep(.modal-wrapper) {
		width: 100% !important;
		max-width: 100% !important;
		box-sizing: border-box !important;
	}
}

.action-modal {
	display: flex;
	flex-direction: column;
	gap: 16px;
	width: 100%;
	box-sizing: border-box;
	padding: 24px;
}

.action-modal-note {
	margin: 0;
}

.action-modal-comment {
	min-height: 120px;
}

.action-modal-error {
	margin: -4px 0 0;
	color: var(--color-error);
	font-size: 13px;
	font-weight: 700;
}

.action-modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 4px;
}

@media (max-width: 900px) {
	.purchase-action-modal {
		:deep(.modal-container) {
			width: min(96vw, 560px) !important;
			max-width: min(96vw, 560px) !important;
		}
	}

	.action-modal {
		padding: 16px;
	}

	.action-modal-actions {
		flex-direction: column-reverse;
	}

	.pending-approval-summary {
		display: grid;
		grid-template-columns: 52px minmax(0, 1fr) auto;
		gap: 12px;
		align-items: center;
		padding: 16px;
		border: 1px solid #e6b800;
		border-radius: var(--border-radius-large);
		background: #fff8d6;
	}

	.pending-approval-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 52px;
		height: 52px;
		border-radius: var(--border-radius-large);
		background: var(--color-main-background);
		color: #7a5a00;
	}

	.pending-approval-content {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}

	.pending-approval-content strong {
		color: var(--color-main-text);
		font-size: 30px;
		font-weight: 800;
		line-height: 1;
	}

	.pending-approval-content span {
		overflow: hidden;
		margin-top: 4px;
		color: var(--color-text-maxcontrast);
		font-size: 13px;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.badge-document-ok {
	background: rgba(22, 163, 74, 0.12);
	color: #15803d;
	border: 1px solid rgba(22, 163, 74, 0.25);
}

.badge-document-pending {
	background: rgba(234, 179, 8, 0.14);
	color: #a16207;
	border: 1px solid rgba(234, 179, 8, 0.28);
}

.badge-document-missing {
	background: rgba(100, 116, 139, 0.14);
	color: #475569;
	border: 1px solid rgba(100, 116, 139, 0.25);
}
</style>
