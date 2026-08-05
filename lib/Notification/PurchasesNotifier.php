<?php

declare(strict_types=1);

namespace OCA\Employees\Notification;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class PurchasesNotifier implements INotifier {

	private IFactory $l10nFactory;
	private IURLGenerator $urlGenerator;

	public function __construct(
		IFactory $l10nFactory,
		IURLGenerator $urlGenerator
	) {
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
	}

	public function getID(): string {
		return 'employees';
	}

	public function getName(): string {
		return $this->l10nFactory->get('employees')->t('Employees');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'employees') {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get('employees', $languageCode);

		if ($notification->getSubject() === 'compra_pendiente_autorizacion') {
			return $this->prepareCompraPendiente($notification, $l);
		}

		if ($notification->getSubject() === 'compra_solicitud_autorizada') {
			return $this->prepareCompraAutorizada($notification, $l);
		}

		if ($notification->getSubject() === 'compra_solicitud_rechazada') {
			return $this->prepareCompraRechazada($notification, $l);
		}

		throw new UnknownNotificationException();
	}

	private function prepareCompraPendiente(INotification $notification, IL10N $l): INotification {
		$params = $notification->getSubjectParameters();

		$reference = (string)($params['reference'] ?? 'Solicitud');
		$title = (string)($params['title'] ?? '');
		$solicitante = (string)($params['solicitante'] ?? '');

		$reference = $reference !== '' ? $reference : 'Solicitud';
		$title = $title !== '' ? $title : $l->t('No title');
		$solicitante = $solicitante !== '' ? $solicitante : $l->t('Unknown user');

		$notification->setParsedSubject(
			$l->t('Purchase request pending approval')
		);

		$notification->setParsedMessage(
			$l->t('%s - %s was sent by %s and is waiting for approval.', [
				$reference,
				$title,
				$solicitante,
			])
		);

		$notification->setIcon($this->getAppIconUrl());

		return $notification;
	}

	private function prepareCompraAutorizada(INotification $notification, IL10N $l): INotification {
		$params = $notification->getSubjectParameters();

		$reference = (string)($params['reference'] ?? 'Solicitud');
		$title = (string)($params['title'] ?? '');
		$aprobador = (string)($params['aprobador'] ?? '');
		$comment = (string)($params['comment'] ?? '');

		$reference = $reference !== '' ? $reference : 'Solicitud';
		$title = $title !== '' ? $title : $l->t('No title');
		$aprobador = $aprobador !== '' ? $aprobador : $l->t('an approver');

		$notification->setParsedSubject(
			$l->t('Purchase request approved')
		);

		if ($comment !== '') {
			$notification->setParsedMessage(
				$l->t('%s - %s was approved by %s. Comment: %s', [
					$reference,
					$title,
					$aprobador,
					$comment,
				])
			);
		} else {
			$notification->setParsedMessage(
				$l->t('%s - %s was approved by %s.', [
					$reference,
					$title,
					$aprobador,
				])
			);
		}

		$notification->setIcon($this->getAppIconUrl());

		return $notification;
	}

	private function prepareCompraRechazada(INotification $notification, IL10N $l): INotification {
		$params = $notification->getSubjectParameters();
		$reference = (string)($params['reference'] ?? 'Solicitud');
		$title = (string)($params['title'] ?? $l->t('No title'));
		$aprobador = (string)($params['aprobador'] ?? $l->t('an approver'));
		$comment = (string)($params['comment'] ?? '');

		$notification->setParsedSubject($l->t('Purchase request rejected'));
		$notification->setParsedMessage($comment !== ''
			? $l->t('%s - %s was rejected by %s. Comment: %s', [$reference, $title, $aprobador, $comment])
			: $l->t('%s - %s was rejected by %s.', [$reference, $title, $aprobador]));
		$notification->setIcon($this->getAppIconUrl());

		return $notification;
	}
	private function getAppIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath('employees', 'app.svg')
		);
	}
}
