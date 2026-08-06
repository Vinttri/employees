import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = path => generateUrl(`/apps/employees/ai-import${path}`)
const data = response => response.data?.data ?? response.data

export default {
	async targets() { return data(await axios.get(url('/targets'))) },
	async create(target, { text, file }) {
		const form = new FormData()
		if (file) form.append('file', file)
		else form.append('text', text || '')
		return data(await axios.post(url(`/${encodeURIComponent(target)}`), form))
	},
	async get(id) { return data(await axios.get(url(`/batches/${id}`))) },
	async review(id, rows) { return data(await axios.post(url(`/batches/${id}/review`), { rows })) },
	async apply(id, rows) { return data(await axios.post(url(`/batches/${id}/apply`), { rows })) },
}
