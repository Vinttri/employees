<template>
	<div class="top">
		<!-- Marital status -->
		<div class="label-input-trabajo">
			<NcSelect
				v-model="status_marital"
				class="select"
				:disabled="!show"
				:input-label="t('employees', 'Marital status')"
				:options="EstadoCiviloptions" />
		</div>

		<!-- Gender -->
		<div class="label-input-trabajo">
			<NcSelect
				v-model="gender"
				class="select"
				:disabled="!show"
				:input-label="t('employees', 'Gender')"
				:options="GeneroOptions" />
		</div>

		<!-- Contact phone -->
		<div class="external-label">
			<label for="phone_contact" class="labeltype">
				<Badgeaccountoutline :size="20" />
				{{ t('employees', 'Contact number') }}
			</label>
			<input
				id="phone_contact"
				v-model="phone_contact"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<br>

		<!-- Address -->
		<div class="external-label field-wide">
			<label for="address" class="labeltype">
				<MapMarkerOutline :size="20" />
				{{ t('employees', 'Address') }}
			</label>
			<input
				id="address"
				v-model="address"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- RFC -->
		<div class="external-label">
			<label for="rfc" class="labeltype">
				<Badgeaccountoutline :size="20" />
				{{ t('employees', 'RFC') }}
			</label>
			<input
				id="rfc"
				v-model="rfc"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- IMSS -->
		<div class="external-label">
			<label for="imss" class="labeltype">
				<Badgeaccountoutline :size="20" />
				{{ t('employees', 'IMSS') }}
			</label>
			<input
				id="imss"
				v-model="imss"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- CURP -->
		<div class="external-label">
			<label for="curp" class="labeltype">
				<Badgeaccountoutline :size="20" />
				{{ t('employees', 'CURP') }}
			</label>
			<input
				id="curp"
				v-model="curp"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- Birth date -->
		<div class="external-label">
			<label for="date_birth" class="labeltype">
				<CakeVariantOutline :size="20" />
				{{ t('employees', 'Birth date') }}
			</label>
			<input
				id="date_birth"
				v-model="date_birth"
				type="date"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- Email -->
		<div class="external-label">
			<label for="email_contact" class="labeltype">
				<EmailOutline :size="20" />
				{{ t('employees', 'Email') }}
			</label>
			<input
				id="email_contact"
				v-model="email_contact"
				type="email"
				:disabled="!show"
				class="inputtype">
		</div>

		<br>

		<section class="emergency-contacts">
			<div class="section-heading">
				<div>
					<h3>{{ t('employees', 'Emergency contacts') }}</h3>
					<p>{{ t('employees', 'People to contact in case of an emergency.') }}</p>
				</div>
				<NcButton v-if="show" type="secondary" @click="openContactDialog()">
					{{ t('employees', 'Add contact') }}
				</NcButton>
			</div>
			<NcLoadingIcon v-if="loadingContacts" :size="32" />
			<p v-else-if="contacts.length === 0" class="empty-state">
				{{ t('employees', 'No emergency contacts registered.') }}
			</p>
			<div v-else class="contact-grid">
				<article v-for="contact in contacts" :key="contact.id" class="contact-card">
					<div class="contact-title">
						<div><strong>{{ contact.name }}</strong><span>{{ contact.relationship }}</span></div>
						<span v-if="contact.is_primary" class="primary-badge">{{ t('employees', 'Primary contact') }}</span>
					</div>
					<dl>
						<div><dt>{{ t('employees', 'Contact number') }}</dt><dd>{{ contact.number_contact }}</dd></div>
						<div v-if="contact.assistance_type">
							<dt>{{ t('employees', 'Help type') }}</dt><dd>{{ contact.assistance_type }}</dd>
						</div>
						<div v-if="contact.alternate_method">
							<dt>{{ t('employees', 'Alternative contact method') }}</dt><dd>{{ contact.alternate_method }}</dd>
						</div>
						<div v-if="contact.notes">
							<dt>{{ t('employees', 'Notes') }}</dt><dd>{{ contact.notes }}</dd>
						</div>
					</dl>
					<div v-if="show" class="contact-actions">
						<NcButton type="tertiary" @click="openContactDialog(contact)">
							{{ t('employees', 'Edit') }}
						</NcButton>
						<NcButton v-if="!contact.is_primary" type="tertiary" @click="markPrimary(contact)">
							{{ t('employees', 'Mark as primary') }}
						</NcButton>
						<NcButton type="error" @click="confirmDelete(contact)">
							{{ t('employees', 'Delete') }}
						</NcButton>
					</div>
				</article>
			</div>
		</section>

		<NcDialog :open.sync="showContactDialog"
			is-form
			:buttons="contactDialogButtons"
			:name="editingContact ? t('employees', 'Edit emergency contact') : t('employees', 'Add emergency contact')"
			@submit="saveContact">
			<div class="contact-form">
				<label>{{ t('employees', 'Full name') }} *<input v-model="contactForm.name"
					class="inputtype"
					maxlength="200"
					required></label>
				<label>{{ t('employees', 'Relationship') }} *<input v-model="contactForm.relationship"
					class="inputtype"
					maxlength="120"
					required></label>
				<label>{{ t('employees', 'Contact number') }} *<input v-model="contactForm.number_contact"
					class="inputtype"
					maxlength="80"
					required></label>
				<label>{{ t('employees', 'Alternative contact method') }}<input v-model="contactForm.alternate_method" class="inputtype" maxlength="255"></label>
				<label>{{ t('employees', 'Help type') }}<input v-model="contactForm.assistance_type"
					class="inputtype"
					maxlength="255"
					:placeholder="t('employees', 'For example: medical contact or transportation')"></label>
				<label class="form-wide">{{ t('employees', 'Notes') }}<textarea v-model="contactForm.notes" class="inputtype contact-notes" maxlength="2000" /></label>
				<NcCheckboxRadioSwitch v-model="contactForm.is_primary" class="form-wide" type="switch">
					{{ t('employees', 'Primary contact') }}
				</NcCheckboxRadioSwitch>
				<p v-if="formError" class="form-error form-wide">
					{{ formError }}
				</p>
			</div>
		</NcDialog>
		<NcDialog :open.sync="showDeleteDialog"
			:name="t('employees', 'Delete emergency contact?')"
			:message="t('employees', 'This emergency contact will be permanently deleted.')"
			:buttons="deleteDialogButtons" />
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import 'vue-nav-tabs/themes/vue-tabs.css'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

