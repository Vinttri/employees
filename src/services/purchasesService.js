import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const BASE_URL = '/apps/employees/purchases/solicitudes'

export async function listarSolicitudes(params = {}) {
	const response = await axios.get(generateUrl(BASE_URL), { params })
	return response.data
}

export async function listarPendientes(params = {}) {
	const response = await axios.get(generateUrl(`${BASE_URL}/pendientes`), { params })
	return response.data
}

export async function obtenerSolicitud(id) {
	const response = await axios.get(generateUrl(`${BASE_URL}/${id}`))
	return response.data
}

export async function obtenerFlujoSolicitud(id) {
	const response = await axios.get(generateUrl(`${BASE_URL}/${id}/flujo`))
	return response.data
}

export async function obtenerHistorySolicitud(id, params = {}) {
	const response = await axios.get(generateUrl(`${BASE_URL}/${id}/historial`), { params })
	return response.data
}

export async function crearSolicitud(payload) {
	const response = await axios.post(generateUrl(BASE_URL), payload)
	return response.data
}

export async function actualizarSolicitud(id, payload) {
	const response = await axios.put(generateUrl(`${BASE_URL}/${id}`), payload)
	return response.data
}

export async function enviarAutorizacion(id) {
	const response = await axios.post(generateUrl(`${BASE_URL}/${id}/enviar-autorizacion`), {})
	return response.data
}

export async function autorizarSolicitud(id, comment = '') {
	const response = await axios.post(generateUrl(`${BASE_URL}/${id}/autorizar`), {
		comment,
	})
	return response.data
}

export async function rechazarSolicitud(id, comment = '') {
	const response = await axios.post(generateUrl(`${BASE_URL}/${id}/rechazar`), {
		comment,
	})
	return response.data
}

export async function cancelarSolicitud(id, comment = '') {
	const response = await axios.post(generateUrl(`${BASE_URL}/${id}/cancelar`), {
		comment,
	})
	return response.data
}

export async function obtenerContextoPurchases() {
	const response = await axios.get(generateUrl('/apps/employees/purchases/contexto'))
	return response.data
}

export const guardarDocumentoSolicitud = async (id) => {
	const response = await axios.post(
		generateUrl('/apps/employees/purchases/solicitudes/{id}/document/guardar', { id }),
	)

	return response.data
}
export const subirDocumentoFirmadoSolicitud = async (id, file) => {
	const formData = new FormData()
	formData.append('file', file)

	const response = await axios.post(
		generateUrl('/apps/employees/purchases/solicitudes/{id}/document/firmado', { id }),
		formData,
		{
			headers: {
				'Content-Type': 'multipart/form-data',
			},
		},
	)

	return response.data
}
