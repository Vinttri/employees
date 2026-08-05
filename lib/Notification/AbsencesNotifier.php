<?php

declare(strict_types=1);

namespace OCA\Employees\Notification;

use OCA\Employees\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class AbsencesNotifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Employees');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();
		$type = (string)($params['type'] ?? $l->t('Time off'));
		$employee = (string)($params['employee'] ?? '');
		$from = (string)($params['from'] ?? '');
		$until = (string)($params['until'] ?? '');

		switch ($notification->getSubject()) {
			case 'absence_requested':
				$subject = $l->t('New time-off request from %s', [$employee]);
				$message = $l->t('%s requested %s from %s to %s.', [$employee, $type, $from, $until]);
				break;
			case 'absence_approved':
				$subject = $l->t('Your time-off request was approved');
				$message = $l->t('Your %s request from %s to %s was approved.', [$type, $from, $until]);
				break;
			case 'absence_rejected':
				$subject = $l->t('Your time-off request was rejected');
				$message = $l->t('Your %s request from %s to %s was rejected.', [$type, $from, $until]);
				break;
			default:
				throw new UnknownNotificationException();
		}

		return $notification
			->setParsedSubject($subject)
			->setParsedMessage($message)
			->setIcon($this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
			))
			->setLink($this->urlGenerator->linkToRouteAbsolute('employees.page.index') . '#/Calendar');
	}
}
