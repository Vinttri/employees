<template>
	<div class="employees-dashboard-widget">
		<p class="description">
			Registra tu tiempo del día sin abrir el módulo completo.
		</p>

		<div class="status-card" :class="estadoClass">
			<div class="status-title">
				status de hoy
			</div>

			<div v-if="loadingEstado" class="status-value">
				Cargando...
			</div>

			<div v-else class="status-value">
				{{ estadoLabel }}
			</div>

			<div class="status-detail">
				Horas reportadas: {{ horasHoy }} h
			</div>
		</div>

		<NcButton
			type="primary"
			wide
			@click="openModal">
			Reportar tiempo
		</NcButton>

		<ReportTimeModal v-if="modal" @created="loadEstadoHoy" @close="closeModal" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import ReportTimeModal from '../views/components/reports/ReportTimeModal.vue'

import {
	NcButton,
} from '@nextcloud/vue'

export default {
	name: 'DashboardReportsWidget',

	components: {
		NcButton,
		ReportTimeModal,
	},

	data() {
		return {
			modal: false,
		}
	},

	computed: {
		estadoLabel() {
			const status = this.estadoHoy?.status

			if (status === 'reportado') {
				return 'Reportado'
			}

			if (status === 'sin_empleado') {
				return 'Sin empleado asignado'
			}

			return 'Pendiente'
		},

		estadoClass() {
			const status = this.estadoHoy?.status

			if (status === 'reportado') {
				return 'status-ok'
			}

			if (status === 'sin_empleado') {
				return 'status-warning'
			}

			return 'status-pending'
		},

		horasHoy() {
			return Number(this.estadoHoy?.horas_reportadas || 0).toFixed(2)
		},
	},

	async mounted() {
		await this.loadEstadoHoy()
	},

	methods: {
		t,
		openModal() {
			this.modal = true
		},

		closeModal() {
			this.modal = false
		},

		async loadEstadoHoy() {
			this.loadingEstado = true

			try {
				const response = await axios.get(generateUrl('/apps/employees/estadoReporteHoy'))

				this.estadoHoy = response?.data?.ocs?.data ?? response?.data ?? null
			} catch (err) {
				showError(t('employees', 'No se pudo cargar el status de hoy: {error}', { error: String(err) }))
			} finally {
				this.loadingEstado = false
			}
		},
	},
}
</script>

<style scoped>
.employees-dashboard-widget {
	padding: 12px;
}

.description {
	margin-bottom: 12px;
	color: var(--color-text-maxcontrast);
}

.fit {
	width: 100%;
}

.time-selector {
	display: flex;
	gap: 8px;
	margin: 12px 0;
	align-items: center;
}

.radios {
	display: flex;
	margin: 8px 0 12px;
}

.estimatetime {
	flex: 1;
}

.date-picker {
	min-width: 180px;
}

.top {
	margin-top: 12px;
}

.save {
	display: flex;
	justify-content: flex-end;
}
.status-card {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 12px;
	margin-bottom: 14px;
	background-color: var(--color-background-hover);
}

.status-title {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
}

.status-value {
	font-size: 20px;
	font-weight: 700;
	margin-bottom: 4px;
}

.status-detail {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.status-ok {
	border-left: 5px solid #46ba61;
}

.status-pending {
	border-left: 5px solid #e9322d;
}

.status-warning {
	border-left: 5px solid #eca700;
}
</style>
