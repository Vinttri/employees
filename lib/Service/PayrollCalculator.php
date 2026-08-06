<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use InvalidArgumentException;

final class PayrollCalculator {
	public const PAYMENT_MODES = [
		'monthly', 'hourly', 'monthly_plus_hours', 'fixed_period', 'commission', 'piecework',
	];

	public const RULE_CATEGORIES = ['earning', 'deduction'];
	public const CALCULATION_TYPES = ['fixed', 'percent_base', 'percent_gross', 'per_hour', 'per_unit'];

	/**
	 * @param array<string, mixed> $plan
	 * @param array<int, array<string, mixed>> $rules
	 * @param array<int, array<string, mixed>> $inputs
	 * @return array<string, mixed>
	 */
	public function calculate(array $plan, array $rules, array $inputs): array {
		$this->validatePlan($plan);
		$hours = '0';
		$overtimeHours = '0';
		$units = '0';

		foreach ($inputs as $input) {
			$type = (string)($input['input_type'] ?? '');
			if ($type === 'hours') {
				$hours = bcadd($hours, $this->decimal($input['quantity'] ?? 0, 4), 4);
			} elseif ($type === 'overtime_hours') {
				$overtimeHours = bcadd($overtimeHours, $this->decimal($input['quantity'] ?? 0, 4), 4);
			} elseif ($type === 'units') {
				$units = bcadd($units, $this->decimal($input['quantity'] ?? 0, 4), 4);
			}
		}

		$baseAmount = $this->calculateBase($plan, $hours, $units);
		$lines = [];
		if (bccomp($baseAmount, '0', 2) > 0) {
			$lines[] = $this->line('BASE', 'Base pay', 'earning', 'plan', null, '1', $baseAmount, $baseAmount, true, 10);
		}

		$overtimeRate = $this->decimal($plan['overtime_rate'] ?? 0, 4);
		if (bccomp($overtimeHours, '0', 4) > 0 && bccomp($overtimeRate, '0', 4) > 0) {
			$amount = $this->money(bcmul($overtimeHours, $overtimeRate, 8));
			$lines[] = $this->line('OVERTIME', 'Overtime', 'earning', 'plan', null, $overtimeHours, $overtimeRate, $amount, true, 20);
		}

		foreach ($inputs as $input) {
			$category = $this->inputCategory((string)($input['input_type'] ?? ''));
			if ($category === null) {
				continue;
			}
			$quantity = $this->decimal($input['quantity'] ?? 0, 4);
			$rate = $this->decimal($input['rate'] ?? 0, 4);
			$amount = $this->decimal($input['amount'] ?? 0, 2);
			if (bccomp($amount, '0', 2) === 0 && bccomp($quantity, '0', 4) !== 0 && bccomp($rate, '0', 4) !== 0) {
				$amount = $this->money(bcmul($quantity, $rate, 8));
			}
			if (bccomp($amount, '0', 2) < 0) {
				throw new InvalidArgumentException('Payroll input amounts must not be negative.');
			}
			$lines[] = $this->line(
				(string)($input['code'] ?? strtoupper((string)$input['input_type'])),
				(string)($input['name'] ?? 'Payroll input'),
				$category,
				'input',
				isset($input['id']) ? (int)$input['id'] : null,
				$quantity,
				$rate,
				$amount,
				(bool)($input['taxable'] ?? ($category === 'earning')),
				50,
			);
		}

		$grossBeforeRules = $this->sumCategory($lines, 'earning');
		usort($rules, static fn(array $a, array $b): int => ((int)($a['sort_order'] ?? 100)) <=> ((int)($b['sort_order'] ?? 100)));
		foreach ($rules as $rule) {
			if (!$this->toBool($rule['active'] ?? true) || !$this->toBool($rule['enabled'] ?? true)) {
				continue;
			}
			$category = (string)($rule['category'] ?? '');
			$type = (string)($rule['calculation_type'] ?? '');
			if (!in_array($category, self::RULE_CATEGORIES, true) || !in_array($type, self::CALCULATION_TYPES, true)) {
				throw new InvalidArgumentException('Unsupported payroll rule.');
			}
			$value = $this->decimal($rule['effective_value'] ?? $rule['value'] ?? 0, 4);
			$amount = $this->calculateRuleAmount($type, $value, $baseAmount, $grossBeforeRules, $hours, $units);
			if (bccomp($amount, '0', 2) === 0) {
				continue;
			}
			$lines[] = $this->line(
				(string)($rule['code'] ?? 'RULE'),
				(string)($rule['name'] ?? 'Payroll rule'),
				$category,
				'rule',
				isset($rule['id']) ? (int)$rule['id'] : null,
				$type === 'per_hour' ? $hours : ($type === 'per_unit' ? $units : '1'),
				$value,
				$amount,
				$this->toBool($rule['taxable'] ?? false),
				(int)($rule['sort_order'] ?? 100),
			);
			if ($category === 'earning') {
				$grossBeforeRules = bcadd($grossBeforeRules, $amount, 2);
			}
		}

		$gross = $this->sumCategory($lines, 'earning');
		$deductions = $this->sumCategory($lines, 'deduction');
		$net = $this->money(bcsub($gross, $deductions, 8));
		if (bccomp($net, '0', 2) < 0) {
			throw new InvalidArgumentException('Deductions cannot exceed gross earnings.');
		}

		return [
			'hours' => $hours,
			'overtime_hours' => $overtimeHours,
			'units' => $units,
			'base_amount' => $baseAmount,
			'gross_amount' => $gross,
			'deduction_amount' => $deductions,
			'net_amount' => $net,
			'lines' => $lines,
		];
	}

