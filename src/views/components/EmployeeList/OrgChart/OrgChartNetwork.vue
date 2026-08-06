<template>
	<div class="organigrama-wrapper">
		<NcEmptyContent v-if="loading" :name="t('employees', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>

		<template v-else>
			<div class="organigrama-toolbar">
				<div class="organigrama-view-switch">
					<button
						type="button"
						class="view-switch-btn"
						:class="{ active: viewMode === 'traditional' }"
						:aria-pressed="viewMode === 'traditional' ? 'true' : 'false'"
						@click="setViewMode('traditional')">
						{{ t('employees', 'Organization chart') }}
					</button>
					<button
						type="button"
						class="view-switch-btn"
						:class="{ active: viewMode === 'table' }"
						:aria-pressed="viewMode === 'table' ? 'true' : 'false'"
						@click="setViewMode('table')">
						{{ t('employees', 'Table') }}
					</button>
					<button
						type="button"
						class="view-switch-btn"
						:class="{ active: viewMode === 'network' }"
						:aria-pressed="viewMode === 'network' ? 'true' : 'false'"
						@click="setViewMode('network')">
						{{ t('employees', 'Network') }}
					</button>
				</div>
				<div v-if="viewMode === 'network'" class="organigrama-network-actions">
					<button type="button" class="view-switch-btn" @click="fitNetwork">
						{{ t('employees', 'Fit') }}
					</button>
					<button
						type="button"
						class="view-switch-btn"
						:class="{ active: networkEditMode }"
						:aria-pressed="networkEditMode ? 'true' : 'false'"
						@click="toggleNetworkEditMode">
						{{ networkEditMode ? t('employees', 'Finish editing') : t('employees', 'Edit relationships') }}
					</button>
				</div>
			</div>
			<div class="organigrama-hint" :class="{ 'organigrama-hint--active': connectMode }">
				<span>{{ viewHint }}</span>
				<button
					v-if="connectMode"
					type="button"
					class="cancel-connection-btn"
					@click="exitConnectMode">
					{{ t('employees', 'Cancel') }}
				</button>
			</div>

			<div class="organigrama-content">
				<div
					v-show="viewMode === 'network'"
					ref="networkContainer"
					class="organigrama-network"
					:class="{ 'organigrama-network--connecting': connectMode }" />
				<OrgChartTraditional
					v-if="viewMode === 'traditional'"
					class="organigrama-network"
					:Employee="employees"
					:relaciones="relaciones" />
				<OrgChartTable
					v-if="viewMode === 'table'"
					class="organigrama-network"
					:Employee="employees"
					:relaciones="relaciones" />
			</div>
		</template>
	</div>
</template>

<script>
import { Network } from 'vis-network'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { getNextcloudThemeColor, getNextcloudThemeColorWithAlpha } from '../../../../utils/nextcloudTheme.js'
import OrgChartTable from './OrgChartTable.vue'
import OrgChartTraditional from './OrgChartTraditional.vue'

