<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Employees\Service\PayrollPackageService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class PayrollPackageServiceTest extends TestCase {
	private PayrollPackageService $service;

	protected function setUp(): void {
		$this->service = (new ReflectionClass(PayrollPackageService::class))->newInstanceWithoutConstructor();
	}

	public function testCyprusIbanIsNormalizedAndValidated(): void {
		$this->assertSame(
			'CY17002001280000001200527600',
			$this->invoke('iban', 'cy17 0020 0128 0000 0012 0052 7600', 'IBAN'),
		);
	}

	public function testInvalidIbanIsRejected(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->invoke('iban', 'CY000000', 'IBAN');
	}

	public function testSpreadsheetFormulaIsRenderedAsText(): void {
		$this->assertSame("'=2+2", $this->invoke('safeSpreadsheetValue', '=2+2'));
		$this->assertSame('2500.00', $this->invoke('safeSpreadsheetValue', '2500.00'));
	}

	public function testSepaIdentifiersDoNotContainDoubleOrEdgeSlashes(): void {
		$this->assertSame('PAYROLL/2026-08', $this->invoke('sepaId', '/payroll//2026-08/'));
	}

	private function invoke(string $method, mixed ...$arguments): mixed {
		$reflection = new ReflectionMethod(PayrollPackageService::class, $method);
		$reflection->setAccessible(true);
		return $reflection->invoke($this->service, ...$arguments);
	}
}
