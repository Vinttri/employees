<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\Employees\Db\PayrollRepository;
use RuntimeException;

final class PayrollService {
	private const PERIOD_STATUSES = ['draft', 'calculated', 'approved', 'paid'];
	private const INPUT_TYPES = [
		'hours', 'overtime_hours', 'units', 'commission', 'bonus', 'reimbursement',
		'one_off', 'deduction', 'tax', 'adjustment_earning', 'adjustment_deduction',
	];
	private const PAYMENT_METHODS = ['bank_transfer', 'cash', 'card', 'other'];

	public function __construct(
		private PayrollRepository $repository,
		private PayrollCalculator $calculator,
		private PayrollPackageService $packageService,
	) {
	}

	/** @return array<string, mixed> */
	public function overview(?int $periodId, string $uid, bool $manageAll): array {
		$periods = $this->repository->listPeriods();
		if ($periodId === null && $periods !== []) {
			$periodId = (int)$periods[0]['id'];
		}

		$employeeId = null;
		if (!$manageAll) {
			$employee = $this->repository->findEmployeeByUserId($uid);
			$employeeId = $employee === null ? -1 : (int)$employee['id_employees'];
		}

		$payslips = $periodId === null ? [] : $this->repository->listPayslips($periodId, $employeeId);
		$payments = $periodId === null || !$manageAll ? [] : $this->repository->listPayments($periodId);
		return [
			'periods' => $periods,
			'selected_period' => $periodId === null ? null : $this->repository->findPeriod($periodId),
			'employees' => $manageAll ? $this->repository->listActiveEmployees() : [],
			'plans' => $manageAll ? $this->repository->listPlans() : [],
			'profiles' => $manageAll ? $this->repository->listProfiles() : [],
			'profile_assignments' => $manageAll ? $this->repository->listProfileAssignments() : [],
			'employee_rule_assignments' => $manageAll ? $this->repository->listEmployeeRuleAssignments() : [],
			'inputs' => $periodId === null || !$manageAll ? [] : $this->repository->listInputsForPeriod($periodId),
			'payslips' => $payslips,
			'payments' => $payments,
			'totals' => $this->totals($payslips),
			'bank_settings' => $manageAll ? $this->packageService->bankSettings() : [],
		];
	}

	public function createPeriod(array $payload, string $actorUid): array {
		$from = $this->date($payload['date_from'] ?? null, 'date_from');
		$until = $this->date($payload['date_until'] ?? null, 'date_until');
		if ($until < $from) {
			throw new InvalidArgumentException('Period end must not be before its start.');
		}
		$currency = $this->currency($payload['currency'] ?? 'EUR');
		$name = trim((string)($payload['name'] ?? ''));
		if ($name === '') {
			$name = $from->format('F Y');
		}
		$name = $this->requiredText($name, 'name', 190);

		$id = $this->repository->createPeriod([
			'name' => $name,
			'date_from' => $from->format('Y-m-d'),
			'date_until' => $until->format('Y-m-d'),
			'currency' => $currency,
			'created_by' => $actorUid,
		]);
		$this->repository->audit($actorUid, 'period_created', 'period', $id);
		return $this->repository->findPeriod($id);
	}

	public function createPlan(array $payload, string $actorUid): array {
		$employeeId = $this->positiveInt($payload['employee_id'] ?? null, 'employee_id');
		$this->repository->findEmployee($employeeId);
		$data = $this->planData($payload);
		$id = $this->repository->createPlan(['employee_id' => $employeeId] + $data, $actorUid);
		$this->repository->audit($actorUid, 'plan_created', 'plan', $id, ['employee_id' => $employeeId]);
		return $this->repository->findPlan($id);
	}

	public function updatePlan(int $planId, array $payload, string $actorUid): array {
		$this->repository->findPlan($planId);
		if ($this->repository->isPlanLocked($planId)) {
			throw new RuntimeException('This plan was used by approved payroll. Create a new effective-dated plan instead.');
		}
		$data = $this->planData($payload);
		$this->repository->updatePlan($planId, $data);
		$this->repository->audit($actorUid, 'plan_updated', 'plan', $planId);
		return $this->repository->findPlan($planId);
	}