// ICONOS
import EmailOutline from 'vue-material-design-icons/EmailOutline.vue'
import Badgeaccountoutline from 'vue-material-design-icons/BadgeAccountOutline.vue'
import MapMarkerOutline from 'vue-material-design-icons/MapMarkerOutline.vue'
import CakeVariantOutline from 'vue-material-design-icons/CakeVariantOutline.vue'

import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcDialog,
	NcLoadingIcon,
	NcSelect,
} from '@nextcloud/vue'

const emptyContact = () => ({ name: '', relationship: '', number_contact: '', alternate_method: '', assistance_type: '', notes: '', is_primary: false })

export default {
	name: 'PersonalDetailsTab',

	components: {
		Badgeaccountoutline,
		MapMarkerOutline,
		CakeVariantOutline,
		EmailOutline,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
	},

	props: {
		data: {
			type: Object,
			required: true,
		},
		show: {
			type: Boolean,
			required: true,
		},
		Employee: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			contacts: [],
			loadingContacts: false,
			savingContact: false,
			showContactDialog: false,
			showDeleteDialog: false,
			editingContact: null,
			deletingContact: null,
			contactForm: emptyContact(),
			formError: '',
			address: '',
			status_marital: '',
			phone_contact: '',
			rfc: '',
			imss: '',
			emergency_contact: '',
			emergency_phone: '',
			curp: '',
			date_birth: '',
			email_contact: '',
			gender: '',
			// Opciones traducidas (keys en inglés)
			GeneroOptions: [t('employees', 'Male'), t('employees', 'Female')],
			EstadoCiviloptions: [
				t('employees', 'Single'),
				t('employees', 'Married'),
				t('employees', 'Divorced'),
				t('employees', 'Widowed'),
				t('employees', 'Domestic partnership'),
			],
		}
	},

	computed: {
		contactDialogButtons() {
			return [
				{ label: t('employees', 'Cancel'), callback: () => { this.showContactDialog = false } },
				{ label: t('employees', 'Save'), type: 'primary', nativeType: 'submit', disabled: this.savingContact },
			]
		},
		deleteDialogButtons() {
			return [
				{ label: t('employees', 'Cancel'), callback: () => { this.showDeleteDialog = false } },
				{ label: t('employees', 'Delete'), type: 'error', disabled: this.savingContact, callback: this.deleteContact },
			]
		},
	},

	watch: {
		data(news) {
			if (news) {
				this.setAttr(news)
				this.loadContacts()
			}
		},
	},

	mounted() {
		this.setAttr(this.data)
		this.loadContacts()
	},

	methods: {
		t,

		contactsUrl(suffix = '') {
			return generateUrl(`/apps/employees/employees/${this.data.id_employees}/contactos-emergencia${suffix}`)
		},

		async loadContacts() {
			if (!this.data.id_employees) return
			this.loadingContacts = true
			try {
				const response = await axios.get(this.contactsUrl())
				this.contacts = response?.data?.ocs?.data.contactos || []
			} catch (error) {
				showError(t('employees', 'Could not load emergency contacts: {error}', { error: this.errorMessage(error) }))
			} finally {
				this.loadingContacts = false
			}
		},

		openContactDialog(contact = null) {
			this.editingContact = contact
			this.contactForm = contact ? { ...emptyContact(), ...contact } : emptyContact()
			this.formError = ''
			this.showContactDialog = true
		},

		async saveContact() {
			if (this.savingContact) return
			const payload = Object.fromEntries(Object.entries(this.contactForm).map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value]))
			if (!payload.name || !payload.relationship || !payload.number_contact) {
				this.formError = t('employees', 'Full name, relationship and contact number are required.')
				return
			}
			this.savingContact = true
			try {
				if (this.editingContact) await axios.put(this.contactsUrl(`/${this.editingContact.id}`), payload)
				else await axios.post(this.contactsUrl(), payload)
				this.showContactDialog = false
				showSuccess(t('employees', 'Emergency contact saved.'))
				await this.loadContacts()
			} catch (error) {
				showError(t('employees', 'Could not save emergency contact: {error}', { error: this.errorMessage(error) }))
			} finally {
				this.savingContact = false
			}
		},

		confirmDelete(contact) {
			this.deletingContact = contact
			this.showDeleteDialog = true
		},

		async deleteContact() {
			if (!this.deletingContact || this.savingContact) return
			this.savingContact = true
			try {
				await axios.delete(this.contactsUrl(`/${this.deletingContact.id}`))
				this.showDeleteDialog = false
				showSuccess(t('employees', 'Emergency contact deleted.'))
				await this.loadContacts()
			} catch (error) {
				showError(t('employees', 'Could not delete emergency contact: {error}', { error: this.errorMessage(error) }))
			} finally {
				this.savingContact = false
			}
		},

		async markPrimary(contact) {
			if (this.savingContact) return
			this.savingContact = true
			try {
				await axios.post(this.contactsUrl(`/${contact.id}/principal`))
				showSuccess(t('employees', 'Primary emergency contact updated.'))
				await this.loadContacts()
			} catch (error) {
				showError(t('employees', 'Could not update primary contact: {error}', { error: this.errorMessage(error) }))
			} finally {
				this.savingContact = false
			}
		},

		errorMessage(error) {
			return error?.response?.data?.message || error?.message || String(error)
		},

		setAttr(data) {
			this.address = this.checknull(data.address)
			this.status_marital = this.checknull(data.status_marital)
			this.phone_contact = this.checknull(data.phone_contact)
			this.rfc = this.checknull(data.rfc)
			this.imss = this.checknull(data.imss)
			this.emergency_contact = this.checknull(data.emergency_contact)
			this.emergency_phone = this.checknull(data.emergency_phone)
			this.curp = this.checknull(data.curp)
			this.date_birth = this.checknull(data.date_birth)
			this.email_contact = this.checknull(data.email_contact)
			this.gender = this.checknull(data.gender)
		},

		checknull(value) {
			return value === null ? '' : value
		},

		async saveFromEmployeeToolbar() {
			return this.CambiosPersonal(false)
		},

		async CambiosPersonal(closeEditing = true) {
			try {
				await axios.post(generateUrl('/apps/employees/CambiosPersonal'), {
					id_employees: this.data.id_employees,
					address: this.checknull(this.address),
					status_marital: this.checknull(this.status_marital),
					phone_contact: this.checknull(this.phone_contact), // fix: sin tilde
					rfc: this.checknull(this.rfc),
					imss: this.checknull(this.imss),
					emergency_contact: this.checknull(this.emergency_contact),
					emergency_phone: this.checknull(this.emergency_phone),
					curp: this.checknull(this.curp),
					date_birth: this.checknull(this.date_birth),
					email_contact: this.checknull(this.email_contact),
					gender: this.checknull(this.gender),
				})
				if (closeEditing) {
					this.$bus.emit('getall')
					this.$bus.emit('show', false)
					showSuccess(t('employees', 'Data updated'))
				}
				return true
			} catch (err) {
				showError(t('employees', 'An exception has occurred [03] [{error}]', { error: String(err) }))
				return false
			}
		},
	},
}
</script>

