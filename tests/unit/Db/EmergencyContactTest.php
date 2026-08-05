<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Db;

use OCA\Employees\Controller\EmployeesController;
use OCA\Employees\Db\EmergencyContact;
use OCP\AppFramework\Db\Entity;
use PHPUnit\Framework\TestCase;

class EmergencyContactTest extends TestCase {
	public function testEntityUsesInheritedIdAndMapsDatabaseColumns(): void {
		$entity = EmergencyContact::fromRow([
			'id' => '7',
			'id_employee' => '12',
			'name' => 'Ana Pérez',
			'relationship' => 'Hermana',
			'contact_number' => '+52 871 123-4567',
			'alternate_method' => null,
			'assistance_type' => 'Transporte',
			'notes' => null,
			'is_primary' => '1',
			'primary_employee' => '12',
			'order' => '0',
			'created_at' => '2026-08-01 09:00:00',
			'updated_at' => '2026-08-01 09:00:00',
		]);

		$this->assertSame(7, $entity->getId());
		$this->assertSame(12, $entity->getIdEmployee());
		$this->assertSame('+52 871 123-4567', $entity->getContactNumber());
		$this->assertTrue($entity->getIsPrimary());
		$this->assertNull($entity->getAlternateMethod());
		$idProperty = (new \ReflectionClass(EmergencyContact::class))->getProperty('id');
		$this->assertSame(Entity::class, $idProperty->getDeclaringClass()->getName());
	}

	public function testContactValidationTrimsFields(): void {
		$result = $this->validate(['  Ana Pérez  ', '  Hermana ', ' +52 871 123-4567 ', ' correo@example.com ', ' Transporte ', ' Nota ']);

		$this->assertSame('Ana Pérez', $result['name']);
		$this->assertSame('Hermana', $result['relationship']);
		$this->assertSame('+52 871 123-4567', $result['numero']);
		$this->assertSame('correo@example.com', $result['alternativo']);
	}

	public function testContactValidationRejectsRequiredWhitespace(): void {
		$result = $this->validate(['  ', 'Madre', '871 123 4567', '', '', '']);

		$this->assertArrayHasKey('error', $result);
	}

	public function testContactValidationRejectsOversizedText(): void {
		$result = $this->validate([str_repeat('a', 201), 'Madre', '871 123 4567', '', '', '']);

		$this->assertArrayHasKey('error', $result);
	}

	private function validate(array $fields): array {
		$controller = (new \ReflectionClass(EmployeesController::class))->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod(EmployeesController::class, 'validarContacto');
		return $method->invoke($controller, ...$fields);
	}
}