	/** @return array<string, mixed> */
	public function saveEmployeeSetup(int $employeeId, array $payload, string $actorUid): array {
		$employee = $this->repository->findEmployee($employeeId);
		$data = $this->planData($payload);
		$planId = isset($payload['plan_id']) && (int)$payload['plan_id'] > 0 ? (int)$payload['plan_id'] : null;
		if ($planId !== null) {
			$plan = $this->repository->findPlan($planId);
			if ((int)$plan['employee_id'] !== $employeeId) {
				throw new InvalidArgumentException('Salary plan does not belong to this employee.');
			}
			if ($this->repository->isPlanLocked($planId) && (string)$data['effective_from'] <= (string)$plan['effective_from']) {
				throw new InvalidArgumentException('Choose an effective date after the locked salary plan start date.');
			}
		}

		$iban = $this->packageService->employeeIban($payload['number_account'] ?? $employee['number_account'] ?? null);
		$resultPlanId = $this->repository->transactional(function () use ($employeeId, $planId, $data, $actorUid, $iban, $payload): int {
			if ($planId !== null && !$this->repository->isPlanLocked($planId)) {
				$this->repository->updatePlan($planId, $data);
				$resultPlanId = $planId;
			} else {
				$resultPlanId = $this->repository->createPlan(['employee_id' => $employeeId] + $data, $actorUid);
			}
			$this->repository->updateEmployeePayrollFields($employeeId, (string)$data['base_salary'], $iban);
			$profileId = isset($payload['profile_id']) ? (int)$payload['profile_id'] : 0;
			if ($profileId > 0) {
				$this->repository->findProfile($profileId);
				$this->repository->upsertPrimaryPlanProfile($resultPlanId, $profileId, (string)$data['effective_from'], $data['effective_until']);
			}
			return $resultPlanId;
		});
		$this->repository->audit($actorUid, 'employee_payroll_setup_saved', 'plan', $resultPlanId, ['employee_id' => $employeeId]);
		return ['plan' => $this->repository->findPlan($resultPlanId), 'employee' => $this->repository->findEmployee($employeeId)];
	}

	/** @return array<string, mixed> */
	public function setEmployeePayrollEnabled(int $employeeId, bool $enabled, string $actorUid): array {
		$this->repository->findEmployee($employeeId);
		$this->repository->setEmployeePayrollEnabled($employeeId, $enabled);
		$this->repository->audit($actorUid, $enabled ? 'employee_payroll_included' : 'employee_payroll_excluded', 'employee', $employeeId);
		return $this->repository->findEmployee($employeeId);
	}

	/** @return array<string, mixed> */
	private function planData(array $payload): array {
		$mode = trim((string)($payload['payment_mode'] ?? 'monthly'));
		if (!in_array($mode, PayrollCalculator::PAYMENT_MODES, true)) {
			throw new InvalidArgumentException('Unsupported payroll payment mode.');
		}
		$from = $this->date($payload['effective_from'] ?? null, 'effective_from');
		$until = $this->nullableDate($payload['effective_until'] ?? null, 'effective_until');
		if ($until !== null && $until < $from) {
			throw new InvalidArgumentException('Plan end must not be before its start.');
		}

		return [
			'name' => $this->requiredText($payload['name'] ?? 'Compensation plan', 'name', 190),
			'payment_mode' => $mode,
			'currency' => $this->currency($payload['currency'] ?? 'EUR'),
			'base_salary' => $this->number($payload['base_salary'] ?? 0, 'base_salary', 2),
			'hourly_rate' => $this->number($payload['hourly_rate'] ?? 0, 'hourly_rate', 4),
			'cost_rate' => $this->number($payload['cost_rate'] ?? 0, 'cost_rate', 4),
			'standard_month_hours' => $this->number($payload['standard_month_hours'] ?? 0, 'standard_month_hours', 4, 12),
			'overtime_rate' => $this->number($payload['overtime_rate'] ?? 0, 'overtime_rate', 4),
			'effective_from' => $from->format('Y-m-d'),
			'effective_until' => $until?->format('Y-m-d'),
			'active' => $this->bool($payload['active'] ?? true),
		];
	}

	public function createProfile(array $payload, string $actorUid): array {
		$id = $this->repository->createProfile(
			$this->requiredText($payload['name'] ?? null, 'name', 190),
			$this->nullableText($payload['description'] ?? null, 4000),
		);
		$this->repository->audit($actorUid, 'profile_created', 'profile', $id);
		return $this->repository->findProfile($id);
	}

	public function updateProfile(int $profileId, array $payload, string $actorUid): array {
		$this->repository->findProfile($profileId);
		$this->repository->updateProfile(
			$profileId,
			$this->requiredText($payload['name'] ?? null, 'name', 190),
			$this->nullableText($payload['description'] ?? null, 4000),
			$this->bool($payload['active'] ?? true),
		);
		$this->repository->audit($actorUid, 'profile_updated', 'profile', $profileId);
		return $this->repository->findProfile($profileId);
	}

