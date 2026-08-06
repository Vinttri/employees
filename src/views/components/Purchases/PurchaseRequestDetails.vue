<template>
	<div class="purchase-detail-view">
		<header class="purchase-detail-header">
			<div class="purchase-title-block">
				<div class="purchase-icon" aria-hidden="true">
					<CartOutline :size="30" />
				</div>
				<div>
					<p>{{ t('employees', 'Purchase request') }}</p>
					<h2>{{ solicitud.reference || t('employees', 'No reference') }}</h2>
					<span :class="['status-chip', `is-${solicitud.status || 'unknown'}`]">
						{{ statusLabel(solicitud.status) }}
					</span>
				</div>
			</div>

			<div class="purchase-header-actions">
				<NcButton v-if="actions.exportPdf" @click="$emit('export-pdf')">
					<template #icon>
						<FilePdfBox :size="19" />
					</template>
					{{ t('employees', 'Export PDF') }}
				</NcButton>

				<NcButton v-if="actions.send"
					type="primary"
					:disabled="processing"
					@click="$emit('send')">
					<template #icon>
						<SendOutline :size="19" />
					</template>
					{{ t('employees', 'Send for approval') }}
				</NcButton>

				<NcButton v-if="actions.reject"
					type="error"
					:disabled="processing"
					@click="$emit('reject')">
					{{ t('employees', 'Reject') }}
				</NcButton>

				<NcButton v-if="actions.approve"
					type="primary"
					:disabled="processing"
					@click="$emit('approve')">
					{{ t('employees', 'Approve') }}
				</NcButton>

				<NcActions v-if="hasSecondaryActions" :force-menu="true" :aria-label="t('employees', 'More actions')">
					<NcActionButton v-if="actions.edit" @click="$emit('edit')">
						<template #icon>
							<PencilOutline :size="19" />
						</template>
						{{ t('employees', 'Edit') }}
					</NcActionButton>
					<NcActionButton v-if="actions.uploadSigned" @click="$emit('upload-signed')">
						<template #icon>
							<UploadOutline :size="19" />
						</template>
						{{ t('employees', 'Upload signed document') }}
					</NcActionButton>
					<NcActionButton v-if="actions.cancel" @click="$emit('cancel')">
						<template #icon>
							<Cancel :size="19" />
						</template>
						{{ t('employees', 'Cancel request') }}
					</NcActionButton>
				</NcActions>

				<NcButton :aria-label="t('employees', 'Close')" :title="t('employees', 'Close')" @click="$emit('close')">
					<template #icon>
						<Close :size="20" />
					</template>
				</NcButton>
			</div>
		</header>

		<main class="purchase-detail-content">
			<section class="summary-grid" :aria-label="t('employees', 'Purchase summary')">
				<div><span>{{ t('employees', 'Subtotal') }}</span><strong>{{ money(solicitud.total_excluding_tax) }}</strong></div>
				<div><span>{{ t('employees', 'VAT') }}</span><strong>{{ money(solicitud.tax_amount) }}</strong></div>
				<div class="summary-total">
					<span>{{ t('employees', 'Total') }}</span><strong>{{ money(solicitud.total_including_tax) }}</strong>
				</div>
				<div><span>{{ t('employees', 'Priority') }}</span><strong>{{ valueOrDefault(solicitud.priority) }}</strong></div>
				<div><span>{{ t('employees', 'Purchase use') }}</span><strong>{{ valueOrDefault(solicitud.purchase_use) }}</strong></div>
				<div><span>{{ t('employees', 'Currency') }}</span><strong>{{ valueOrDefault(solicitud.currency) }}</strong></div>
			</section>

			<section class="detail-section requester-section">
				<SectionHeading :title="t('employees', 'Requester information')" />
				<div class="requester-card">
					<NcAvatar :user="requesterUid"
						:display-name="requesterName"
						:show-user-status="false"
						:size="52"
						disable-menu />
					<div class="requester-identity">
						<strong>{{ requesterName }}</strong>
						<small>{{ requesterUid || t('employees', 'No user identifier') }}</small>
					</div>
					<div><span>{{ t('employees', 'Department') }}</span><strong>{{ valueOrDefault(solicitud.requester_department) }}</strong></div>
					<div><span>{{ t('employees', 'Position') }}</span><strong>{{ valueOrDefault(solicitud.requester_position) }}</strong></div>
				</div>
			</section>

			<section class="detail-section">
				<SectionHeading :title="t('employees', 'Purchase information')" />
				<dl class="information-grid">
					<div><dt>{{ t('employees', 'Title') }}</dt><dd>{{ valueOrDefault(solicitud.title) }}</dd></div>
					<div><dt>{{ t('employees', 'Purchase type') }}</dt><dd>{{ valueOrDefault(solicitud.purchase_type) }}</dd></div>
					<div><dt>{{ t('employees', 'Purchase use') }}</dt><dd>{{ valueOrDefault(solicitud.purchase_use) }}</dd></div>
					<div><dt>{{ t('employees', 'Priority') }}</dt><dd>{{ valueOrDefault(solicitud.priority) }}</dd></div>
					<div><dt>{{ t('employees', 'Currency') }}</dt><dd>{{ valueOrDefault(solicitud.currency) }}</dd></div>
					<div><dt>{{ t('employees', 'Required date') }}</dt><dd>{{ formatDate(solicitud.date_required) }}</dd></div>
					<div><dt>{{ t('employees', 'Warranty') }}</dt><dd>{{ warrantyLabel }}</dd></div>
					<div class="span-full">
						<dt>{{ t('employees', 'Information') }}</dt><dd>{{ valueOrDefault(solicitud.information) }}</dd>
					</div>
					<div class="span-full">
						<dt>{{ t('employees', 'Reason') }}</dt><dd>{{ valueOrDefault(solicitud.reason) }}</dd>
					</div>
				</dl>
			</section>

			<section class="detail-section">
				<SectionHeading :title="t('employees', 'Requested items')" :subtitle="itemCountLabel" />
				<div class="table-scroll">
					<table class="items-table">
						<thead>
							<tr>
								<th>{{ t('employees', 'Description') }}</th><th>{{ t('employees', 'Supplier') }}</th>
								<th>{{ t('employees', 'Delivery') }}</th><th>{{ t('employees', 'Brand / Model') }}</th>
								<th>{{ t('employees', 'Quantity') }}</th><th>{{ t('employees', 'Unit price') }}</th>
								<th>{{ t('employees', 'VAT') }}</th><th>{{ t('employees', 'Total') }}</th>
								<th>{{ t('employees', 'Actions') }}</th>
							</tr>
						</thead>
						<tbody>
							<template v-for="(item, index) in details">
								<tr :key="`item-${item.id_detail || index}`">
									<td><strong>{{ valueOrDefault(item.description) }}</strong></td>
									<td>{{ valueOrDefault(item.supplier_name) }}</td>
									<td>{{ valueOrDefault(item.delivery) }}</td>
									<td>{{ valueOrDefault(item.brand_model) }}</td>
									<td>{{ valueOrDefault(item.quantity) }} {{ item.unit || '' }}</td>
									<td>{{ money(item.price_estimated) }}</td>
									<td>{{ money(item.tax_amount) }}</td>
									<td>{{ money(item.total) }}</td>
									<td>
										<NcButton :aria-expanded="isItemExpanded(item, index)"
											:title="t('employees', 'View item details')"
											:aria-label="t('employees', 'View item details')"
											@click="toggleItem(item, index)">
											<template #icon>
												<ChevronDown :class="{ 'is-open': isItemExpanded(item, index) }" :size="20" />
											</template>
										</NcButton>
									</td>
								</tr>
								<tr v-if="isItemExpanded(item, index)" :key="`item-detail-${item.id_detail || index}`" class="item-detail-row">
									<td colspan="9">
										<div class="item-extra-grid">
											<div><span>{{ t('employees', 'Specifications') }}</span><p>{{ valueOrDefault(item.specifications) }}</p></div>
											<div><span>{{ t('employees', 'Notes') }}</span><p>{{ valueOrDefault(item.notes) }}</p></div>
											<div><span>{{ t('employees', 'Attention') }}</span><p>{{ valueOrDefault(item.attention) }}</p></div>
											<div class="span-full">
												<span>{{ t('employees', 'Related documents') }}</span>
												<p v-if="itemDocuments(item).length === 0">
													{{ t('employees', 'No related documents') }}
												</p>
												<ul v-else>
													<li v-for="document in itemDocuments(item)" :key="document.file_id || document.name_file">
														{{ document.name_file }}
													</li>
												</ul>
											</div>
										</div>
									</td>
								</tr>
							</template>
						</tbody>
					</table>
				</div>
			</section>

			<section class="detail-section">
				<SectionHeading :title="t('employees', 'Documents')" />
				<div class="document-groups">
					<DocumentGroup :title="t('employees', 'Generated PDF')" :documents="generatedPdfDocuments" :empty-text="t('employees', 'No generated PDF')">
						<template #actions="{ document }">
							<NcButton @click="$emit('export-pdf', document)">
								{{ t('employees', 'View or download') }}
							</NcButton>
							<NcButton v-if="actions.savePdf" @click="$emit('save-pdf')">
								{{ t('employees', 'Update saved PDF') }}
							</NcButton>
						</template>
					</DocumentGroup>
					<DocumentGroup :title="t('employees', 'Quotations')" :documents="quotations" :empty-text="t('employees', 'No quotations associated')" />
					<DocumentGroup :title="t('employees', 'Signed document')" :documents="signedDocuments" :empty-text="t('employees', 'No signed document')">
						<template #actions="{ document }">
							<NcButton @click="$emit('view-signed', document)">
								{{ t('employees', 'View or download') }}
							</NcButton>
						</template>
					</DocumentGroup>
					<DocumentGroup :title="t('employees', 'Other attachments')" :documents="attachments" :empty-text="t('employees', 'No other attachments')" />
				</div>
			</section>

			<PurchaseApprovalFlow v-if="showApprovalFlow"
				:flow="flow"
				:history="history"
				:loading="flowLoading"
				:error="flowError"
				:processing="processing"
				:show-actions="false"
				@approve="$emit('approve')"
				@reject="$emit('reject')" />

			<section class="detail-section history-section">
				<div class="history-heading">
					<SectionHeading :title="t('employees', 'History')" />
					<NcButton :disabled="historyLoading" @click="toggleHistory">
						{{ showHistory ? t('employees', 'Hide history') : t('employees', 'Show history') }}
					</NcButton>
				</div>
				<NcLoadingIcon v-if="historyLoading" :size="28" />
				<NcNoteCard v-else-if="historyError" type="error">
					{{ historyError }}
				</NcNoteCard>
				<template v-else-if="showHistory">
					<NcEmptyContent v-if="history.length === 0" :name="t('employees', 'No history records found')" />
					<ol v-else class="history-list">
						<li v-for="event in history" :key="event.id_history">
							<div><strong>{{ actionLabel(event.action) }}</strong><span>{{ historyActor(event) }} · {{ formatDateTime(event.created_at) }}</span></div>
							<p v-if="event.status_previous || event.status_new">
								{{ statusLabel(event.status_previous) }} → {{ statusLabel(event.status_new) }}
							</p>
							<p v-if="event.comment">
								{{ event.comment }}
							</p>
							<a v-if="historyDocument(event)" href="#" @click.prevent="openHistoryDocument(event)">{{ historyDocument(event).name }}</a>
						</li>
					</ol>
					<NcButton v-if="history.length < historyTotal" :disabled="historyLoading" @click="loadHistory(true)">
						{{ t('employees', 'Load more') }}
					</NcButton>
				</template>
			</section>
		</main>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcActionButton, NcActions, NcAvatar, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import CartOutline from 'vue-material-design-icons/CartOutline.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import Close from 'vue-material-design-icons/Close.vue'
