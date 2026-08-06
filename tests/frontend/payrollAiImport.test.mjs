import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const read = path => readFile(new URL(`../../${path}`, import.meta.url), 'utf8')
const [router, navigation, payroll, aiImport, payrollService, aiService, routes, migration, canonical, repository, aiImportBackend] = await Promise.all([
	read('src/router/index.js'), read('src/views/navigator/SideNavigation.vue'),
	read('src/views/components/Payroll/Payroll.vue'), read('src/views/components/AiImport/AiImport.vue'),
	read('src/services/payrollService.js'), read('src/services/aiImportService.js'),
	read('appinfo/routes.php'), read('lib/Migration/Version2052Date20260806120000.php'),
	read('lib/Migration/CanonicalSchema.php'), read('lib/Db/PayrollRepository.php'),
	read('lib/Service/AiImportService.php'),
])

assert.match(router, /name:\s*'Payroll'/)
assert.match(router, /name:\s*'AiImport'/)
assert.match(navigation, /modulo_payroll/)
assert.match(payroll, /payrollService\.calculate/)
assert.match(payroll, /payrollService\.approve/)
assert.match(payroll, /payrollService\.recordPayment/)
assert.match(payroll, /payrollService\.assignEmployeeRule/)
assert.match(payroll, /payrollService\.updatePlan/)
assert.match(payroll, /payrollService\.updateProfile/)
assert.match(payroll, /payrollService\.updateRule/)
assert.match(payroll, /payrollService\.updateInput/)
assert.match(payroll, /payrollService\.deleteInput/)
assert.match(payroll, /payrollService\.updateProfileAssignment/)
assert.match(payroll, /payrollService\.updateEmployeeRuleAssignment/)
assert.match(aiImport, /canApply/)
assert.match(aiImport, /Apply reviewed rows/)
assert.match(aiImport, /accept="\.csv,\.md,\.txt/)
assert.match(aiImport, /aiImportService\.create\('auto'/)
assert.match(aiImport, /What can be imported/)
assert.match(aiImport, /Proposed destination/)
assert.match(aiImport, /Routing summary/)
assert.doesNotMatch(aiImport, /input-label="t\('employees', 'Import destination'\)"/)
assert.doesNotMatch(payroll, /\$router\.push\(\{ name: 'AiImport'/)
assert.match(aiService, /\/batches\/\$\{id\}\/apply/)
assert.match(aiService, /\/batches\/\$\{id\}\/review/)
assert.match(aiImportBackend, /AUTO_TARGET\s*=\s*'auto'/)
assert.match(aiImportBackend, /validateMixed/)
assert.match(aiImportBackend, /\['_target'\]/)
assert.match(aiImportBackend, /Allowed target schemas/)
assert.match(payrollService, /export\.csv/)
assert.match(routes, /ai_import#apply/)
assert.match(routes, /ai_import#review/)
assert.match(routes, /payroll#recordPayment/)
assert.match(routes, /payroll#updatePlan/)
assert.match(routes, /payroll#updateProfile/)
assert.match(routes, /payroll#updateRule/)
assert.match(routes, /payroll#updateInput/)
assert.match(routes, /payroll#deleteInput/)
assert.match(routes, /payroll#updateProfileAssignment/)
assert.match(routes, /payroll#updateEmployeeRuleAssignment/)
for (const target of ['payroll_profiles', 'payroll_rules', 'payroll_employee_rules']) {
	assert.ok(aiImport.includes(target), `AI import UI must expose ${target}`)
}
for (const table of ['payroll_plans', 'payroll_periods', 'payroll_payslips', 'payroll_payments', 'employee_ai_imports']) {
	assert.ok(migration.includes(`'${table}'`), `Migration must create ${table}`)
}
for (const typedColumn of [
	"'payroll_plans.base_salary' => 'numeric'",
	"'payroll_payslips.overtime_hours' => 'numeric'",
	"'payroll_payments.payment_date' => 'date'",
	"'employee_ai_imports.expires_at' => 'timestamp without time zone'",
]) {
	assert.ok(canonical.includes(typedColumn), `Canonical database contract must include ${typedColumn}`)
}
assert.match(repository, /employee_id'.*PARAM_INT/)
assert.match(repository, /effective_until'.*PARAM_NULL.*PARAM_STR/)
assert.match(repository, /source_id'.*PARAM_NULL.*PARAM_INT/)
assert.match(repository, /deleteUnapprovedPayslips/)
for (const source of [payroll, aiImport]) {
	assert.doesNotMatch(source, /#[0-9a-f]{3,8}\b/i, 'New UI must use Nextcloud theme variables, not hard-coded colors')
}

console.log('PAYROLL_AI_IMPORT_CONTRACT_OK')