<style scoped>
.wrapper {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	align-items: flex-end;
}

.external-label {
	display: flex;
	flex-direction: column;
	align-items: stretch;
	min-width: 0;
	gap: 6px;
}

.labeltype {
	display: inline-flex;
	align-items: center;
	min-height: 24px;
	gap: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.labeltype .material-design-icon {
	color: var(--color-primary-element);
}

.inputtype {
	width: 100%;
	min-height: 40px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 14px;
	transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
}

.inputtype:focus {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element-light);
	outline: none;
}

.inputtype:disabled {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	cursor: not-allowed;
	opacity: 1;
}

.inputtype:hover:not(:disabled) {
	border-color: var(--color-primary-element-light);
}

.emergency-contacts {
	grid-column: 1 / -1;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.top > br {
	display: none;
}

.section-heading,
.contact-title,
.contact-actions {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.section-heading h3 { margin: 0; font-size: 18px; }
.section-heading p { margin: 3px 0 0; color: var(--color-text-maxcontrast); }
.empty-state { padding: 24px; text-align: center; color: var(--color-text-maxcontrast); }
.contact-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
.contact-card { min-width: 0; padding: 16px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-background-hover); }
.contact-title > div { display: flex; flex-direction: column; min-width: 0; }
.contact-title strong { overflow-wrap: anywhere; font-size: 16px; }
.contact-title span:not(.primary-badge) { color: var(--color-text-maxcontrast); }
.primary-badge { padding: 3px 8px; border-radius: 12px; background: var(--color-primary-element-light); color: var(--color-primary-element-text); font-size: 12px; white-space: nowrap; }
.contact-card dl { margin: 14px 0; }
.contact-card dl > div { margin-top: 8px; }
.contact-card dt { color: var(--color-text-maxcontrast); font-size: 12px; font-weight: 600; }
.contact-card dd { margin: 2px 0 0; overflow-wrap: anywhere; white-space: pre-wrap; }
.contact-actions { justify-content: flex-end; flex-wrap: wrap; }
.contact-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 12px; }
.contact-form label { display: flex; flex-direction: column; gap: 5px; font-weight: 600; }
.form-wide { grid-column: 1 / -1; }
.contact-notes { min-height: 90px; resize: vertical; }
.form-error { margin: 0; color: var(--color-error); }

.top {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
	margin-top: 14px;
}

.label-input-trabajo {
	min-width: 0;
}

.select {
	width: 100%;
}

.field-wide {
	grid-column: 1 / -1;
}

.div-center {
	display: flex;
	grid-column: 1 / -1;
	justify-content: center;
	margin-top: 8px;
}

@media (max-width: 768px) {
	.top,
	.contact-grid,
	.contact-form {
		grid-template-columns: 1fr;
	}

	.top {
		gap: 12px;
		margin-top: 8px;
	}

	.emergency-contacts {
		padding: 14px;
	}

	.section-heading { align-items: flex-start; flex-direction: column; }
	.form-wide { grid-column: auto; }
}
</style>