	public function createRule(int $profileId, array $payload, string $actorUid): array {
		$this->repository->findProfile($profileId);
		$id = $this->repository->createRule($profileId, $this->ruleData($payload));
		$this->repository->audit($actorUid, 'rule_created', 'rule', $id, ['profile_id' => $profileId]);
		return $this->repository->findRule($id);
	}

	public function updateRule(int $ruleId, array $payload, string $actorUid): array {
		$this->repository->findRule($ruleId);
		$data = $this->ruleData($payload);
		$this->repository->updateRule($ruleId, $data);
		$this->repository->audit($actorUid, 'rule_updated', 'rule', $ruleId);
		return $this->repository->findRule($ruleId);
	}

	/** @return array<string, mixed> */
	private function ruleData(array $payload): array {
		$category = trim((string)($payload['category'] ?? ''));
		$type = trim((string)($payload['calculation_type'] ?? ''));
		if (!in_array($category, PayrollCalculator::RULE_CATEGORIES, true)) {
			throw new InvalidArgumentException('Rule category must be earning or deduction.');
		}
		if (!in_array($type, PayrollCalculator::CALCULATION_TYPES, true)) {
			throw new InvalidArgumentException('Unsupported rule calculation type.');
		}
		return [
			'code' => $this->code($payload['code'] ?? null),
			'name' => $this->requiredText($payload['name'] ?? null, 'name', 190),
			'category' => $category,
			'calculation_type' => $type,
			'value' => $this->number($payload['value'] ?? 0, 'value', 4),
			'taxable' => $this->bool($payload['taxable'] ?? false),
			'sort_order' => max(0, min((int)($payload['sort_order'] ?? 100), 10000)),
			'active' => $this->bool($payload['active'] ?? true),
		];
	}

	public function assignProfile(int $planId, array $payload, string $actorUid): array {
		$this->repository->findPlan($planId);
		$profileId = $this->positiveInt($payload['profile_id'] ?? null, 'profile_id');
		$this->repository->findProfile($profileId);
		$from = $this->date($payload['effective_from'] ?? null, 'effective_from');
		$until = $this->nullableDate($payload['effective_until'] ?? null, 'effective_until');
		if ($until !== null && $until < $from) {
			throw new InvalidArgumentException('Profile assignment end must not be before its start.');
		}
		$this->repository->assignProfile($planId, $profileId, $from->format('Y-m-d'), $until?->format('Y-m-d'), max(0, min((int)($payload['sort_order'] ?? 100), 10000)));
		$this->repository->audit($actorUid, 'profile_assigned', 'plan', $planId, ['profile_id' => $profileId]);
		return $this->repository->findPlan($planId);
	}

	public function updateProfileAssignment(int $assignmentId, array $payload, string $actorUid): array {
		$this->repository->findProfileAssignment($assignmentId);
		$planId = $this->positiveInt($payload['plan_id'] ?? null, 'plan_id');
		$profileId = $this->positiveInt($payload['profile_id'] ?? null, 'profile_id');
		$this->repository->findPlan($planId);
		$this->repository->findProfile($profileId);
		$from = $this->date($payload['effective_from'] ?? null, 'effective_from');
		$until = $this->nullableDate($payload['effective_until'] ?? null, 'effective_until');
		if ($until !== null && $until < $from) {
			throw new InvalidArgumentException('Profile assignment end must not be before its start.');
		}
		$this->repository->updateProfileAssignment($assignmentId, $planId, $profileId, $from->format('Y-m-d'), $until?->format('Y-m-d'), max(0, min((int)($payload['sort_order'] ?? 100), 10000)));
		$this->repository->audit($actorUid, 'profile_assignment_updated', 'profile_assignment', $assignmentId, ['plan_id' => $planId, 'profile_id' => $profileId]);
		return $this->repository->findProfileAssignment($assignmentId);
	}

