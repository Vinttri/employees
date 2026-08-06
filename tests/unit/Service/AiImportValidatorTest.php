<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use OCA\Employees\Service\AiImportValidator;
use PHPUnit\Framework\TestCase;

final class AiImportValidatorTest extends TestCase {
	public function testExactEmployeeMatchAndTypedFieldsBecomeReady(): void {
		$result = (new AiImportValidator())->validate([
			'fields' => [
				'employee' => ['type' => 'employee', 'required' => true],
				'amount' => ['type' => 'decimal', 'required' => true],
				'date' => ['type' => 'date', 'required' => true],
			],
		], [[
			'employee' => 'alice', 'amount' => '123.45', 'date' => '2026-08-06',
		]], [[
			'id_employees' => 7, 'id_user' => 'alice', 'number_employee' => 'E-7',
			'email_contact' => 'alice@example.test', 'display_name' => 'Alice Example',
		]]);

		$this->assertSame(['ready' => 1, 'review' => 0, 'invalid' => 0], $result['counts']);
		$this->assertSame(7, $result['rows'][0]['employee_id']);
		$this->assertSame('alice', $result['rows'][0]['employee_uid']);
	}

	public function testReviewMetadataCanBeResubmittedWithoutBecomingUnknownFields(): void {
		$definition = ['fields' => ['employee' => ['type' => 'employee', 'required' => true]]];
		$employees = [['id_employees' => 7, 'id_user' => 'alice', 'display_name' => 'Alice Example']];
		$result = (new AiImportValidator())->validate($definition, [[
			'employee' => 'alice', 'employee_id' => 7, 'employee_uid' => 'alice',
			'_row' => 1, '_status' => 'review', '_messages' => ['previous message'],
		]], $employees);

		$this->assertSame(['ready' => 1, 'review' => 0, 'invalid' => 0], $result['counts']);
	}

	public function testDecimalMustFitTheDatabasePrecisionAndScale(): void {
		$result = (new AiImportValidator())->validate([
			'fields' => ['salary' => ['type' => 'decimal', 'required' => true, 'precision' => 12, 'scale' => 2]],
		], [
			['salary' => '9999999999.99'],
			['salary' => '10000000000.00'],
			['salary' => '1.234'],
		], []);

		$this->assertSame(['ready' => 1, 'review' => 0, 'invalid' => 2], $result['counts']);
	}

	public function testMixedRowsAreValidatedAgainstTheirOwnDestination(): void {
		$result = (new AiImportValidator())->validateMixed([
			'positions' => ['fields' => [
				'name' => ['type' => 'string', 'required' => true],
				'level' => ['type' => 'integer', 'required' => false],
			]],
			'departments' => ['fields' => [
				'name' => ['type' => 'string', 'required' => true],
				'parent' => ['type' => 'string', 'required' => false],
			]],
		], [
			['_target' => 'positions', '_source_row' => 4, 'name' => 'Senior Producer', 'level' => '5'],
			['_target' => 'departments', '_source_row' => 7, 'name' => 'Production', 'parent' => 'Operations'],
		], []);

		$this->assertSame(['ready' => 2, 'review' => 0, 'invalid' => 0], $result['counts']);
		$this->assertSame('positions', $result['rows'][0]['_target']);
		$this->assertSame(4, $result['rows'][0]['_source_row']);
		$this->assertSame(5, $result['rows'][0]['level']);
		$this->assertSame('departments', $result['rows'][1]['_target']);
	}

	public function testUnknownOrMissingMixedDestinationCannotBeApplied(): void {
		$result = (new AiImportValidator())->validateMixed([
			'positions' => ['fields' => ['name' => ['type' => 'string', 'required' => true]]],
		], [
			['_target' => 'payroll_payments', '_source_row' => 2, 'name' => 'Not allowed'],
			['_source_row' => 3, 'name' => 'Ambiguous'],
		], []);

		$this->assertSame(['ready' => 0, 'review' => 0, 'invalid' => 2], $result['counts']);
		$this->assertSame('payroll_payments', $result['rows'][0]['_target']);
		$this->assertStringContainsString('not available', $result['rows'][0]['_messages'][0]);
		$this->assertNull($result['rows'][1]['_target']);
		$this->assertStringContainsString('destination', $result['rows'][1]['_messages'][0]);
	}

	public function testMixedReviewMetadataIsBoundedAndPreserved(): void {
		$result = (new AiImportValidator())->validateMixed([
			'positions' => ['fields' => ['name' => ['type' => 'string', 'required' => true]]],
		], [[
			'_target' => 'positions', '_source_row' => '9',
			'_source_excerpt' => str_repeat('x', 600), 'name' => 'Designer',
		]], []);

		$this->assertSame(9, $result['rows'][0]['_source_row']);
		$this->assertSame(240, mb_strlen($result['rows'][0]['_source_excerpt']));
	}
}
