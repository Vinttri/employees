<?php

declare(strict_types=1);

namespace OCA\Employees\Dashboard;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Service\PermissionsService;
use OCP\Dashboard\IConditionalWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class TeamSupportWidget implements IWidget, IConditionalWidget, IIconWidget {

	public const ID = 'employees-team-support';

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private IUserSession $userSession,
		private PermissionsService $permisosService,
	) {
	}

	public function getId(): string {
		return self::ID;
	}

	public function getTitle(): string {
		// Nextcloud renders this title in a single-line dashboard header.
		return $this->l10n->t('Register support');
	}

	public function getOrder(): int {
		return 100;
	}

	public function getIconClass(): string {
		return 'icon-settings-dark';
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
		);
	}

	public function getUrl(): ?string {
		return null;
	}

	public function load(): void {
		Util::addScript(Application::APP_ID, 'employees-dashboard-support');
	}

	public function isEnabled(): bool {
		$user = $this->userSession->getUser();

		if ($user === null || !$this->permisosService->isModuleEnabled('inventario')) {
			return false;
		}

		return $this->permisosService->canSee('inventario.admin', $user->getUID());
	}
}