import FilePdfBox from 'vue-material-design-icons/FilePdfBox.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import SendOutline from 'vue-material-design-icons/SendOutline.vue'
import UploadOutline from 'vue-material-design-icons/UploadOutline.vue'

import { obtenerHistorySolicitud } from '../../../services/purchasesService.js'
import { getPurchaseDetailActions } from '../../../utils/purchaseDetailView.js'
import PurchaseApprovalFlow from './PurchaseApprovalFlow.vue'
import DocumentGroup from './DocumentGroup.vue'
import SectionHeading from './SectionHeading.vue'

export default {
	name: 'PurchaseRequestDetails',
	components: { Cancel, CartOutline, ChevronDown, Close, PurchaseApprovalFlow, DocumentGroup, FilePdfBox, NcActionButton, NcActions, NcAvatar, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard, PencilOutline, SectionHeading, SendOutline, UploadOutline },
	props: {
		detail: { type: Object, required: true }, flow: { type: Object, default: null }, flowLoading: { type: Boolean, default: false }, flowError: { type: String, default: '' }, processing: { type: Boolean, default: false }, permissions: { type: Object, default: () => ({}) }, currentUserId: { type: String, default: '' },
	},
	data() { return { expandedItems: {}, showHistory: false, history: [], historyTotal: 0, historyLoading: false, historyError: '', historyPageSize: 10 } },
	computed: {
		solicitud() { return this.detail?.solicitud || {} },
		details() { return this.detail?.details || [] },
		actions() { return getPurchaseDetailActions({ request: this.solicitud, permissions: this.permissions, flow: this.flow, currentUserId: this.currentUserId }) },
		hasSecondaryActions() { return this.actions.edit || this.actions.uploadSigned || this.actions.cancel },
		requesterUid() { return String(this.solicitud.id_user || '') },
		requesterName() { return this.solicitud.requester_name || this.requesterUid || t('employees', 'Not specified') },
		warrantyLabel() { return Number(this.solicitud.warranty || 0) === 1 ? t('employees', 'Yes') : t('employees', 'No') },
		itemCountLabel() { return t('employees', '{count} item(s)', { count: this.details.length }) },
		showApprovalFlow() { return ['pendiente_autorizacion', 'autorizada', 'rechazada', 'cancelada'].includes(String(this.solicitud.status || '')) },
		generatedPdfDocuments() { return this.solicitud.pdf_file_id ? [{ file_id: this.solicitud.pdf_file_id, name: this.solicitud.pdf_name, type: 'PDF', date: this.solicitud.pdf_generated_at, user: this.solicitud.updated_by }] : [] },
		signedDocuments() { return this.solicitud.signed_file_id ? [{ file_id: this.solicitud.signed_file_id, name: this.solicitud.signed_name, type: this.solicitud.signed_mime, date: this.solicitud.signed_uploaded_at, user: this.solicitud.signed_uploaded_by }] : [] },
		quotations() { return this.detail?.cotizaciones || [] },
		attachments() { return this.detail?.adjuntos || [] },
	},
	methods: {
		t,
		valueOrDefault(value) { return value === null || value === undefined || String(value).trim() === '' ? t('employees', 'Not specified') : value },
		money(value) { return new Intl.NumberFormat(document.documentElement.lang || 'en', { style: 'currency', currency: this.solicitud.currency || 'MXN' }).format(Number(value || 0)) },
		formatDate(value) { if (!value) return t('employees', 'Not specified'); const date = new Date(`${String(value).slice(0, 10)}T00:00:00`); return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(document.documentElement.lang || 'en', { dateStyle: 'medium' }).format(date) },
		formatDateTime(value) { if (!value) return t('employees', 'Not specified'); const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(document.documentElement.lang || 'en', { dateStyle: 'medium', timeStyle: 'short' }).format(date) },
		statusLabel(state) { return ({ borrador: t('employees', 'Draft'), pendiente_autorizacion: t('employees', 'Pending approval'), autorizada: t('employees', 'Approved'), rechazada: t('employees', 'Rejected'), cancelada: t('employees', 'Cancelled') })[state] || this.valueOrDefault(state) },
		itemKey(item, index) { return String(item.id_detail || index) },
		isItemExpanded(item, index) { return Boolean(this.expandedItems[this.itemKey(item, index)]) },
		toggleItem(item, index) { const key = this.itemKey(item, index); this.$set(this.expandedItems, key, !this.expandedItems[key]) },
		itemDocuments(item) { return item.documents || item.adjuntos || [] },
		async toggleHistory() { this.showHistory = !this.showHistory; if (this.showHistory && this.history.length === 0) await this.loadHistory(false) },
		async loadHistory(append) { this.historyLoading = true; this.historyError = ''; try { const offset = append ? this.history.length : 0; const response = await obtenerHistorySolicitud(this.solicitud.id_request, { limit: this.historyPageSize, offset }); const payload = response?.ocs?.data || response; if (!payload.success) throw new Error(payload.message || t('employees', 'Could not load history.')); const result = payload.data || {}; this.history = append ? [...this.history, ...(result.items || [])] : (result.items || []); this.historyTotal = Number(result.pagination?.total || 0) } catch (error) { this.historyError = error?.response?.data?.ocs?.data?.message || error?.response?.data?.message || error?.message || t('employees', 'Could not load history.') } finally { this.historyLoading = false } },
		parseMetadata(value) { if (value && typeof value === 'object') return value; try { return value ? JSON.parse(value) : {} } catch (error) { return {} } },
		historyActor(event) { const metadata = this.parseMetadata(event.metadata); return metadata.actor_name || event.created_by || t('employees', 'Unknown actor') },
		historyDocument(event) { const metadata = this.parseMetadata(event.metadata); const name = metadata.file_name || metadata.name_file; return name ? { name, fileId: metadata.file_id } : null },
		openHistoryDocument(event) { const document = this.historyDocument(event); if (!document) return; if (event.action === 'document_firmado_subido') this.$emit('view-signed', document); else this.$emit('export-pdf', document) },
		actionLabel(action) { return String(action || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()) },
	},
}
</script>