	public function assignEmployeeRule(int $employeeId, array $payload, string $actorUid): array {
		$this->repository->findEmployee($employeeId);
		$ruleId = $this->positiveInt($payload['rule_id'] ?? null, 'rule_id');
		$this->repository->findRule($ruleId);
		$from = $this->date($payload['effective_from'] ?? null, 'effective_from');
		$until = $this->nullableDate($payload['effective_until'] ?? null, 'effective_until');
		if ($until !== null && $until < $from) {
			throw new InvalidArgumentException('Individual rule end must not be before its start.');
		}
		$id = $this->repository->assignEmployeeRule(
			$employeeId,
			$ruleId,
			isset($payload['override_value']) && $payload['override_value'] !== '' ? $this->number($payload['override_value'], 'override_value', 4) : null,
			$this->bool($payload['enabled'] ?? true),
			$from->format('Y-m-d'),
			$until?->format('Y-m-d'),
			$this->nullableText($payload['notes'] ?? null, 4000),
		);
		$this->repository->audit($actorUid, 'employee_rule_assigned', 'employee_rule', $id, ['employee_id' => $employeeId, 'rule_id' => $ruleId]);
		return ['id' => $id];
	}

	public function updateEmployeeRuleAssignment(int $assignmentId, array $payload, string $actorUid): array {
		$this->repository->findEmployeeRuleAssignment($assignmentId);
		$employeeId = $this->positiveInt($payload['employee_id'] ?? null, 'employee_id');
		$ruleId = $this->positiveInt($payload['rule_id'] ?? null, 'rule_id');
		$this->repository->findEmployee($employeeId);
		$this->repository->findRule($ruleId);
		$from = $this->date($payload['effective_from'] ?? null, 'effective_from');
		$until = $this->nullableDate($payload['effective_until'] ?? null, 'effective_until');
		if ($until !== null && $until < $from) {
			throw new InvalidArgumentException('Individual rule end must not be before its start.');
		}
		$this->repository->updateEmployeeRuleAssignment(
			$assignmentId,
			$employeeId,
			$ruleId,
			isset($payload['override_value']) && $payload['override_value'] !== '' ? $this->number($payload['override_value'], 'override_value', 4) : null,
			$this->bool($payload['enabled'] ?? true),
			$from->format('Y-m-d'),
			$until?->format('Y-m-d'),
			$this->nullableText($payload['notes'] ?? null, 4000),
		);
		$this->repository->audit($actorUid, 'employee_rule_updated', 'employee_rule', $assignmentId, ['employee_id' => $employeeId, 'rule_id' => $ruleId]);
		return $this->repository->findEmployeeRuleAssignment($assignmentId);
	}

	public function addInput(int $periodId, array $payload, string $actorUid, string $sourceType = 'manual'): array {
		$period = $this->requireEditablePeriod($periodId);
		$data = $this->inputData($period, $payload, $sourceType);
		$id = $this->repository->addInput(['period_id' => $periodId] + $data, $actorUid);
		$this->invalidateCalculatedPeriod($period, $actorUid);
		$this->repository->audit($actorUid, 'input_created', 'input', $id, ['period_id' => $periodId, 'employee_id' => $data['employee_id']]);
		return ['id' => $id];
	}

	public function updateInput(int $inputId, array $payload, string $actorUid): array {
		$input = $this->repository->findInput($inputId);
		$period = $this->requireEditablePeriod((int)$input['period_id']);
		$data = $this->inputData($period, $payload, (string)$input['source_type']);
		$this->repository->updateInput($inputId, $data);
		$this->invalidateCalculatedPeriod($period, $actorUid);
		$this->repository->audit($actorUid, 'input_updated', 'input', $inputId, ['period_id' => (int)$input['period_id'], 'employee_id' => $data['employee_id']]);
		return $this->repository->findInput($inputId);
	}

	public function deleteInput(int $inputId, string $actorUid): array {
		$input = $this->repository->findInput($inputId);
		$period = $this->requireEditablePeriod((int)$input['period_id']);
		$this->repository->deleteInput($inputId);
		$this->invalidateCalculatedPeriod($period, $actorUid);
		$this->repository->audit($actorUid, 'input_deleted', 'input', $inputId, ['period_id' => (int)$input['period_id'], 'employee_id' => (int)$input['employee_id']]);
		return ['id' => $inputId];
	}

	private function invalidateCalculatedPeriod(array $period, string $actorUid): void {
		if ((string)$period['status'] !== 'calculated') {
			return;
		}
		$periodId = (int)$period['id'];
		$this->repository->deleteUnapprovedPayslips($periodId);
		$this->repository->updatePeriodStatus($periodId, 'draft', $actorUid);
	}

