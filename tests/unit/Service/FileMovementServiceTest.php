<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\FileMovement;
use OCA\Employees\Db\FileMovementMapper;
use OCA\Employees\Service\FileMovementService;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\Files\Storage\IStorage;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class FileMovementServiceTest extends TestCase {
	public function testRecordMovementUsesEnglishEntityFields(): void {
		$mapper = $this->createMock(FileMovementMapper::class);
		$employeeMapper = $this->createMock(EmployeeMapper::class);
		$session = $this->createMock(IUserSession::class);
		$request = $this->createMock(IRequest::class);
		$user = $this->createMock(IUser::class);
		$node = $this->createMock(Node::class);
		$storage = $this->createMock(IStorage::class);

		$user->method('getUID')->willReturn('audit-user');
		$session->method('getUser')->willReturn($user);
		$employeeMapper->method('GetMyEmployeeInfo')->with('audit-user')->willReturn([['id_employees' => 7]]);
		$storage->method('getId')->willReturn('home::audit-user');
		$node->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$node->method('getSize')->with(false)->willReturn(42);
		$node->method('getStorage')->willReturn($storage);
		$node->method('getId')->willReturn(99);
		$node->method('getPath')->willReturn('/audit-user/files/smoke.txt');
		$node->method('getName')->willReturn('smoke.txt');
		$node->method('getMimetype')->willReturn('text/plain');

		$mapper->expects($this->once())->method('insert')->willReturnCallback(function (FileMovement $movement): FileMovement {
			$this->assertSame(7, $movement->getIdEmployee());
			$this->assertSame('audit-user', $movement->getActorUid());
			$this->assertSame(FileMovementService::EVENT_CREATED, $movement->getEventType());
			$this->assertSame('/audit-user/files/smoke.txt', $movement->getActualPath());
			$this->assertSame('smoke.txt', $movement->getFileName());
			$this->assertSame(42, $movement->getSize());
			$this->assertFalse($movement->getIsFolder());
			return $movement;
		});

		$service = new FileMovementService($mapper, $employeeMapper, $session, $request);
		$service->recordMovement(FileMovementService::EVENT_CREATED, $node);
	}
}
