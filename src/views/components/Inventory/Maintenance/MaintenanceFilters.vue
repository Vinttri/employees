<template>
	<div class="filters">
		<label><span>{{ t('employees', 'Department') }}</span><select :value="value.departmentId" @change="update('departmentId', $event.target.value)"><option value="">{{ t('employees', 'All departments') }}</option><option v-for="item in departments" :key="departmentId(item)" :value="departmentId(item)">{{ departmentName(item) }}</option></select></label>
		<label v-if="showTechnician"><span>{{ t('employees', 'Technician') }}</span><select :value="value.technicianUid" :disabled="techniciansLoading || Boolean(techniciansError)" @change="update('technicianUid', $event.target.value)"><option value="">{{ technicianOptionLabel }}</option><option v-for="item in technicians" :key="item.uid" :value="item.uid">{{ item.displayName }}</option></select></label>
		<label><span>{{ t('employees', 'Type') }}</span><select :value="value.type" @change="update('type', $event.target.value)"><option value="">{{ t('employees', 'All types') }}</option><option value="preventive">{{ t('employees', 'Preventive') }}</option><option value="corrective">{{ t('employees', 'Corrective') }}</option><option value="special">{{ t('employees', 'Special') }}</option></select></label>
		<label><span>{{ t('employees', 'Status') }}</span><select :value="value.status" @change="update('status', $event.target.value)"><option value="">{{ t('employees', 'All statuses') }}</option><option value="active">{{ t('employees', 'Active') }}</option><option value="cancelled">{{ t('employees', 'Cancelled') }}</option></select></label>
		<label class="search"><span>{{ t('employees', 'Search') }}</span><input :value="search" type="search" @input="scheduleSearch($event.target.value)"></label>
		<label class="check"><input :checked="value.showOverdue" type="checkbox" @change="update('showOverdue', $event.target.checked)">{{ t('employees', 'Show overdue') }}</label>
		<NcButton type="tertiary" @click="clear">
			{{ t('employees', 'Clear filters') }}
		</NcButton>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
export default {
	name: 'MaintenanceFilters',
	components: { NcButton },
	props: { value: { type: Object, required: true }, departments: { type: Array, default: () => [] }, technicians: { type: Array, default: () => [] }, techniciansLoading: { type: Boolean, default: false }, techniciansError: { type: String, default: '' }, showTechnician: { type: Boolean, default: false } },
	data() { return { timer: null, search: this.value.search || '' } },
	computed: { technicianOptionLabel() { if (this.techniciansLoading) return t('employees', 'Loading technicians…'); if (this.techniciansError) return t('employees', 'Could not load technicians.'); if (!this.technicians.length) return t('employees', 'No technicians configured'); return t('employees', 'All technicians') } },
	beforeDestroy() { clearTimeout(this.timer) },
	methods: {
		t,
		departmentId(item) { return item.id_department || item.id_department || item.id },
		departmentName(item) { return item.name || item.name || item.name || this.departmentId(item) },
		update(key, value) { this.$emit('input', { ...this.value, [key]: value }); this.$emit('change') },
		scheduleSearch(value) { this.search = value; clearTimeout(this.timer); this.timer = setTimeout(() => this.update('search', value.trim()), 350) },
		clear() { this.search = ''; this.$emit('input', { departmentId: '', technicianUid: '', type: '', status: '', search: '', showOverdue: false }); this.$emit('change') },
	},
}
</script>

<style scoped lang="scss">
.filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; }
label { display: grid; gap: 4px; min-width: 150px; } label span { font-size: .85rem; color: var(--color-text-maxcontrast); }
select, input[type='search'] { min-height: 36px; padding: 6px 10px; border: 1px solid var(--color-border-maxcontrast); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-main-text); }
.search { flex: 1; min-width: 190px; } .check { display: flex; flex-direction: row; align-items: center; min-height: 36px; }
</style>
