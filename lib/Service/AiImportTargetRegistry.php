<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use InvalidArgumentException;

final class AiImportTargetRegistry {
	/** @return array<string, array<string, mixed>> */
	public function all(): array {
		return [
			'employees' => $this->target('employees.admin', [
				'id_user' => $this->field('string', true), 'number_employee' => $this->field('string'),
				'email_contact' => $this->field('email'), 'hire_date' => $this->field('date'),
				'department' => $this->field('string'), 'position' => $this->field('string'), 'team' => $this->field('string'),
				'manager_uid' => $this->field('string'), 'base_salary' => $this->field('decimal', false, [], 12, 2), 'currency' => $this->field('currency'),
			]),
			'departments' => $this->target('employees.admin', [
				'name' => $this->field('string', true), 'parent' => $this->field('string'),
			]),
			'positions' => $this->target('employees.admin', [
				'name' => $this->field('string', true), 'level' => $this->field('integer'),
			]),
			'teams' => $this->target('employees.admin', [
				'name' => $this->field('string', true), 'leader' => $this->field('employee'),
			]),
			'time_entries' => $this->target('reporte_tiempos.admin', [
				'employee' => $this->field('employee', true), 'date' => $this->field('date', true),
				'hours' => $this->field('decimal', true, [], 12, 4), 'activity' => $this->field('string'),
				'client' => $this->field('string'), 'description' => $this->field('string'),
			]),
			'absences' => $this->target('absences.admin', [
				'employee' => $this->field('employee', true), 'type' => $this->field('string', true),
				'date_from' => $this->field('date', true), 'date_until' => $this->field('date', true),
				'notes' => $this->field('string'), 'status' => $this->field('enum', false, ['pending', 'approved']),
			]),
			'payroll_plans' => $this->target('payroll.import', [
				'employee' => $this->field('employee', true), 'name' => $this->field('string'),
				'payment_mode' => $this->field('enum', true, PayrollCalculator::PAYMENT_MODES),
				'currency' => $this->field('currency', true), 'base_salary' => $this->field('decimal', false, [], 18, 2),
				'hourly_rate' => $this->field('decimal', false, [], 18, 4), 'cost_rate' => $this->field('decimal', false, [], 18, 4),
				'standard_month_hours' => $this->field('decimal', false, [], 12, 4), 'overtime_rate' => $this->field('decimal', false, [], 18, 4),
				'effective_from' => $this->field('date', true), 'effective_until' => $this->field('date'),
			]),
			'payroll_profiles' => $this->target('payroll.import', [
				'name' => $this->field('string', true), 'description' => $this->field('string'),
			]),
			'payroll_rules' => $this->target('payroll.import', [
				'profile' => $this->field('string', true), 'code' => $this->field('code', true),
				'name' => $this->field('string', true),
				'category' => $this->field('enum', true, PayrollCalculator::RULE_CATEGORIES),
				'calculation_type' => $this->field('enum', true, PayrollCalculator::CALCULATION_TYPES),
				'value' => $this->field('decimal', true, [], 18, 4), 'taxable' => $this->field('boolean'),
			]),
			'payroll_employee_rules' => $this->target('payroll.import', [
				'employee' => $this->field('employee', true), 'profile' => $this->field('string', true),
				'rule_code' => $this->field('code', true), 'override_value' => $this->field('decimal', false, [], 18, 4),
				'enabled' => $this->field('boolean'), 'effective_from' => $this->field('date', true),
				'effective_until' => $this->field('date'), 'notes' => $this->field('string'),
			]),
			'payroll_inputs' => $this->target('payroll.import', [
				'period_id' => $this->field('integer', true), 'employee' => $this->field('employee', true),
				'input_type' => $this->field('enum', true, ['hours', 'overtime_hours', 'units', 'commission', 'bonus', 'reimbursement', 'one_off', 'deduction', 'tax', 'adjustment_earning', 'adjustment_deduction']),
				'code' => $this->field('code'), 'name' => $this->field('string'),
				'quantity' => $this->field('decimal', false, [], 12, 4), 'rate' => $this->field('decimal', false, [], 18, 4), 'amount' => $this->field('decimal', false, [], 18, 2),
				'currency' => $this->field('currency'), 'notes' => $this->field('string'),
			]),
			'payroll_payments' => $this->target('payroll.import', [
				'payslip_id' => $this->field('integer', true), 'employee' => $this->field('employee', true),
				'payment_date' => $this->field('date', true), 'amount' => $this->field('decimal', true, [], 18, 2),
				'method' => $this->field('enum', false, ['bank_transfer', 'cash', 'card', 'other']),
				'reference' => $this->field('string'), 'notes' => $this->field('string'),
			]),
			'clients' => $this->target('Client.admin', [
				'name' => $this->field('string', true), 'legal_name' => $this->field('string'),
				'email' => $this->field('email'), 'phone' => $this->field('string'), 'location' => $this->field('string'),
				'parent' => $this->field('string'), 'project_leader' => $this->field('employee'),
			]),
			'activities' => $this->target('Client.admin', [
				'name' => $this->field('string', true), 'details' => $this->field('string'),
				'estimated_hours' => $this->field('decimal', false, [], 12, 4), 'billable' => $this->field('boolean'),
				'type' => $this->field('enum', false, ['client', 'internal']),
			]),
			'costs' => $this->target('reporte_tiempos.admin', [
				'employee' => $this->field('employee', true), 'cost_rate' => $this->field('decimal', true, [], 18, 4),
				'effective_from' => $this->field('date', true),
			]),
			'purchases' => $this->target('purchases.admin', [
				'requester' => $this->field('employee', true), 'title' => $this->field('string', true),
				'description' => $this->field('string'), 'amount' => $this->field('decimal', false, [], 18, 2), 'currency' => $this->field('currency'),
			]),
			'inventory' => $this->target('inventario.admin', [
				'asset_name' => $this->field('string', true), 'serial_number' => $this->field('string'),
				'model' => $this->field('string'), 'employee' => $this->field('employee'), 'status' => $this->field('string'),
			]),
			'maintenance' => $this->target('inventario.admin', [
				'asset' => $this->field('string', true), 'scheduled_date' => $this->field('date', true),
				'assignee' => $this->field('employee'), 'description' => $this->field('string'),
			]),
		];
	}

	/** @return array<string, mixed> */
	public function get(string $target): array {
		$definition = $this->all()[$target] ?? null;
		if ($definition === null) {
			throw new InvalidArgumentException('Unsupported AI import target.');
		}
		return $definition;
	}

	/** @param array<string, array<string, mixed>> $fields */
	private function target(string $permission, array $fields): array {
		return ['permission' => $permission, 'fields' => $fields];
	}

	/** @param string[] $values */
	private function field(string $type, bool $required = false, array $values = [], ?int $precision = null, ?int $scale = null): array {
		$field = ['type' => $type, 'required' => $required];
		if ($values !== []) {
			$field['values'] = $values;
		}
		if ($precision !== null && $scale !== null) {
			$field['precision'] = $precision;
			$field['scale'] = $scale;
		}
		return $field;
	}
}
