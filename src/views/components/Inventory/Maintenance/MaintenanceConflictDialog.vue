<template>
	<NcModal :name="t('employees', 'Potential duplicate maintenance')" @close="$emit('review')">
		<div class="dialog">
			<h2>{{ t('employees', 'Potential duplicate maintenance') }}</h2><p>{{ t('employees', '{count} conflicting devices were found.', { count: conflicts.length }) }}</p><div class="table-wrap">
				<table>
					<thead><tr><th>{{ t('employees', 'Device') }}</th><th>{{ t('employees', 'Existing campaign') }}</th><th>{{ t('employees', 'Campaign period') }}</th><th>{{ t('employees', 'Type') }}</th><th>{{ t('employees', 'Status') }}</th></tr></thead><tbody>
						<tr v-for="(item, index) in conflicts" :key="item.id || index">
							<td>{{ item.team_identifier || item.equipmentId || item.id_team || '—' }}</td><td>{{ item.grupo_titulo || item.groupId || item.id_group || '—' }}</td><td>{{ formatDateRange(item.existingPeriodStart || item.date_scheduled, item.existingPeriodEnd || item.date_scheduled) }}</td><td>{{ maintenanceTypeLabel(item.type, t) }}</td><td>{{ maintenanceStatusLabel(item.status, t) }}</td>
						</tr>
					</tbody>
				</table>
			</div><div class="actions">
				<NcButton :disabled="busy" @click="$emit('cancel')">
					{{ t('employees', 'Cancel') }}
				</NcButton><NcButton :disabled="busy" @click="$emit('review')">
					{{ t('employees', 'Review selection') }}
				</NcButton><NcButton type="primary" :disabled="busy" @click="confirmOverride">
					{{ t('employees', 'Create anyway') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'; import { NcButton, NcModal } from '@nextcloud/vue'
import { formatDateRange, maintenanceStatusLabel, maintenanceTypeLabel } from '../../../../utils/maintenanceFormatters.js'
export default { name: 'MaintenanceConflictDialog', components: { NcButton, NcModal }, props: { conflicts: { type: Array, default: () => [] }, busy: { type: Boolean, default: false } }, methods: { t, formatDateRange, maintenanceStatusLabel, maintenanceTypeLabel, confirmOverride() { if (window.confirm(t('employees', 'Create the campaign despite potential duplicates?'))) this.$emit('confirm') } } }
</script>
<style scoped lang="scss">.dialog { padding: 24px; max-width: 850px; } .table-wrap { overflow-x: auto; } table { width: 100%; border-collapse: collapse; } th,td { padding: 8px; border-bottom: 1px solid var(--color-border); text-align: left; } .actions { display: flex; justify-content: end; gap: 8px; margin-top: 20px; flex-wrap: wrap; }</style>
