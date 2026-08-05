<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use DateTime;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Throwable;
use OCP\IUserManager;

class PurchaseNotificationService {

	private PurchasePermissionsService $permisosService;
	private IGroupManager $groupManager;
	private INotificationManager $notificationManager;
	private IMailer $mailer;
	private IURLGenerator $urlGenerator;
	private IConfig $config;
	private LoggerInterface $logger;
	private IUserManager $userManager;

	public function __construct(
		PurchasePermissionsService $permisosService,
		IGroupManager $groupManager,
		IUserManager $userManager,
		INotificationManager $notificationManager,
		IMailer $mailer,
		IURLGenerator $urlGenerator,
		IConfig $config,
		LoggerInterface $logger
	) {
		$this->permisosService = $permisosService;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->notificationManager = $notificationManager;
		$this->mailer = $mailer;
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
		$this->logger = $logger;
	}

	public function notificarPendienteAutorizacion($solicitud, string $triggerUserId): void {
		$approvers = $this->getApproverUsers($triggerUserId);

		if (count($approvers) === 0) {
			return;
		}

		$idRequest = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdRequest', 'getId'],
			['id_request', 'id'],
			''
		);

		$reference = (string)$this->readSolicitudValue(
			$solicitud,
			['getReference'],
			['reference'],
			''
		);

		if ($reference === '') {
			$reference = $idRequest !== '' ? 'Solicitud #' . $idRequest : 'Solicitud';
		}

		$title = (string)$this->readSolicitudValue(
			$solicitud,
			['getTitle', 'getTitle'],
			['title', 'title'],
			''
		);

		if ($title === '') {
			$title = 'Sin título';
		}

		$solicitante = (string)$this->readSolicitudValue(
			$solicitud,
			['getRequesterName'],
			['requester_name', 'requester_name', 'displayname', 'created_by', 'id_user'],
			$triggerUserId
		);

		$amount = (string)$this->readSolicitudValue(
			$solicitud,
			['getTotalIncludingTax', 'getFinalAmount', 'getEstimatedAmount'],
			['total_including_tax', 'amount_final', 'amount_estimated'],
			''
		);

		$link = $this->urlGenerator->getAbsoluteURL('/index.php/apps/employees/#/purchases');

