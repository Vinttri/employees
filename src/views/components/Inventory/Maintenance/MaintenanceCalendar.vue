<template>
	<div class="calendar">
		<FullCalendar ref="calendar" :options="options" />
	</div>
</template>
<script>
import FullCalendar from '@fullcalendar/vue'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'
import enGbLocale from '@fullcalendar/core/locales/en-gb'
import ruLocale from '@fullcalendar/core/locales/ru'
import { getLanguage, translate as t } from '@nextcloud/l10n'
import { addOneCalendarDay, maintenanceTypeLabel, toApiDate } from '../../../../utils/maintenanceFormatters.js'
const fullCalendarLocale = String(getLanguage() || 'en').toLowerCase().startsWith('ru') ? ruLocale : enGbLocale
export default {
	name: 'MaintenanceCalendar',
	components: { FullCalendar },
	props: { loadEvents: { type: Function, required: true }, refreshKey: { type: Number, default: 0 } },
	computed: { options() { return { plugins: [dayGridPlugin, interactionPlugin, multiMonthPlugin], locale: fullCalendarLocale, initialView: 'dayGridMonth', headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,multiMonthYear' }, buttonText: { today: t('employees', 'Today'), month: t('employees', 'Month'), year: t('employees', 'Year') }, events: this.fetchEvents, eventClick: info => this.$emit('open-group', Number(info.event.extendedProps.groupId)), fixedWeekCount: false, dayMaxEvents: true, height: 'auto' } } },
	watch: { refreshKey() { this.$refs.calendar?.getApi().refetchEvents() } },
	methods: {
		async fetchEvents(info, success, failure) {
			try {
				const groups = await this.loadEvents({ start: toApiDate(info.start), end: toApiDate(new Date(info.end.getTime() - 86400000)) })
				success(groups.map(group => { const progress = group.progress || group; const hasProgress = Object.prototype.hasOwnProperty.call(progress, 'total'); const completed = hasProgress ? Number(progress.completed || progress.completados || 0) : null; const total = hasProgress ? Number(progress.total || 0) : null; const ratio = hasProgress ? ` · ${completed}/${total}` : ''; const periodStart = group.periodStart || group.date_start || group.date_scheduled; const periodEnd = group.periodEnd || group.date_end || group.date_scheduled; return { id: String(group.id), title: `${group.department_name || group.title || ''} — ${maintenanceTypeLabel(group.type, t)}${ratio}`, start: periodStart, end: addOneCalendarDay(periodEnd), allDay: true, extendedProps: { groupId: group.id, departmentName: group.department_name, type: group.type, operationalStatus: progress.operational_status || progress.estado_operativo, completed, total, technicianName: group.technician_name, periodStart, periodEnd } } }))
			} catch (error) { this.$emit('load-error', error); failure(error) }
		},
	},
}
</script>
<style scoped>.calendar { min-width: 0; overflow-x: auto; } .calendar :deep(.fc) { min-width: 620px; }</style>
