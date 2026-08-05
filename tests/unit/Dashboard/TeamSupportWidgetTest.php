<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Dashboard;

use OCA\Employees\Dashboard\TeamSupportWidget;
use OCA\Employees\Service\PermissionsService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;
use PHPUnit\Framework\TestCase;

class TeamSupportWidgetTest extends TestCase {
	public function testUnauthenticatedUserDoesNotSeeWidget(): void {
		$permissions = $this->createMock(PermissionsService::class);
		$permissions->expects($this->never())->method('isModuleEnabled');

		$this->assertFalse($this->widget(null, $permissions)->isEnabled());
	}

	public function testDisabledInventoryDoesNotShowWidget(): void {
		$permissions = $this->createMock(PermissionsService::class);
		$permissions->expects($this->once())->method('isModuleEnabled')->with('inventario')->willReturn(false);
		$permissions->expects($this->never())->method('canSee');

		$this->assertFalse($this->widget($this->user('tecnico'), $permissions)->isEnabled());
	}

	public function testUnauthorizedUserDoesNotSeeWidget(): void {
		$permissions = $this->permissions(true, false, 'normal');

		$this->assertFalse($this->widget($this->user('normal'), $permissions)->isEnabled());
	}

	public function testAuthorizedUserSeesWidget(): void {
		$permissions = $this->permissions(true, true, 'tecnico');

		$this->assertTrue($this->widget($this->user('tecnico'), $permissions)->isEnabled());
	}

	public function testGlobalAdminUsesPermissionsServiceDecision(): void {
		$permissions = $this->permissions(true, true, 'admin');

		$this->assertTrue($this->widget($this->user('admin'), $permissions)->isEnabled());
	}

	public function testWidgetIdIsStable(): void {
		$this->assertSame('employees-team-support', $this->widget()->getId());
	}

	public function testLoadAddsOnlySupportDashboardBundle(): void {
		$before = Util::getScripts();

		$this->widget()->load();

		$added = array_slice(Util::getScripts(), count($before));
		$this->assertContains('employees/js/employees-dashboard-support', $added);
		$this->assertNotContains('employees/js/main', $added);
	}

	private function widget(?IUser $user = null, ?PermissionsService $permissions = null): TeamSupportWidget {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new TeamSupportWidget(
			$l10n,
			$urlGenerator,
			$session,
			$permissions ?? $this->createMock(PermissionsService::class),
		);
	}

	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);

		return $user;
	}

	private function permissions(bool $moduleEnabled, bool $canSee, string $uid): PermissionsService {
		$permissions = $this->createMock(PermissionsService::class);
		$permissions->expects($this->once())->method('isModuleEnabled')->with('inventario')->willReturn($moduleEnabled);
		$permissions->expects($this->once())->method('canSee')->with('inventario.admin', $uid)->willReturn($canSee);

		return $permissions;
	}
}
