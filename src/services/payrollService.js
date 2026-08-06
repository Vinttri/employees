import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = path => generateUrl(`/apps/employees/payroll${path}`)
const data = response => response.data?.data ?? response.data

export default {
	async overview(periodId = null) {
		const response = await axios.get(url(''), { params: periodId ? { periodId } : {} })
		return data(response)
	},
	async createPeriod(payload) { return data(await axios.post(url('/periods'), payload)) },
	async createPlan(payload) { return data(await axios.post(url('/plans'), payload)) },
	async updatePlan(planId, payload) { return data(await axios.put(url(`/plans/${planId}`), payload)) },
	async createProfile(payload) { return data(await axios.post(url('/profiles'), payload)) },
	async updateProfile(profileId, payload) { return data(await axios.put(url(`/profiles/${profileId}`), payload)) },
	async createRule(profileId, payload) { return data(await axios.post(url(`/profiles/${profileId}/rules`), payload)) },
	async updateRule(ruleId, payload) { return data(await axios.put(url(`/rules/${ruleId}`), payload)) },
	async assignProfile(planId, payload) { return data(await axios.post(url(`/plans/${planId}/profiles`), payload)) },
	async updateProfileAssignment(assignmentId, payload) { return data(await axios.put(url(`/profile-assignments/${assignmentId}`), payload)) },
	async assignEmployeeRule(employeeId, payload) { return data(await axios.post(url(`/employees/${employeeId}/rules`), payload)) },
	async updateEmployeeRuleAssignment(assignmentId, payload) { return data(await axios.put(url(`/employee-rule-assignments/${assignmentId}`), payload)) },
	async addInput(periodId, payload) { return data(await axios.post(url(`/periods/${periodId}/inputs`), payload)) },
	async updateInput(inputId, payload) { return data(await axios.put(url(`/inputs/${inputId}`), payload)) },
	async deleteInput(inputId) { return data(await axios.delete(url(`/inputs/${inputId}`))) },
	async calculate(periodId) { return data(await axios.post(url(`/periods/${periodId}/calculate`))) },
	async approve(periodId) { return data(await axios.post(url(`/periods/${periodId}/approve`))) },
	async recordPayment(payslipId, payload) { return data(await axios.post(url(`/payslips/${payslipId}/payments`), payload)) },
	exportUrl(periodId) { return url(`/periods/${periodId}/export.csv`) },
	paymentExportUrl(periodId) { return url(`/periods/${periodId}/payments.csv`) },
}
