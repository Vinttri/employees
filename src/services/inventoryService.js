/* eslint-disable camelcase */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const appUrl = (path) => generateUrl(`/apps/employees${path}`)

export default {
	// =========================
	// Modelos
	// =========================

	async getModelos(params = {}) {
		const response = await axios.get(appUrl('/GetInventoryModelos'), { params })
		return response.data?.ocs?.data ?? response.data
	},

	async getModelo(id_model) {
		const response = await axios.post(appUrl('/GetInventoryModelo'), { id_model })
		return response.data?.ocs?.data ?? response.data
	},

	async crearModelo(data) {
		const response = await axios.post(appUrl('/CrearInventoryModelo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async actualizarModelo(data) {
		const response = await axios.post(appUrl('/ActualizarInventoryModelo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async eliminarModelo(id_model) {
		const response = await axios.post(appUrl('/EliminarInventoryModelo'), { id_model })
		return response.data?.ocs?.data ?? response.data
	},

	// =========================
	// Teams
	// =========================

	async getTeams(params = {}) {
		const response = await axios.get(appUrl('/GetInventoryComputo'), { params })
		return response.data?.ocs?.data ?? response.data
	},

	async getEquipo(id_team) {
		const response = await axios.post(appUrl('/GetInventoryEquipo'), { id_team })
		return response.data?.ocs?.data ?? response.data
	},

	async getHistoryEquipo(id_team, params = {}) {
		const response = await axios.get(appUrl(`/inventario/Team/${id_team}/historial`), { params })
		return response.data?.ocs?.data ?? response.data
	},

	async getTeamsEmpleado(id_employee) {
		const response = await axios.post(appUrl('/GetInventoryEmpleado'), { id_employee })
		return response.data?.ocs?.data ?? response.data
	},

	async crearEquipo(data) {
		const response = await axios.post(appUrl('/CrearInventoryEquipo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async actualizarEquipo(data) {
		const response = await axios.post(appUrl('/ActualizarInventoryEquipo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async eliminarEquipo(id_team) {
		const response = await axios.post(appUrl('/EliminarInventoryEquipo'), { id_team })
		return response.data?.ocs?.data ?? response.data
	},

	// =========================
	// Soporte
	// =========================

	async getSoporteEquipo(id_team) {
		const response = await axios.post(appUrl('/GetSoporteEquipo'), { id_team })
		return response.data?.ocs?.data ?? response.data
	},

	async createSupport(data) {
		const response = await axios.post(appUrl('/CrearSoporteEquipo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async actualizarSoporte(data) {
		const response = await axios.post(appUrl('/ActualizarSoporteEquipo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async eliminarSoporte(id_support) {
		const response = await axios.post(appUrl('/EliminarSoporteEquipo'), { id_support })
		return response.data?.ocs?.data ?? response.data
	},
}
