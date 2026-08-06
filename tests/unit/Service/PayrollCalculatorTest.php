<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Employees\Service\PayrollCalculator;
use PHPUnit\Framework\TestCase;

final class PayrollCalculatorTest extends TestCase {
	private PayrollCalculator $calculator;

	protected function setUp(): void {
		$this->calculator = new PayrollCalculator();
	}

	public function testMonthlySalaryManualCyprusDiscountAndOneOffPayment(): void {
		$result = $this->calculator->calculate(
			$this->plan('monthly', '4000.00'),
			[
				['id' => 1, 'code' => 'TAX', 'name' => 'Income tax', 'category' => 'deduction', 'calculation_type' => 'percent_gross', 'value' => '12.5', 'effective_value' => '7.5', 'enabled' => true, 'active' => true],
				['id' => 2, 'code' => 'SOCIAL', 'name' => 'Social contribution', 'category' => 'deduction', 'calculation_type' => 'fixed', 'value' => '120', 'enabled' => true, 'active' => true],
			],
			[['id' => 10, 'input_type' => 'one_off', 'code' => 'WELCOME', 'name' => 'One-off payment', 'amount' => '500', 'quantity' => '0', 'rate' => '0']],
		);

		$this->assertSame('4500.00', $result['gross_amount']);
		$this->assertSame('457.50', $result['deduction_amount']);
		$this->assertSame('4042.50', $result['net_amount']);
	}

	public function testMixedPlanUsesHoursAndOvertime(): void {
		$result = $this->calculator->calculate(
			$this->plan('monthly_plus_hours', '1000', '25', '37.5'),
			[],
			[
				['input_type' => 'hours', 'quantity' => '10', 'amount' => '0', 'rate' => '0'],
				['input_type' => 'overtime_hours', 'quantity' => '2', 'amount' => '0', 'rate' => '0'],
			],
		);
		$this->assertSame('1325.00', $result['gross_amount']);
		$this->assertSame('1325.00', $result['net_amount']);
	}

	public function testDeductionsCannotExceedGross(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->calculator->calculate(
			$this->plan('monthly', '100'),
			[['category' => 'deduction', 'calculation_type' => 'fixed', 'value' => '101', 'active' => true, 'enabled' => true]],
			[],
		);
	}

	public function testHourlyFixedCommissionAndPieceworkPlans(): void {
		$cases = [
			[$this->plan('hourly', '0', '20'), [['input_type' => 'hours', 'quantity' => '8']], '160.00'],
			[$this->plan('fixed_period', '750'), [], '750.00'],
			[$this->plan('commission', '0'), [['input_type' => 'commission', 'amount' => '315.25']], '315.25'],
			[$this->plan('piecework', '0', '12.5'), [['input_type' => 'units', 'quantity' => '4']], '50.00'],
		];

		foreach ($cases as [$plan, $inputs, $expected]) {
			$result = $this->calculator->calculate($plan, [], $inputs);
			$this->assertSame($expected, $result['gross_amount']);
			$this->assertSame($expected, $result['net_amount']);
		}
	}

	private function plan(string $mode, string $base, string $hourly = '0', string $overtime = '0'): array {
		return ['payment_mode' => $mode, 'currency' => 'EUR', 'base_salary' => $base, 'hourly_rate' => $hourly, 'overtime_rate' => $overtime];
	}
}