import {
	NcEmptyContent,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'OrgChartNetwork',

	components: {
		NcEmptyContent,
		NcLoadingIcon,
		OrgChartTable,
		OrgChartTraditional,
	},

	data() {
		return {
			loading: true,
			network: null,
			Employee: [],
			relaciones: [],
			posiciones: {},
			connectMode: false,
			networkEditMode: false,
			connectionSourceId: null,
			connectionPending: false,
			viewMode: 'traditional',
			ringRadii: [0, 0, 0, 0, 0, 0],
			espacioPorEmpleado: 70,
			radioMinimoEntreAnillos: 300,
		}
	},

	computed: {
		employees() {
			return Array.isArray(this.Employee) ? this.Employee : []
		},

		viewHint() {
			if (this.connectMode) {
				const source = this.employees.find(
					employee => String(employee.id_employees) === String(this.connectionSourceId),
				)
				const sourceName = source?.id_user || t('employees', 'the selected employee')
				return t(
					'employees',
					'Creating a connection from {employee}. Click the employee who will report to them.',
					{ employee: sourceName },
				)
			}
			if (this.viewMode === 'traditional') {
				return t('employees', 'This view shows the complete hierarchical structure. Edit relationships from the Network view.')
			}
			if (this.viewMode === 'table') {
				return t('employees', 'Expand a manager to see all their direct and indirect reports.')
			}
			if (!this.networkEditMode) {
				return t('employees', 'Network view is read-only. Select Edit relationships to change reporting lines.')
			}
			return t('employees', 'Drag an avatar to move it. To create a connection, double-click the manager and then click their dependent. Double-click a connection to remove it.')
		},

		ringLabels() {
			return [
				null,
				t('employees', 'Partners'),
				t('employees', 'Managers'),
				t('employees', 'Supervisors'),
				t('employees', 'Analysts'),
				t('employees', 'Staff'),
			]
		},
	},

	async mounted() {
		window.addEventListener('keydown', this.handleKeydown)
		await this.cargarDatos()
		this.loading = false
		this.initializeNetwork()
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.handleKeydown)
		if (this.network) {
			this.network.destroy()
		}
	},

	methods: {
		t,

		initializeNetwork(attempt = 0) {
			this.$nextTick(() => {
				if (this._isDestroyed || this._isBeingDestroyed) return
				if (this.buildNetwork() || attempt >= 3) return
				window.setTimeout(() => this.initializeNetwork(attempt + 1), 50)
			})
		},

		setViewMode(viewMode) {
			const previousViewMode = this.viewMode
			if (viewMode !== 'network') {
				this.exitConnectMode()
			}
			this.viewMode = viewMode

			if (viewMode === 'network' && previousViewMode !== 'network') {
				this.$nextTick(() => {
					if (!this.network) return
					this.network.redraw()
					this.network.fit()
				})
			}
		},

		fitNetwork() {
			this.network?.fit({ animation: true })
		},

		toggleNetworkEditMode() {
			this.networkEditMode = !this.networkEditMode
			if (!this.networkEditMode) this.exitConnectMode()
			this.initializeNetwork()
		},

		async cargarDatos() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetOrgChart'))
				const data = response?.data?.ocs?.data ?? response?.data ?? {}
				const employees = data?.employees ?? data?.Employee ?? []
				this.Employee = Array.isArray(employees)
					? employees.map(this.normalizeEmployee)
					: []
				this.relaciones = data?.relaciones || []

				const posMap = {}
				;(data?.posiciones || []).forEach(p => {
					posMap[p.id_employee] = { x: Number(p.pos_x), y: Number(p.pos_y) }
				})
				this.posiciones = posMap
			} catch (err) {
				showError(t('employees', 'An exception has occurred [01] [{error}]', { error: String(err) }))
			}
		},

		avatarUrl(userId) {
			return generateUrl('/avatar/{userId}/64', { userId })
		},

		normalizeEmployee(employee) {
			const source = employee && typeof employee === 'object' ? employee : {}
			const uid = String(source.id_user ?? source.employee_uid ?? source.uid ?? '').trim()
			const displayName = String(source.displayname ?? source.display_name ?? source.name ?? uid).trim()

			return {
				...source,
				id_employees: Number(source.id_employees ?? source.id_employee),
				id_user: uid,
				uid,
				displayname: displayName || uid,
			}
		},

		// Calcula el level jerárquico (anillo) de cada empleado a partir de las
		// relaciones jefe -> dependiente, vía BFS desde las "raíces" (quienes no
		// dependen de nadie, es decir, el/los patrón(es)).
		calcularNiveles() {
			const niveles = {}
			const hijosDe = {}
			const esDependiente = new Set()

			this.relaciones.forEach(rel => {
				esDependiente.add(String(rel.id_dependent))
				if (!hijosDe[rel.id_employee]) hijosDe[rel.id_employee] = []
				hijosDe[rel.id_employee].push(rel.id_dependent)
			})

			const raices = this.Employee
				.map(emp => emp.id_employees)
				.filter(id => !esDependiente.has(String(id)))

			const visitado = new Set(raices.map(id => String(id)))
			const cola = raices.map(id => ({ id, level: 0 }))

			while (cola.length) {
				const { id, level } = cola.shift()
				niveles[id] = Math.min(level, this.ringLabels.length - 1)
				const hijos = hijosDe[id] || []
				hijos.forEach(hijoId => {
					if (!visitado.has(String(hijoId))) {
						visitado.add(String(hijoId))
						cola.push({ id: hijoId, level: level + 1 })
					}
				})
			}

			// Empleados sin ninguna relación: los mandamos al anillo exterior (staff)
			this.employees.forEach(emp => {
				if (!(emp.id_employees in niveles)) {
					niveles[emp.id_employees] = this.ringLabels.length - 1
				}
			})

			return niveles
		},

		// Calcula el radio de cada anillo según cuántos Employee le tocan.
		// Entre más gente en un level, más grande su circunferencia, para que
		// siempre haya "espacioPorEmpleado" px de separación entre avatares.
		calcularRadios(niveles) {
			const conteoPorNivel = [0, 0, 0, 0, 0, 0]
			Object.values(niveles).forEach(level => {
				conteoPorNivel[level] = (conteoPorNivel[level] || 0) + 1
			})

			const radios = [0]
			for (let level = 1; level < conteoPorNivel.length; level++) {
				const quantity = conteoPorNivel[level] || 0
				// Radio necesario para que la circunferencia completa (2πr) scope
				// a darle "espacioPorEmpleado" px a cada quien.
				const radioPorCantidad = (quantity * this.espacioPorEmpleado) / (2 * Math.PI)
				// Nunca más chico que el anillo anterior + un mínimo de separación.
				const radioMinimo = radios[level - 1] + this.radioMinimoEntreAnillos
				radios.push(Math.max(radioPorCantidad, radioMinimo))
			}

			return radios
		},

		// Calcula el ángulo de cada empleado dentro de su anillo, agrupando a
		// los hijos cerca del ángulo de su jefe para minimizar cruces de líneas.
		calcularAngulos(niveles) {
			const angulos = {}
			const padreDe = {}
			this.relaciones.forEach(rel => {
				padreDe[rel.id_dependent] = rel.id_employee
			})

			const raices = this.Employee
				.map(emp => emp.id_employees)
				.filter(id => niveles[id] === 0)

			raices.forEach((id, index) => {
				angulos[id] = (2 * Math.PI * index) / Math.max(raices.length, 1)
			})

			const maxNivel = Math.max(0, ...Object.values(niveles))
			for (let level = 1; level <= maxNivel; level++) {
				const idsDelNivel = this.Employee
					.map(emp => emp.id_employees)
					.filter(id => niveles[id] === level)

				idsDelNivel.sort((a, b) => {
					const anguloA = angulos[padreDe[a]] ?? 0
					const anguloB = angulos[padreDe[b]] ?? 0
					if (anguloA !== anguloB) return anguloA - anguloB
					return String(a).localeCompare(String(b))
				})

				idsDelNivel.forEach((id, index) => {
					angulos[id] = (2 * Math.PI * index) / Math.max(idsDelNivel.length, 1)
				})
			}

			return angulos
		},

		buildNetwork() {
			const container = this.$refs.networkContainer
			if (!container) return false

			if (this.network) {
				this.network.destroy()
				this.network = null
			}

			const niveles = this.calcularNiveles()
			this.ringRadii = this.calcularRadios(niveles)
			const angulos = this.calcularAngulos(niveles)

			const nodes = this.employees.map(emp => {
				const guardada = this.posiciones[emp.id_employees]
				const nodo = {
					id: emp.id_employees,
					label: emp.id_user,
					shape: 'circularImage',
					image: this.avatarUrl(emp.id_user),
					brokenImage: this.avatarUrl(emp.id_user),
					size: 28,
					physics: false,
					fixed: !this.networkEditMode,
				}

				if (guardada) {
					nodo.x = guardada.x
					nodo.y = guardada.y
				} else {
					const level = niveles[emp.id_employees] ?? this.ringLabels.length - 1
					const angulo = angulos[emp.id_employees] ?? 0
					const radio = this.ringRadii[level]
					nodo.x = radio * Math.cos(angulo)
					nodo.y = radio * Math.sin(angulo)
				}

				return nodo
			})

			const edges = this.relaciones.map(rel => ({
				id: `${rel.id_employee}-${rel.id_dependent}`,
				from: rel.id_employee,
				to: rel.id_dependent,
				arrows: 'to',
			}))

			const options = {
				physics: {
					enabled: false,
				},
				edges: {
					smooth: { type: 'continuous' },
					color: {
						color: getNextcloudThemeColor('--color-text-maxcontrast'),
						highlight: getNextcloudThemeColor('--color-primary-element'),
					},
				},
				nodes: {
					borderWidth: 2,
					font: { size: 12 },
				},
				manipulation: {
					enabled: false,
					addEdge: (edgeData, callback) => {
						if (edgeData.from === edgeData.to) {
							showError(t('employees', 'An employee cannot depend on themselves'))
							callback(null)
							this.exitConnectMode()
							return
						}
						this.crearRelacion(edgeData.from, edgeData.to, callback)
					},
				},
				interaction: {
					hover: true,
				},
			}

			this.network = new Network(
				container,
				{ nodes, edges },
				options,
			)

			this.network.on('beforeDrawing', (ctx) => {
				ctx.save()

				for (let level = 1; level < this.ringRadii.length; level++) {
					const radio = this.ringRadii[level]
					if (!radio) continue

					ctx.beginPath()
					ctx.arc(0, 0, radio, 0, 2 * Math.PI)
					ctx.strokeStyle = getNextcloudThemeColor('--color-primary-element')
					ctx.lineWidth = 1
					ctx.setLineDash([4, 6])
					ctx.stroke()

					const label = this.ringLabels[level]
					if (label) {
						ctx.font = 'bold 26px sans-serif'
						ctx.fillStyle = getNextcloudThemeColorWithAlpha('--color-text-maxcontrast', 0.4)
						ctx.textAlign = 'center'
						ctx.setLineDash([])
						ctx.fillText(label, 0, -radio - 6)
					}
				}

				ctx.restore()
			})

			this.network.fit()

			this.network.on('dragEnd', (params) => {
				if (!this.networkEditMode) return
				if (params.nodes.length === 1) {
					const idEmployee = params.nodes[0]
					const pos = this.network.getPositions([idEmployee])[idEmployee]
					if (pos) {
						this.guardarPosicion(idEmployee, pos.x, pos.y)
					}
				}
			})

			this.network.on('doubleClick', (params) => {
				if (!this.networkEditMode) return
				if (params.nodes.length === 1) {
					if (!this.connectMode) {
						this.enterConnectMode(params.nodes[0])
					}
				} else if (params.nodes.length === 0 && params.edges.length === 1) {
					const edgeId = params.edges[0]
					const edge = this.network.body.data.edges.get(edgeId)
					if (edge) {
						this.eliminarRelacion(edge.from, edge.to, edge.id)
					}
				}
			})

			this.network.on('click', (params) => {
				if (!this.networkEditMode) return
				if (!this.connectMode || params.nodes.length !== 1) return
				this.completeConnection(params.nodes[0])
			})

			return true
		},

		handleKeydown(event) {
			if (event.key === 'Escape' && this.connectMode) {
				this.exitConnectMode()
			}
		},

		enterConnectMode(sourceId) {
			if (this.connectMode) return
			this.connectMode = true
			this.connectionSourceId = sourceId
			this.network.selectNodes([sourceId])
		},

		completeConnection(targetId) {
			if (
				!this.connectMode
				|| this.connectionPending
				|| String(targetId) === String(this.connectionSourceId)
			) {
				return
			}

			const sourceId = this.connectionSourceId
			const alreadyExists = this.relaciones.some(
				relation => String(relation.id_employee) === String(sourceId)
					&& String(relation.id_dependent) === String(targetId),
			)
			if (alreadyExists) {
				showError(t('employees', 'This connection already exists'))
				this.exitConnectMode()
				return
			}

			this.connectionPending = true
			this.crearRelacion(sourceId, targetId, edgeData => {
				if (edgeData) {
					this.network.body.data.edges.add(edgeData)
				}
			})
		},

		exitConnectMode() {
			this.connectMode = false
			this.connectionSourceId = null
			this.connectionPending = false
			if (this.network) {
				this.network.unselectAll()
			}
		},

		async guardarPosicion(idEmployee, x, y) {
			try {
				await axios.post(generateUrl('/apps/employees/GuardarPosicionOrgChart'), {
					id_employee: idEmployee,
					x,
					y,
				})
			} catch (err) {
				showError(t('employees', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async crearRelacion(idEmployee, idDependiente, callback) {
			try {
				await axios.post(generateUrl('/apps/employees/CrearRelacionOrgChart'), {
					id_employee: idEmployee,
					id_dependent: idDependiente,
				})
				callback({
					id: `${idEmployee}-${idDependiente}`,
					from: idEmployee,
					to: idDependiente,
					arrows: 'to',
				})

				this.relaciones = [
					...this.relaciones,
					{ id_employee: idEmployee, id_dependent: idDependiente },
				]

				showSuccess(t('employees', 'Connection created'))
			} catch (err) {
				callback(null)
				showError(t('employees', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			} finally {
				this.exitConnectMode()
			}
		},

		async eliminarRelacion(idEmployee, idDependiente, edgeId) {
			try {
				await axios.post(generateUrl('/apps/employees/EliminarRelacionOrgChart'), {
					id_employee: idEmployee,
					id_dependent: idDependiente,
				})
				this.network.body.data.edges.remove(edgeId)

				this.relaciones = this.relaciones.filter(
					rel => !(rel.id_employee === idEmployee && rel.id_dependent === idDependiente),
				)

				showSuccess(t('employees', 'Connection removed'))
			} catch (err) {
				showError(t('employees', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style scoped lang="scss">
.organigrama-wrapper {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.organigrama-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 8px 0;
}

.organigrama-hint {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin: 0;
	padding: 8px 16px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.organigrama-hint--active {
	color: var(--color-main-text);
	background: var(--color-primary-element-light);
	border-radius: var(--border-radius-large);
}

.cancel-connection-btn {
	flex: 0 0 auto;
	border: 0;
	background: transparent;
	color: var(--color-primary-element);
	font-weight: 600;
	cursor: pointer;
}

.organigrama-content {
	position: relative;
	flex: 1;
	min-height: 500px;
}

.organigrama-network {
	position: absolute;
	inset: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.organigrama-network--connecting {
	:deep(canvas) {
		cursor: crosshair;
	}
}

.organigrama-view-switch {
	display: inline-flex;
	gap: 2px;
	padding: 4px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 999px;
}

.organigrama-network-actions {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.view-switch-btn {
	border: none;
	background: transparent;
	padding: 7px 18px;
	border-radius: 999px;
	font-size: 12.5px;
	font-weight: 600;
	letter-spacing: 0.02em;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;

	&:hover {
		color: var(--color-main-text);
	}

	&.active {
		background: var(--color-primary-element);
		color: var(--color-primary-element-text);
		box-shadow: 0 2px 10px rgb(from var(--color-box-shadow) r g b / 0.35);
	}
}

@media (max-width: 600px) {
	.organigrama-toolbar {
		align-items: stretch;
		flex-direction: column;
	}

	.organigrama-view-switch {
		justify-content: center;
	}

	.organigrama-network-actions {
		justify-content: flex-end;
	}

	.view-switch-btn {
		flex: 1;
		padding: 7px 8px;
	}
}
</style>