	/** @return array<string, mixed> */
	private function inputData(array $period, array $payload, string $sourceType): array {
		$employeeId = $this->positiveInt($payload['employee_id'] ?? null, 'employee_id');
		$this->repository->findEmployee($employeeId);
		$inputType = trim((string)($payload['input_type'] ?? ''));
		if (!in_array($inputType, self::INPUT_TYPES, true)) {
			throw new InvalidArgumentException('Unsupported payroll input type.');
		}
		$currency = $this->currency($payload['currency'] ?? $period['currency']);
		if ($currency !== (string)$period['currency']) {
			throw new InvalidArgumentException('Payroll input currency must match the payroll period.');
		}
		return [
			'employee_id' => $employeeId,
			'input_type' => $inputType,
			'code' => $this->code($payload['code'] ?? strtoupper($inputType)),
			'name' => $this->requiredText($payload['name'] ?? ucfirst(str_replace('_', ' ', $inputType)), 'name', 190),
			'quantity' => $this->number($payload['quantity'] ?? 0, 'quantity', 4, 12),
			'rate' => $this->number($payload['rate'] ?? 0, 'rate', 4),
			'amount' => $this->number($payload['amount'] ?? 0, 'amount', 2),
			'currency' => $currency,
			'source_type' => $sourceType,
			'source_reference' => $this->nullableText($payload['source_reference'] ?? null, 190),
			'notes' => $this->nullableText($payload['notes'] ?? null, 4000),
		];
	}

	/** @return array<string, mixed> */
	public function calculatePeriod(int $periodId, string $actorUid): array {
		$period = $this->requireEditablePeriod($periodId);
		$this->repository->deleteUnapprovedPayslips($periodId);
		$results = [];
		$errors = [];
		foreach ($this->repository->listActiveEmployees() as $employee) {
			$employeeId = (int)$employee['id_employees'];
			if (!$this->bool($employee['payroll_enabled'] ?? false)) {
				continue;
			}
			// Synced Nextcloud service, guest and smoke accounts can have an
			// employee directory entry without belonging to payroll. A dated
			// compensation plan is the explicit inclusion boundary.
			if ($this->repository->findPlanForEmployee($employeeId, (string)$period['date_until']) === null) {
				continue;
			}
			try {
				$results[] = $this->calculateEmployee($period, $employeeId, $actorUid);
			} catch (\Throwable $e) {
				$errors[] = [
					'employee_id' => $employeeId,
					'employee_uid' => (string)$employee['id_user'],
					'message' => $e->getMessage(),
				];
			}
		}
		if ($results === []) {
			throw new RuntimeException('No payslips could be calculated.');
		}
		// A partial run must never leave the period approvable with stale
		// payslips from an earlier calculation.
		$this->repository->updatePeriodStatus($periodId, $errors === [] ? 'calculated' : 'draft', $actorUid);
		$this->repository->audit($actorUid, 'period_calculated', 'period', $periodId, ['calculated' => count($results), 'errors' => count($errors)]);
		return ['payslips' => $results, 'errors' => $errors, 'totals' => $this->totals($this->repository->listPayslips($periodId))];
	}

	/** @return array<string, mixed> */
	public function approvePeriod(int $periodId, string $actorUid): array {
		$period = $this->repository->findPeriod($periodId);
		if ((string)$period['status'] !== 'calculated') {
			throw new RuntimeException('Only a calculated period can be approved.');
		}
		$activeEmployees = $this->repository->countEmployeesWithPlan((string)$period['date_until']);
		$payslips = $this->repository->countPayslips($periodId);
		if ($activeEmployees === 0 || $payslips !== $activeEmployees) {
			throw new RuntimeException("Payroll is incomplete: {$payslips} of {$activeEmployees} active employees were calculated.");
		}
		$period = $this->repository->transactional(function () use ($periodId, $actorUid): array {
			$this->repository->approvePayslipsForPeriod($periodId);
			$this->repository->updatePeriodStatus($periodId, 'approved', $actorUid);
			$this->repository->updatePeriodPaidIfComplete($periodId);
			$this->repository->audit($actorUid, 'period_approved', 'period', $periodId);
			return $this->repository->findPeriod($periodId);
		});
		try {
			$period['package'] = $this->packageService->publish($periodId, $actorUid);
		} catch (\Throwable $e) {
			$period['package'] = ['status' => 'error', 'errors' => [$e->getMessage()]];
		}
		return $period;
	}