	private function validatePlan(array $plan): void {
		$mode = (string)($plan['payment_mode'] ?? '');
		if (!in_array($mode, self::PAYMENT_MODES, true)) {
			throw new InvalidArgumentException('Unsupported payroll payment mode.');
		}
		$currency = strtoupper(trim((string)($plan['currency'] ?? '')));
		if (!preg_match('/^[A-Z]{3}$/', $currency)) {
			throw new InvalidArgumentException('Payroll currency must use an ISO 4217 code.');
		}
	}

	private function calculateBase(array $plan, string $hours, string $units): string {
		$mode = (string)$plan['payment_mode'];
		$baseSalary = $this->decimal($plan['base_salary'] ?? 0, 2);
		$hourlyRate = $this->decimal($plan['hourly_rate'] ?? 0, 4);

		return match ($mode) {
			'monthly', 'fixed_period' => $this->money($baseSalary),
			'hourly' => $this->money(bcmul($hours, $hourlyRate, 8)),
			'monthly_plus_hours' => $this->money(bcadd($baseSalary, bcmul($hours, $hourlyRate, 8), 8)),
			'piecework' => $this->money(bcmul($units, $hourlyRate, 8)),
			'commission' => '0.00',
			default => throw new InvalidArgumentException('Unsupported payroll payment mode.'),
		};
	}

	private function calculateRuleAmount(string $type, string $value, string $base, string $gross, string $hours, string $units): string {
		$raw = match ($type) {
			'fixed' => $value,
			'percent_base' => bcdiv(bcmul($base, $value, 8), '100', 8),
			'percent_gross' => bcdiv(bcmul($gross, $value, 8), '100', 8),
			'per_hour' => bcmul($hours, $value, 8),
			'per_unit' => bcmul($units, $value, 8),
			default => throw new InvalidArgumentException('Unsupported payroll calculation type.'),
		};
		return $this->money($raw);
	}

	private function inputCategory(string $type): ?string {
		return match ($type) {
			'commission', 'bonus', 'reimbursement', 'one_off', 'adjustment_earning' => 'earning',
			'deduction', 'tax', 'adjustment_deduction' => 'deduction',
			default => null,
		};
	}

	/** @return array<string, mixed> */
	private function line(string $code, string $name, string $category, string $sourceType, ?int $sourceId, string $quantity, string $rate, string $amount, bool $taxable, int $sortOrder): array {
		return [
			'code' => strtoupper(substr(trim($code), 0, 64)),
			'name' => trim($name),
			'category' => $category,
			'source_type' => $sourceType,
			'source_id' => $sourceId,
			'quantity' => $quantity,
			'rate' => $rate,
			'amount' => $this->money($amount),
			'taxable' => $taxable,
			'sort_order' => $sortOrder,
		];
	}

	/** @param array<int, array<string, mixed>> $lines */
	private function sumCategory(array $lines, string $category): string {
		$total = '0.00';
		foreach ($lines as $line) {
			if (($line['category'] ?? null) === $category) {
				$total = bcadd($total, (string)$line['amount'], 2);
			}
		}
		return $this->money($total);
	}

	private function decimal(mixed $value, int $scale): string {
		$value = trim((string)$value);
		if ($value === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
			throw new InvalidArgumentException('Payroll value must be numeric.');
		}
		return bcadd($value, '0', $scale);
	}

	private function money(string $value): string {
		$adjustment = str_starts_with($value, '-') ? '-0.005' : '0.005';
		return bcadd(bcadd($value, $adjustment, 3), '0', 2);
	}

	private function toBool(mixed $value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}
}