<style scoped>
/* stylelint-disable no-descending-specificity */
.purchase-detail-view { display: flex; max-height: calc(100vh - 72px); flex-direction: column; background: var(--color-main-background); }
.purchase-detail-header { position: sticky; z-index: 3; top: 0; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 18px 24px; border-bottom: 1px solid var(--color-border); background: var(--color-main-background); }
.purchase-title-block, .purchase-header-actions, .requester-card, .history-heading { display: flex; align-items: center; gap: 12px; }
.purchase-title-block p, .purchase-title-block h2 { margin: 0; }.purchase-title-block p { color: var(--color-text-maxcontrast); font-size: 12px; font-weight: 700; text-transform: uppercase; }
.purchase-icon { display: grid; width: 52px; height: 52px; place-items: center; border-radius: 14px; background: var(--color-primary-element-light); color: var(--color-primary-element); }
.purchase-header-actions { justify-content: flex-end; flex-wrap: wrap; }
.purchase-detail-content { padding: 22px 24px 30px; overflow-y: auto; }
.status-chip { display: inline-flex; margin-top: 5px; padding: 3px 10px; border-radius: 999px; background: var(--color-background-dark); font-size: 12px; font-weight: 700; }.status-chip.is-autorizada { background: var(--color-success-hover); color: var(--color-success-text); }.status-chip.is-rechazada { background: var(--color-error-hover); color: var(--color-error-text); }.status-chip.is-pendiente_autorizacion { background: var(--color-warning-hover); }
.summary-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }.summary-grid > div { padding: 12px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-background-hover); }.summary-grid span, .requester-card span, .item-extra-grid span { display: block; color: var(--color-text-maxcontrast); font-size: 11px; font-weight: 700; text-transform: uppercase; }.summary-grid strong { display: block; margin-top: 5px; overflow-wrap: anywhere; }.summary-total { border-color: var(--color-primary-element) !important; }
.detail-section { margin-top: 22px; }.requester-card { display: grid; grid-template-columns: auto minmax(180px, 1fr) repeat(2, minmax(140px, 0.7fr)); padding: 16px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); }.requester-card > div:not(.requester-identity) { padding-left: 16px; border-left: 1px solid var(--color-border); }.requester-identity { display: flex; min-width: 0; flex-direction: column; }.requester-identity small { color: var(--color-text-maxcontrast); overflow-wrap: anywhere; }
.information-grid, .item-extra-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0; }.information-grid > div, .item-extra-grid > div { padding: 13px; border-radius: var(--border-radius); background: var(--color-background-hover); }.information-grid dt { color: var(--color-text-maxcontrast); font-size: 11px; font-weight: 700; text-transform: uppercase; }.information-grid dd { margin: 5px 0 0; white-space: pre-wrap; overflow-wrap: anywhere; }.span-full { grid-column: 1 / -1; }
.table-scroll { width: 100%; overflow-x: auto; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); }.items-table { width: 100%; border-collapse: collapse; }.items-table th, .items-table td { padding: 10px 11px; border-bottom: 1px solid var(--color-border); text-align: left; vertical-align: middle; }.items-table th { background: var(--color-background-hover); font-size: 11px; text-transform: uppercase; white-space: nowrap; }.item-detail-row td { padding: 14px; background: var(--color-background-hover); }.item-extra-grid p { margin: 5px 0 0; white-space: pre-wrap; }.is-open { transform: rotate(180deg); }
.document-groups { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.history-heading { justify-content: space-between; }.history-list { margin: 12px 0; padding: 0; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); list-style: none; }.history-list li { padding: 13px 15px; border-bottom: 1px solid var(--color-border); }.history-list li:last-child { border-bottom: none; }.history-list li > div { display: flex; justify-content: space-between; gap: 12px; }.history-list span { color: var(--color-text-maxcontrast); }.history-list p { margin: 5px 0 0; }
@media (max-width: 900px) { .purchase-detail-header { align-items: flex-start; flex-direction: column; }.purchase-header-actions { justify-content: flex-start; }.summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }.requester-card { grid-template-columns: auto minmax(0, 1fr); }.requester-card > div:not(.requester-identity) { grid-column: 1 / -1; padding: 8px 0 0; border-top: 1px solid var(--color-border); border-left: 0; }.information-grid, .item-extra-grid, .document-groups { grid-template-columns: 1fr; } }
@media (max-width: 560px) { .purchase-detail-header, .purchase-detail-content { padding-right: 14px; padding-left: 14px; }.summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.history-list li > div { flex-direction: column; gap: 3px; } }
</style>