	/** @return array<string, mixed> */
	public function recordPayment(int $payslipId, array $payload, string $actorUid): array {
		$payslip = $this->repository->findPayslipById($payslipId);
		if (isset($payload['employee_id']) && (int)$payload['employee_id'] !== (int)$payslip['employee_id']) {
			throw new InvalidArgumentException('Payment employee does not match the payslip employee.');
		}
		if (!in_array((string)$payslip['status'], ['approved', 'partially_paid'], true)) {
			throw new RuntimeException('Only approved payslips can be paid.');
		}
		$amount = $this->number($payload['amount'] ?? null, 'amount', 2);
		$outstanding = bcsub((string)$payslip['net_amount'], (string)$payslip['paid_amount'], 2);
		if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $outstanding, 2) > 0) {
			throw new InvalidArgumentException('Payment must be positive and not exceed the outstanding amount.');
		}

		$method = trim((string)($payload['method'] ?? 'bank_transfer'));
		if (!in_array($method, self::PAYMENT_METHODS, true)) {
			throw new InvalidArgumentException('Unsupported payment method.');
		}

		$id = $this->repository->transactional(function () use ($payslip, $payslipId, $payload, $amount, $method, $actorUid): int {
			$id = $this->repository->recordPayment([
				'period_id' => (int)$payslip['period_id'],
				'payslip_id' => $payslipId,
				'employee_id' => (int)$payslip['employee_id'],
				'payment_date' => $this->date($payload['payment_date'] ?? date('Y-m-d'), 'payment_date')->format('Y-m-d'),
				'amount' => $amount,
				'currency' => (string)$payslip['currency'],
				'method' => $method,
				'reference' => $this->nullableText($payload['reference'] ?? null, 190),
				'notes' => $this->nullableText($payload['notes'] ?? null, 4000),
			], $actorUid);
			$this->repository->updatePayslipPaidAmount($payslipId);
			$this->repository->updatePeriodPaidIfComplete((int)$payslip['period_id']);
			$this->repository->audit($actorUid, 'payment_recorded', 'payment', $id, ['payslip_id' => $payslipId, 'amount' => $amount]);
			return $id;
		});
		return ['id' => $id, 'payslip' => $this->repository->findPayslipById($payslipId)];
	}

	public function exportPeriodCsv(int $periodId, string $actorUid): string {
		return $this->packageService->csv($periodId, $actorUid);
	}

	public function exportSepa(int $periodId, string $actorUid): string {
		return $this->packageService->sepa($periodId, $actorUid);
	}

	/** @return array<string, string> */
	public function bankSettings(): array {
		return $this->packageService->bankSettings();
	}

	/** @return array<string, string> */
	public function saveBankSettings(array $payload): array {
		return $this->packageService->saveBankSettings($payload);
	}

	/** @return array<string, mixed> */
	public function publishPeriod(int $periodId, string $actorUid): array {
		return $this->packageService->publish($periodId, $actorUid);
	}

	public function payslipPdf(int $payslipId, string $uid, bool $manageAll): string {
		if (!$manageAll) {
			$payslip = $this->repository->findPayslipById($payslipId);
			$employee = $this->repository->findEmployee((int)$payslip['employee_id']);
			if ((string)$employee['id_user'] !== $uid) {
				throw new RuntimeException('You can download only your own payslip.');
			}
		}
		return $this->packageService->payslipPdf($payslipId);
	}

	public function exportPaymentsCsv(int $periodId, string $actorUid): string {
		$period = $this->repository->findPeriod($periodId);
		if (!in_array((string)$period['status'], ['approved', 'paid'], true)) {
			throw new RuntimeException('Only approved payroll payments can be exported.');
		}
		$stream = fopen('php://temp', 'w+');
		if ($stream === false) {
			throw new RuntimeException('Could not create payment export.');
		}
		fputcsv($stream, ['payment_id', 'payslip_id', 'employee_uid', 'employee_name', 'account', 'payment_date', 'currency', 'amount', 'method', 'reference', 'status']);
		foreach ($this->repository->listPayments($periodId) as $row) {
			fputcsv($stream, [
				$row['id'], $row['payslip_id'], $row['id_user'], $row['display_name'] ?: $row['id_user'],
				$row['number_account'], $row['payment_date'], $row['currency'], $row['amount'],
				$row['method'], $row['reference'], $row['status'],
			]);
		}
		rewind($stream);
		$csv = stream_get_contents($stream);
		fclose($stream);
		$this->repository->audit($actorUid, 'payments_exported', 'period', $periodId, ['format' => 'csv']);
		return "\xEF\xBB\xBF" . ($csv === false ? '' : $csv);
	}

	/** @return array<string, mixed> */
	private function calculateEmployee(array $period, int $employeeId, string $actorUid): array {
		$plan = $this->repository->findPlanForEmployee($employeeId, (string)$period['date_until']);
		if ($plan === null) {
			throw new RuntimeException('No active compensation plan for this period.');
		}
		if ((string)$plan['currency'] !== (string)$period['currency']) {
			throw new RuntimeException('Compensation plan currency does not match the payroll period.');
		}
		$inputs = $this->repository->listInputs((int)$period['id'], $employeeId);
		$hasHours = array_filter($inputs, static fn(array $input): bool => (string)$input['input_type'] === 'hours') !== [];
		if (!$hasHours && in_array((string)$plan['payment_mode'], ['hourly', 'monthly_plus_hours'], true)) {
			$inputs[] = [
				'id' => null,
				'input_type' => 'hours',
				'quantity' => $this->repository->getInternalHours($employeeId, (string)$period['date_from'], (string)$period['date_until']),
				'rate' => '0',
				'amount' => '0',
				'code' => 'INTERNAL_HOURS',
				'name' => 'Internal time reports',
				'source_type' => 'time_reports',
			];
		}
		$inputs = array_merge($inputs, $this->absenceInputs($period, $plan, $employeeId));
		$rules = $this->repository->findRulesForPlan((int)$plan['id'], $employeeId, (string)$period['date_until']);
		$calculation = $this->calculator->calculate($plan, $rules, $inputs);
		$snapshot = [
			'calculated_at' => date(DATE_ATOM),
			'calculated_by' => $actorUid,
			'period' => $period,
			'plan' => $plan,
			'rules' => $rules,
			'inputs' => $inputs,
			'result' => array_diff_key($calculation, ['lines' => true]),
		];
		return $this->repository->transactional(function () use ($period, $employeeId, $plan, $calculation, $snapshot): array {
			$id = $this->repository->saveCalculatedPayslip(
				(int)$period['id'], $employeeId, (int)$plan['id'], (string)$plan['currency'], $calculation, $snapshot,
			);
			return ['id' => $id, 'employee_id' => $employeeId] + array_diff_key($calculation, ['lines' => true]);
		});
	}

	/** @return array<int, array<string, mixed>> */
	private function absenceInputs(array $period, array $plan, int $employeeId): array {
		$from = (string)$period['date_from'];
		$until = (string)$period['date_until'];
		$holidays = $this->repository->listHolidayDates($from, $until);
		$periodDays = $this->workingDays($from, $until, $holidays);
		if ($periodDays === 0) {
			return [];
		}
		$standardHours = bccomp((string)($plan['standard_month_hours'] ?? '0'), '0', 4) > 0
			? (string)$plan['standard_month_hours'] : bcmul((string)$periodDays, '8', 4);
		$dailyHours = bcdiv($standardHours, (string)$periodDays, 4);
		$baseSalary = (string)($plan['base_salary'] ?? '0');
		$mode = (string)$plan['payment_mode'];
		$inputs = [];
		foreach ($this->repository->listApprovedPayrollAbsences($employeeId, $from, $until) as $absence) {
			$days = $this->workingDays(
				max($from, substr((string)$absence['date_from'], 0, 10)),
				min($until, substr((string)$absence['date_until'], 0, 10)),
				$holidays,
			);
			if ($days === 0) {
				continue;
			}
			$percentage = max(0.0, min(100.0, (float)$absence['payroll_percentage']));
			$code = 'ABSENCE_' . (int)$absence['absence_history_id'];
			$name = 'Absence: ' . (string)$absence['name'];
			if ($mode === 'hourly') {
				$paidHours = bcmul(bcmul((string)$days, $dailyHours, 4), (string)($percentage / 100), 4);
				if (bccomp($paidHours, '0', 4) > 0) {
					$inputs[] = ['id' => null, 'input_type' => 'hours', 'quantity' => $paidHours, 'rate' => '0', 'amount' => '0', 'code' => $code . '_HOURS', 'name' => $name, 'source_type' => 'approved_absence'];
				}
				$inputs[] = ['id' => null, 'input_type' => 'adjustment_earning', 'quantity' => (string)$days, 'rate' => '0', 'amount' => '0', 'code' => $code, 'name' => $name, 'source_type' => 'approved_absence', 'taxable' => false];
				continue;
			}
			if (in_array($mode, ['monthly', 'monthly_plus_hours', 'fixed_period'], true)) {
				$unpaidRatio = (100 - $percentage) / 100;
				$deduction = bcmul(bcdiv(bcmul($baseSalary, (string)$days, 6), (string)$periodDays, 6), (string)$unpaidRatio, 2);
				$inputs[] = ['id' => null, 'input_type' => bccomp($deduction, '0', 2) > 0 ? 'adjustment_deduction' : 'adjustment_earning', 'quantity' => (string)$days, 'rate' => bccomp($deduction, '0', 2) > 0 ? (string)$percentage : '0', 'amount' => $deduction, 'code' => $code, 'name' => $name, 'source_type' => 'approved_absence', 'taxable' => false];
			}
		}
		return $inputs;
	}

	/** @param array<int, string> $holidays */
	private function workingDays(string $from, string $until, array $holidays): int {
		$start = new DateTimeImmutable($from);
		$end = new DateTimeImmutable($until);
		$count = 0;
		for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
			$key = $date->format('Y-m-d');
			if ((int)$date->format('N') <= 5 && !in_array($key, $holidays, true)) {
				$count++;
			}
		}
		return $count;
	}

	/** @param array<int, array<string, mixed>> $payslips */
	private function totals(array $payslips): array {
		$totals = ['gross' => '0.00', 'deductions' => '0.00', 'net' => '0.00', 'paid' => '0.00'];
		foreach ($payslips as $payslip) {
			$totals['gross'] = bcadd($totals['gross'], (string)$payslip['gross_amount'], 2);
			$totals['deductions'] = bcadd($totals['deductions'], (string)$payslip['deduction_amount'], 2);
			$totals['net'] = bcadd($totals['net'], (string)$payslip['net_amount'], 2);
			$totals['paid'] = bcadd($totals['paid'], (string)$payslip['paid_amount'], 2);
		}
		return $totals + ['employees' => count($payslips)];
	}

	/** @return array<string, mixed> */
	private function requireEditablePeriod(int $periodId): array {
		$period = $this->repository->findPeriod($periodId);
		if (!in_array((string)$period['status'], ['draft', 'calculated'], true)) {
			throw new RuntimeException('Approved payroll is immutable.');
		}
		return $period;
	}

	private function date(mixed $value, string $field): DateTimeImmutable {
		$value = trim((string)$value);
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		$errors = DateTimeImmutable::getLastErrors();
		if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
			throw new InvalidArgumentException("{$field} must use YYYY-MM-DD.");
		}
		return $date;
	}

	private function nullableDate(mixed $value, string $field): ?DateTimeImmutable {
		return $value === null || trim((string)$value) === '' ? null : $this->date($value, $field);
	}

	private function currency(mixed $value): string {
		$value = strtoupper(trim((string)$value));
		if (!preg_match('/^[A-Z]{3}$/', $value)) {
			throw new InvalidArgumentException('Currency must use a three-letter ISO code.');
		}
		return $value;
	}

	private function number(mixed $value, string $field, int $scale, int $precision = 18): string {
		$value = trim((string)$value);
		if (!preg_match('/^\d+(?:\.\d+)?$/', $value)) {
			throw new InvalidArgumentException("{$field} must be a non-negative number.");
		}
		[$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
		$integerDigits = strlen(ltrim($integer, '0')) ?: 1;
		if ($integerDigits > $precision - $scale || strlen($fraction) > $scale) {
			throw new InvalidArgumentException("{$field} exceeds numeric({$precision},{$scale}).");
		}
		return bcadd($value, '0', $scale);
	}

	private function positiveInt(mixed $value, string $field): int {
		$value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($value === false) {
			throw new InvalidArgumentException("{$field} must be a positive integer.");
		}
		return (int)$value;
	}

	private function requiredText(mixed $value, string $field, int $max): string {
		$value = trim((string)$value);
		if ($value === '' || mb_strlen($value) > $max) {
			throw new InvalidArgumentException("{$field} is required and must be at most {$max} characters.");
		}
		return $value;
	}

	private function nullableText(mixed $value, int $max): ?string {
		$value = trim((string)$value);
		if ($value === '') {
			return null;
		}
		if (mb_strlen($value) > $max) {
			throw new InvalidArgumentException("Text must be at most {$max} characters.");
		}
		return $value;
	}

	private function code(mixed $value): string {
		$value = strtoupper(trim((string)$value));
		if (!preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $value)) {
			throw new InvalidArgumentException('Code must start with a letter and contain only A-Z, digits, and underscores.');
		}
		return $value;
	}

	private function bool(mixed $value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}
}