		foreach ($approvers as $uid => $user) {
			$this->sendNextcloudNotification(
				$uid,
				$idRequest,
				$reference,
				$title,
				$solicitante,
				$link
			);

			$this->sendMailNotification(
				$user,
				$reference,
				$title,
				$solicitante,
				$amount,
				$link
			);
		}
	}

	public function notificarAprobadorActual($solicitud, string $approverUid, string $role): void {
		$user = $this->userManager->get($approverUid);
		if ($user === null || !$user->isEnabled()) {
			$this->logger->warning('No se pudo notificar al aprobador actual de la compra.', [
				'app' => 'employees',
				'user' => $approverUid,
				'role' => $role,
			]);
			return;
		}

		$idRequest = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdRequest', 'getId'],
			['id_request', 'id'],
			''
		);
		$reference = (string)$this->readSolicitudValue($solicitud, ['getReference'], ['reference'], '');
		$reference = $reference !== '' ? $reference : ($idRequest !== '' ? 'Solicitud #' . $idRequest : 'Solicitud');
		$title = (string)$this->readSolicitudValue($solicitud, ['getTitle'], ['title'], 'Sin título');
		$solicitante = (string)$this->readSolicitudValue(
			$solicitud,
			['getRequesterName'],
			['requester_name', 'id_user'],
			'Solicitante'
		);
		$amount = (string)$this->readSolicitudValue(
			$solicitud,
			['getTotalIncludingTax', 'getFinalAmount', 'getEstimatedAmount'],
			['total_including_tax', 'amount_final', 'amount_estimated'],
			''
		);
		$link = $this->urlGenerator->getAbsoluteURL('/index.php/apps/employees/#/purchases');

		$this->sendNextcloudNotification(
			$approverUid,
			$idRequest,
			$reference,
			$title,
			$solicitante,
			$link,
			$role
		);
		$this->sendMailNotification($user, $reference, $title, $solicitante, $amount, $link);
	}

	private function getApproverUsers(string $excludeUserId): array {
		$users = [];

		foreach ($this->permisosService->getApproverGroupIds() as $groupId) {
			$group = $this->groupManager->get($groupId);

			if ($group === null) {
				continue;
			}

			foreach ($group->getUsers() as $user) {
				if (!$user->isEnabled()) {
					continue;
				}

				$uid = $user->getUID();

				if ($uid === $excludeUserId) {
					continue;
				}

				$users[$uid] = $user;
			}
		}

		return $users;
	}

	private function sendNextcloudNotification(
		string $uid,
		string $idRequest,
		string $reference,
		string $title,
		string $solicitante,
		string $link,
		string $role = ''
	): void {
		try {
			$notification = $this->notificationManager->createNotification();

			$notification
				->setApp('employees')
				->setUser($uid)
				->setDateTime(new DateTime())
				->setObject('compra_solicitud', $idRequest)
				->setSubject('compra_pendiente_autorizacion', [
					'reference' => $reference,
					'title' => $title,
					'solicitante' => $solicitante,
					'role' => $role,
				])
				->setLink($link);

			$this->notificationManager->notify($notification);
		} catch (Throwable $e) {
			$this->logger->warning('No se pudo enviar notificación de compra pendiente.', [
				'app' => 'employees',
				'user' => $uid,
				'exception' => $e,
			]);
		}
	}

	private function sendMailNotification(
		IUser $user,
		string $reference,
		string $title,
		string $solicitante,
		string $amount,
		string $link
	): void {
		$email = $user->getEMailAddress();

		if ($email === null || $email === '') {
			return;
		}

		try {
			$fromLocal = $this->config->getSystemValueString('mail_from_address', 'no-reply');
			$fromDomain = $this->config->getSystemValueString('mail_domain', 'localhost');
			$from = $fromLocal . '@' . $fromDomain;

			$subject = sprintf('Solicitud de compra pendiente: %s', $reference);

			$montoTexto = '';
			if ($amount !== '') {
				$montoTexto = is_numeric($amount)
					? '$' . number_format((float)$amount, 2, '.', ',') . ' MXN'
					: $amount;
			}

			$folioHtml = $this->escapeHtml($reference);
			$tituloHtml = $this->escapeHtml($title);
			$solicitanteHtml = $this->escapeHtml($solicitante);
			$montoHtml = $this->escapeHtml($montoTexto);
			$linkHtml = $this->escapeHtml($link);

			$detallePlain = "Folio: {$reference}\n"
				. "Título: {$title}\n"
				. "Solicitante: {$solicitante}\n"
				. ($montoTexto !== '' ? "Monto: {$montoTexto}\n" : '');

			$montoHtmlRow = $montoTexto !== ''
				? '<span style="display:block; margin-top:8px;">
						<strong>Monto:</strong> ' . $montoHtml . '
					</span>'
				: '';

			$detalleHtml = '
				<span style="
					display:block;
					max-width:440px;
					margin:14px auto 8px auto;
					padding:16px 18px;
					border:1px solid #e5e7eb;
					border-radius:10px;
					background:#f8f9fa;
					text-align:left;
					line-height:1.5;
					color:#222;
				">
					<span style="display:block; margin-bottom:8px;">
						<strong>Folio:</strong> ' . $folioHtml . '
					</span>

					<span style="display:block; margin-bottom:8px;">
						<strong>Título:</strong> ' . $tituloHtml . '
					</span>

					<span style="display:block; margin-bottom:8px;">
						<strong>Solicitante:</strong> ' . $solicitanteHtml . '
					</span>

					' . $montoHtmlRow . '
				</span>
			';

			$emailTemplate = $this->mailer->createEMailTemplate('employees.CompraPendienteAutorizacion', [
				'reference' => $reference,
				'title' => $title,
				'solicitante' => $solicitante,
				'amount' => $montoTexto,
				'link' => $link,
			]);

			$emailTemplate->setSubject($subject);
			$emailTemplate->addHeader();

			$emailTemplate->addHeading('Solicitud de compra pendiente');

			$emailTemplate->addBodyText(
				'Hay una nueva solicitud de compra pendiente de autorización.',
				'Hay una nueva solicitud de compra pendiente de autorización.'
			);

			$emailTemplate->addBodyText($detalleHtml, $detallePlain);

			$emailTemplate->addBodyText(
				'Revisa la solicitud desde el módulo de purchases.',
				'Revisa la solicitud desde el módulo de purchases.'
			);

			$emailTemplate->addBodyButton('Revisar solicitud', $linkHtml);

			$emailTemplate->addFooter();

			$message = $this->mailer->createMessage();
			$message->setFrom([$from => 'Empleados']);
			$message->setTo([$email => $user->getDisplayName()]);
			$message->useTemplate($emailTemplate);

			$this->mailer->send($message);
		} catch (Throwable $e) {
			$this->logger->warning('No se pudo enviar email de compra pendiente.', [
				'app' => 'employees',
				'user' => $user->getUID(),
				'mail' => $email,
				'exception' => $e,
			]);
		}
	}

	private function readGetter($object, string $getter, $default = null) {
		if (is_object($object) && method_exists($object, $getter)) {
			return $object->$getter();
		}

		return $default;
	}
	public function notificarSolicitudAutorizada($solicitud, string $aprobadorUserId, ?string $comment = null): void {
		$requesterUid = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdUser'],
			['id_user', 'created_by', 'requester_uid'],
			''
		);

		if ($requesterUid === '') {
			$this->logger->warning('No se pudo notificar autorización de compra porque la solicitud no tiene usuario solicitante.', [
				'app' => 'employees',
				'aprobador' => $aprobadorUserId,
			]);

			return;
		}

		$user = $this->userManager->get($requesterUid);

		if ($user === null || !$user->isEnabled()) {
			$this->logger->warning('No se pudo notificar autorización de compra porque el usuario solicitante no existe o está deshabilitado.', [
				'app' => 'employees',
				'user' => $requesterUid,
				'aprobador' => $aprobadorUserId,
			]);

			return;
		}

		$idRequest = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdRequest', 'getId'],
			['id_request', 'id'],
			''
		);

		$reference = (string)$this->readSolicitudValue(
			$solicitud,
			['getReference'],
			['reference'],
			''
		);

		if ($reference === '') {
			$reference = $idRequest !== '' ? 'Solicitud #' . $idRequest : 'Solicitud';
		}

		$title = (string)$this->readSolicitudValue(
			$solicitud,
			['getTitle'],
			['title'],
			''
		);

		if ($title === '') {
			$title = 'Sin título';
		}

		$amount = (string)$this->readSolicitudValue(
			$solicitud,
			['getTotalIncludingTax', 'getFinalAmount', 'getEstimatedAmount'],
			['total_including_tax', 'amount_final', 'amount_estimated'],
			''
		);

		$link = $this->urlGenerator->getAbsoluteURL('/index.php/apps/employees/#/purchases');

		$this->sendNextcloudStatusNotification(
			$requesterUid,
			$idRequest,
			'compra_solicitud_autorizada',
			[
				'reference' => $reference,
				'title' => $title,
				'aprobador' => $aprobadorUserId,
				'comment' => $comment ?: '',
			],
			$link
		);

		$this->sendMailStatusNotification(
			$user,
			'Solicitud de compra autorizada',
			sprintf('Tu solicitud de compra fue autorizada: %s', $reference),
			$reference,
			$title,
			$amount,
			$aprobadorUserId,
			$comment,
			$link,
			'Tu solicitud de compra fue autorizada.'
		);
	}

	public function notificarSolicitudRechazada($solicitud, string $aprobadorUserId, ?string $comment = null): void {
		$requesterUid = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdUser'],
			['id_user', 'created_by'],
			''
		);
		$user = $requesterUid !== '' ? $this->userManager->get($requesterUid) : null;
		if ($user === null || !$user->isEnabled()) {
			$this->logger->warning('No se pudo notificar el rechazo de la solicitud de compra.', [
				'app' => 'employees',
				'user' => $requesterUid,
			]);
			return;
		}

		$idRequest = (string)$this->readSolicitudValue($solicitud, ['getIdRequest'], ['id_request'], '');
		$reference = (string)$this->readSolicitudValue($solicitud, ['getReference'], ['reference'], 'Solicitud');
		$title = (string)$this->readSolicitudValue($solicitud, ['getTitle'], ['title'], 'Sin título');
		$amount = (string)$this->readSolicitudValue(
			$solicitud,
			['getTotalIncludingTax', 'getFinalAmount', 'getEstimatedAmount'],
			['total_including_tax', 'amount_final', 'amount_estimated'],
			''
		);
		$link = $this->urlGenerator->getAbsoluteURL('/index.php/apps/employees/#/purchases');

		$this->sendNextcloudStatusNotification(
			$requesterUid,
			$idRequest,
			'compra_solicitud_rechazada',
			[
				'reference' => $reference,
				'title' => $title,
				'aprobador' => $aprobadorUserId,
				'comment' => $comment ?: '',
			],
			$link
		);
		$this->sendMailStatusNotification(
			$user,
			'Solicitud de compra rechazada',
			sprintf('Tu solicitud de compra fue rechazada: %s', $reference),
			$reference,
			$title,
			$amount,
			$aprobadorUserId,
			$comment,
			$link,
			'Tu solicitud de compra fue rechazada.'
		);
	}

	private function sendNextcloudStatusNotification(
		string $uid,
		string $idRequest,
		string $subject,
		array $parameters,
		string $link
	): void {
		try {
			$notification = $this->notificationManager->createNotification();

			$notification
				->setApp('employees')
				->setUser($uid)
				->setDateTime(new DateTime())
				->setObject('compra_solicitud', $idRequest)
				->setSubject($subject, $parameters)
				->setLink($link);

			$this->notificationManager->notify($notification);
		} catch (Throwable $e) {
			$this->logger->warning('No se pudo enviar notificación de status de compra.', [
				'app' => 'employees',
				'user' => $uid,
				'subject' => $subject,
				'exception' => $e,
			]);
		}
	}

	private function sendMailStatusNotification(
		IUser $user,
		string $heading,
		string $subject,
		string $reference,
		string $title,
		string $amount,
		string $aprobador,
		?string $comment,
		string $link,
		string $statusText
	): void {
		$email = $user->getEMailAddress();

		if ($email === null || $email === '') {
			return;
		}

		try {
			$fromLocal = $this->config->getSystemValueString('mail_from_address', 'no-reply');
			$fromDomain = $this->config->getSystemValueString('mail_domain', 'localhost');
			$from = $fromLocal . '@' . $fromDomain;

			$montoTexto = '';
			if ($amount !== '') {
				$montoTexto = is_numeric($amount)
					? '$' . number_format((float)$amount, 2, '.', ',') . ' MXN'
					: $amount;
			}

			$detallePlain = "Folio: {$reference}\n"
				. "Título: {$title}\n"
				. "Autorizó: {$aprobador}\n"
				. ($montoTexto !== '' ? "Monto: {$montoTexto}\n" : '')
				. ($comment ? "Comentario: {$comment}\n" : '');

			$detalleHtml = '
				<span style="
					display:block;
					max-width:440px;
					margin:14px auto 8px auto;
					padding:16px 18px;
					border:1px solid #e5e7eb;
					border-radius:10px;
					background:#f8f9fa;
					text-align:left;
					line-height:1.5;
					color:#222;
				">
					<span style="display:block; margin-bottom:8px;">
						<strong>Folio:</strong> ' . $this->escapeHtml($reference) . '
					</span>

					<span style="display:block; margin-bottom:8px;">
						<strong>Título:</strong> ' . $this->escapeHtml($title) . '
					</span>

					<span style="display:block; margin-bottom:8px;">
						<strong>Autorizó:</strong> ' . $this->escapeHtml($aprobador) . '
					</span>

					' . ($montoTexto !== '' ? '
						<span style="display:block; margin-bottom:8px;">
							<strong>Monto:</strong> ' . $this->escapeHtml($montoTexto) . '
						</span>
					' : '') . '

					' . ($comment ? '
						<span style="display:block; margin-top:8px;">
							<strong>Comentario:</strong> ' . $this->escapeHtml($comment) . '
						</span>
					' : '') . '
				</span>
			';

			$emailTemplate = $this->mailer->createEMailTemplate('employees.CompraSolicitudAutorizada', [
				'reference' => $reference,
				'title' => $title,
				'amount' => $montoTexto,
				'aprobador' => $aprobador,
				'comment' => $comment,
				'link' => $link,
			]);

			$emailTemplate->setSubject($subject);
			$emailTemplate->addHeader();
			$emailTemplate->addHeading($heading);

			$emailTemplate->addBodyText(
				$statusText,
				$statusText
			);

			$emailTemplate->addBodyText($detalleHtml, $detallePlain);

			$emailTemplate->addBodyText(
				'Puedes revisar el detalle desde el módulo de purchases.',
				'Puedes revisar el detalle desde el módulo de purchases.'
			);

			$emailTemplate->addBodyButton('Ver solicitud', $link);
			$emailTemplate->addFooter();

			$message = $this->mailer->createMessage();
			$message->setFrom([$from => 'Empleados']);
			$message->setTo([$email => $user->getDisplayName()]);
			$message->useTemplate($emailTemplate);

			$this->mailer->send($message);
		} catch (Throwable $e) {
			$this->logger->warning('No se pudo enviar email de status de compra.', [
				'app' => 'employees',
				'user' => $user->getUID(),
				'mail' => $email,
				'exception' => $e,
			]);
		}
	}
	private function readSolicitudValue($object, array $getters, array $keys, $default = null) {
		if (is_object($object)) {
			foreach ($getters as $getter) {
				if (method_exists($object, $getter)) {
					$value = $object->$getter();

					if ($value !== null && $value !== '') {
						return $value;
					}
				}
			}

			if (method_exists($object, 'jsonSerialize')) {
				$data = $object->jsonSerialize();

				if (is_array($data)) {
					foreach ($keys as $key) {
						if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
							return $data[$key];
						}
					}
				}
			}
		}

		if (is_array($object)) {
			foreach ($keys as $key) {
				if (array_key_exists($key, $object) && $object[$key] !== null && $object[$key] !== '') {
					return $object[$key];
				}
			}
		}

		return $default;
	}

	private function escapeHtml(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
