<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use InvalidArgumentException;
use OCA\Employees\Service\PayrollService;
use OCA\Employees\Service\PermissionsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class PayrollController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private PayrollService $service,
		private PermissionsService $permissions,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/** @NoAdminRequired */
	public function overview(?int $periodId = null): DataResponse {
		return $this->respond(fn(): array => $this->service->overview(
			$periodId,
			$this->uid(),
			$this->permissions->canSeeAny(['payroll.manage', 'payroll.approve', 'payroll.pay', 'payroll.export']),
		));
	}

	/** @NoAdminRequired */
	public function createPeriod(): DataResponse {
		return $this->respond(function (): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->createPeriod($this->request->getParams(), $this->uid());
		}, Http::STATUS_CREATED);
	}

	/** @NoAdminRequired */
	public function createPlan(): DataResponse {
		return $this->respond(function (): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->createPlan($this->request->getParams(), $this->uid());
		}, Http::STATUS_CREATED);
	}

	/** @NoAdminRequired */
	public function updatePlan(int $planId): DataResponse {
		return $this->respond(function () use ($planId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->updatePlan($planId, $this->request->getParams(), $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function saveEmployeeSetup(int $employeeId): DataResponse {
		return $this->respond(function () use ($employeeId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->saveEmployeeSetup($employeeId, $this->request->getParams(), $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function setEmployeeInclusion(int $employeeId): DataResponse {
		return $this->respond(function () use ($employeeId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->setEmployeePayrollEnabled(
				$employeeId,
				filter_var($this->request->getParam('enabled', false), FILTER_VALIDATE_BOOL),
				$this->uid(),
			);
		});
	}

	/** @NoAdminRequired */
	public function createProfile(): DataResponse {
		return $this->respond(function (): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->createProfile($this->request->getParams(), $this->uid());
		}, Http::STATUS_CREATED);
	}

	/** @NoAdminRequired */
	public function updateProfile(int $profileId): DataResponse {
		return $this->respond(function () use ($profileId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->updateProfile($profileId, $this->request->getParams(), $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function createRule(int $profileId): DataResponse {
		return $this->respond(function () use ($profileId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->createRule($profileId, $this->request->getParams(), $this->uid());
		}, Http::STATUS_CREATED);
	}

	/** @NoAdminRequired */
	public function updateRule(int $ruleId): DataResponse {
		return $this->respond(function () use ($ruleId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->updateRule($ruleId, $this->request->getParams(), $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function assignProfile(int $planId): DataResponse {
		return $this->respond(function () use ($planId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->assignProfile($planId, $this->request->getParams(), $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function updateProfileAssignment(int $assignmentId): DataResponse {
		return $this->respond(function () use ($assignmentId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->updateProfileAssignment($assignmentId, $this->request->getParams(), $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function assignEmployeeRule(int $employeeId): DataResponse {
		return $this->respond(function () use ($employeeId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->assignEmployeeRule($employeeId, $this->request->getParams(), $this->uid());
		}, Http::STATUS_CREATED);
	}

	/** @NoAdminRequired */
	public function updateEmployeeRuleAssignment(int $assignmentId): DataResponse {
		return $this->respond(function () use ($assignmentId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->updateEmployeeRuleAssignment($assignmentId, $this->request->getParams(), $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function addInput(int $periodId): DataResponse {
		return $this->respond(function () use ($periodId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->addInput($periodId, $this->request->getParams(), $this->uid());
		}, Http::STATUS_CREATED);
	}

	/** @NoAdminRequired */
	public function updateInput(int $inputId): DataResponse {
		return $this->respond(function () use ($inputId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->updateInput($inputId, $this->request->getParams(), $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function deleteInput(int $inputId): DataResponse {
		return $this->respond(function () use ($inputId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->deleteInput($inputId, $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function calculate(int $periodId): DataResponse {
		return $this->respond(function () use ($periodId): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->calculatePeriod($periodId, $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function approve(int $periodId): DataResponse {
		return $this->respond(function () use ($periodId): array {
			$this->permissions->requireCanSee('payroll.approve');
			return $this->service->approvePeriod($periodId, $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function bankSettings(): DataResponse {
		return $this->respond(function (): array {
			$this->permissions->requireCanSeeAny(['payroll.manage', 'payroll.export']);
			return $this->service->bankSettings();
		});
	}

	/** @NoAdminRequired */
	public function updateBankSettings(): DataResponse {
		return $this->respond(function (): array {
			$this->permissions->requireCanSee('payroll.manage');
			return $this->service->saveBankSettings($this->request->getParams());
		});
	}

	/** @NoAdminRequired */
	public function publish(int $periodId): DataResponse {
		return $this->respond(function () use ($periodId): array {
			$this->permissions->requireCanSeeAny(['payroll.approve', 'payroll.export']);
			return $this->service->publishPeriod($periodId, $this->uid());
		});
	}

	/** @NoAdminRequired */
	public function recordPayment(int $payslipId): DataResponse {
		return $this->respond(function () use ($payslipId): array {
			$this->permissions->requireCanSee('payroll.pay');
			return $this->service->recordPayment($payslipId, $this->request->getParams(), $this->uid());
		}, Http::STATUS_CREATED);
	}

	/** @NoAdminRequired */
	public function export(int $periodId): DataDownloadResponse|DataResponse {
		try {
			$this->permissions->requireCanSee('payroll.export');
			$content = $this->service->exportPeriodCsv($periodId, $this->uid());
			return new DataDownloadResponse($content, 'payroll-' . $periodId . '.csv', 'text/csv; charset=utf-8');
		} catch (Throwable $e) {
			return $this->error($e);
		}
	}

	/** @NoAdminRequired */
	public function exportSepa(int $periodId): DataDownloadResponse|DataResponse {
		try {
			$this->permissions->requireCanSee('payroll.export');
			$content = $this->service->exportSepa($periodId, $this->uid());
			return new DataDownloadResponse($content, 'sepa-payroll-' . $periodId . '.xml', 'application/xml; charset=utf-8');
		} catch (Throwable $e) {
			return $this->error($e);
		}
	}

	/** @NoAdminRequired */
	public function payslipPdf(int $payslipId): DataDownloadResponse|DataResponse {
		try {
			$this->permissions->requireCanSeeAny(['payroll.view', 'payroll.manage', 'payroll.pay', 'payroll.export']);
			$content = $this->service->payslipPdf(
				$payslipId,
				$this->uid(),
				$this->permissions->canSeeAny(['payroll.manage', 'payroll.pay', 'payroll.export']),
			);
			return new DataDownloadResponse($content, 'payslip-' . $payslipId . '.pdf', 'application/pdf');
		} catch (Throwable $e) {
			return $this->error($e);
		}
	}

	/** @NoAdminRequired */
	public function exportPayments(int $periodId): DataDownloadResponse|DataResponse {
		try {
			$this->permissions->requireCanSee('payroll.export');
			$content = $this->service->exportPaymentsCsv($periodId, $this->uid());
			return new DataDownloadResponse($content, 'payroll-payments-' . $periodId . '.csv', 'text/csv; charset=utf-8');
		} catch (Throwable $e) {
			return $this->error($e);
		}
	}

	private function respond(callable $callback, int $status = Http::STATUS_OK): DataResponse {
		try {
			return new DataResponse(['success' => true, 'data' => $callback()], $status);
		} catch (Throwable $e) {
			return $this->error($e);
		}
	}

	private function error(Throwable $e): DataResponse {
		if ($e instanceof OCSForbiddenException || str_contains(strtolower($e->getMessage()), 'permission')) {
			$status = Http::STATUS_FORBIDDEN;
		} elseif ($e instanceof InvalidArgumentException) {
			$status = Http::STATUS_BAD_REQUEST;
		} elseif ($e instanceof RuntimeException) {
			$status = Http::STATUS_CONFLICT;
		} else {
			$status = Http::STATUS_INTERNAL_SERVER_ERROR;
			$this->logger->error('Payroll request failed', ['app' => 'employees', 'exception' => $e]);
		}
		return new DataResponse([
			'success' => false,
			'error' => ['code' => 'PAYROLL_REQUEST_FAILED', 'message' => $e->getMessage()],
		], $status);
	}

	private function uid(): string {
		$uid = $this->userSession->getUser()?->getUID();
		if ($uid === null || $uid === '') {
			throw new RuntimeException('Authentication is required.');
		}
		return $uid;
	}
}
