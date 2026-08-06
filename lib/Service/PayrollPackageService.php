<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use DOMDocument;
use InvalidArgumentException;
use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\PayrollRepository;
use OCA\Employees\Db\SettingsMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\L10N\IFactory;
use RuntimeException;
use Throwable;

final class PayrollPackageService {
	private const STORAGE_ROOT = 'Employees_storage';
	private const SETTING_KEYS = [
		'company_name' => 'payroll_company_name',
		'company_iban' => 'payroll_company_iban',
		'company_bic' => 'payroll_company_bic',
		'payment_reference' => 'payroll_payment_reference',
	];

	public function __construct(
		private PayrollRepository $repository,
		private SettingsMapper $settingsMapper,
		private PdfService $pdfService,
		private IRootFolder $rootFolder,
		private IConfig $config,
		private IFactory $l10nFactory,
	) {
	}

	/** @return array<string, string> */
	public function bankSettings(): array {
		$settings = [];
		foreach (self::SETTING_KEYS as $public => $stored) {
			$default = $public === 'payment_reference' ? 'Salary {period}' : '';
			$settings[$public] = $this->config->getAppValue(Application::APP_ID, $stored, $default);
		}
		return $settings;
	}

	/** @return array<string, string> */
	public function saveBankSettings(array $payload): array {
		$values = [
			'company_name' => $this->requiredText($payload['company_name'] ?? null, 'Company name', 140),
			'company_iban' => $this->iban($payload['company_iban'] ?? null, 'Company IBAN'),
			'company_bic' => $this->bic($payload['company_bic'] ?? null),
			'payment_reference' => $this->requiredText($payload['payment_reference'] ?? 'Salary {period}', 'Payment reference', 140),
		];
		foreach (self::SETTING_KEYS as $public => $stored) {
			$this->config->setAppValue(Application::APP_ID, $stored, $values[$public]);
		}
		return $values;
	}

	public function employeeIban(mixed $value): string {
		return $this->iban($value, 'Employee IBAN');
	}

	public function csv(int $periodId, string $actorUid): string {
		$period = $this->exportablePeriod($periodId);
		$l = $this->l10nFactory->get(Application::APP_ID, $this->language($actorUid));
		$stream = fopen('php://temp', 'w+');
		if ($stream === false) {
			throw new RuntimeException('Could not create the payroll register.');
		}
		fwrite($stream, "sep=;\r\n");
		fputcsv($stream, [
			$l->t('Employee'), $l->t('User ID'), $l->t('Employee number'), $l->t('IBAN'),
			$l->t('Period'), $l->t('Currency'), $l->t('Gross'), $l->t('Deductions'),
			$l->t('Net'), $l->t('Already paid'), $l->t('Amount to transfer'), $l->t('Status'),
		], ';', '"', '');
		foreach ($this->repository->listPayslips($periodId) as $row) {
			$outstanding = bcsub((string)$row['net_amount'], (string)$row['paid_amount'], 2);
			fputcsv($stream, array_map([$this, 'safeSpreadsheetValue'], [
				(string)($row['display_name'] ?: $row['id_user']), (string)$row['id_user'],
				(string)($row['number_employee'] ?? ''), $this->normalizedIban((string)($row['number_account'] ?? '')),
				(string)$period['name'], (string)$row['currency'], (string)$row['gross_amount'],
				(string)$row['deduction_amount'], (string)$row['net_amount'], (string)$row['paid_amount'],
				$outstanding, (string)$row['status'],
			]), ';', '"', '');
		}
		rewind($stream);
		$content = stream_get_contents($stream);
		fclose($stream);
		$this->repository->audit($actorUid, 'period_exported', 'period', $periodId, ['format' => 'csv']);
		return "\xEF\xBB\xBF" . ($content === false ? '' : $content);
	}

	public function sepa(int $periodId, string $actorUid): string {
		$period = $this->exportablePeriod($periodId);
		if ((string)$period['currency'] !== 'EUR') {
			throw new RuntimeException('SEPA export is available only for EUR payroll periods.');
		}
		$settings = $this->bankSettings();
		$debtorName = $this->requiredText($settings['company_name'], 'Company name', 140);
		$debtorIban = $this->iban($settings['company_iban'], 'Company IBAN');
		$debtorBic = $this->bic($settings['company_bic']);

		$transactions = [];
		$errors = [];
		foreach ($this->repository->listPayslips($periodId) as $row) {
			$amount = bcsub((string)$row['net_amount'], (string)$row['paid_amount'], 2);
			if (bccomp($amount, '0', 2) <= 0) {
				continue;
			}
			try {
				$creditorIban = $this->iban($row['number_account'] ?? null, 'Employee IBAN');
			} catch (InvalidArgumentException $e) {
				$errors[] = (string)($row['display_name'] ?: $row['id_user']) . ': ' . $e->getMessage();
				continue;
			}
			$transactions[] = [
				'id' => 'SAL-' . (int)$period['id'] . '-' . (int)$row['id'],
				'name' => $this->requiredText($row['display_name'] ?: $row['id_user'], 'Employee name', 140),
				'iban' => $creditorIban,
				'amount' => $amount,
			];
		}
		if ($errors !== []) {
			throw new InvalidArgumentException('SEPA file was not created: ' . implode('; ', $errors));
		}
		if ($transactions === []) {
			throw new RuntimeException('There are no outstanding payroll amounts to transfer.');
		}

		$total = '0.00';
		foreach ($transactions as $transaction) {
			$total = bcadd($total, $transaction['amount'], 2);
		}
		$periodKey = $this->periodKey($period);
		$messageId = $this->sepaId('PAYROLL-' . $periodKey . '-' . (int)$period['id'] . '-' . gmdate('YmdHis'));
		$paymentId = $this->sepaId('PAYROLL-' . $periodKey . '-' . (int)$period['id']);
		$reference = str_replace('{period}', $periodKey, $settings['payment_reference']);

		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;
		$root = $doc->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.001.001.09', 'Document');
		$doc->appendChild($root);
		$initiation = $root->appendChild($doc->createElement('CstmrCdtTrfInitn'));
		$group = $initiation->appendChild($doc->createElement('GrpHdr'));
		$this->xmlText($doc, $group, 'MsgId', $messageId);
		$this->xmlText($doc, $group, 'CreDtTm', gmdate('Y-m-d\TH:i:s\Z'));
		$this->xmlText($doc, $group, 'NbOfTxs', (string)count($transactions));
		$this->xmlText($doc, $group, 'CtrlSum', $total);
		$initiatingParty = $group->appendChild($doc->createElement('InitgPty'));
		$this->xmlText($doc, $initiatingParty, 'Nm', $debtorName);

		$payment = $initiation->appendChild($doc->createElement('PmtInf'));
		$this->xmlText($doc, $payment, 'PmtInfId', $paymentId);
		$this->xmlText($doc, $payment, 'PmtMtd', 'TRF');
		$this->xmlText($doc, $payment, 'BtchBookg', 'true');
		$this->xmlText($doc, $payment, 'NbOfTxs', (string)count($transactions));
		$this->xmlText($doc, $payment, 'CtrlSum', $total);
		$type = $payment->appendChild($doc->createElement('PmtTpInf'));
		$level = $type->appendChild($doc->createElement('SvcLvl'));
		$this->xmlText($doc, $level, 'Cd', 'SEPA');
		$date = $payment->appendChild($doc->createElement('ReqdExctnDt'));
		$this->xmlText($doc, $date, 'Dt', date('Y-m-d'));
		$debtor = $payment->appendChild($doc->createElement('Dbtr'));
		$this->xmlText($doc, $debtor, 'Nm', $debtorName);
		$debtorAccount = $payment->appendChild($doc->createElement('DbtrAcct'));
		$debtorAccountId = $debtorAccount->appendChild($doc->createElement('Id'));
		$this->xmlText($doc, $debtorAccountId, 'IBAN', $debtorIban);
		$debtorAgent = $payment->appendChild($doc->createElement('DbtrAgt'));
		$financialInstitution = $debtorAgent->appendChild($doc->createElement('FinInstnId'));
		if ($debtorBic !== '') {
			$this->xmlText($doc, $financialInstitution, 'BICFI', $debtorBic);
		} else {
			$other = $financialInstitution->appendChild($doc->createElement('Othr'));
			$this->xmlText($doc, $other, 'Id', 'NOTPROVIDED');
		}
		$this->xmlText($doc, $payment, 'ChrgBr', 'SLEV');

		foreach ($transactions as $transaction) {
			$transfer = $payment->appendChild($doc->createElement('CdtTrfTxInf'));
			$id = $transfer->appendChild($doc->createElement('PmtId'));
			$this->xmlText($doc, $id, 'EndToEndId', $this->sepaId($transaction['id']));
			$amount = $transfer->appendChild($doc->createElement('Amt'));
			$instructed = $amount->appendChild($doc->createElement('InstdAmt', $transaction['amount']));
			$instructed->setAttribute('Ccy', 'EUR');
			$creditorAgent = $transfer->appendChild($doc->createElement('CdtrAgt'));
			$creditorInstitution = $creditorAgent->appendChild($doc->createElement('FinInstnId'));
			$creditorOther = $creditorInstitution->appendChild($doc->createElement('Othr'));
			$this->xmlText($doc, $creditorOther, 'Id', 'NOTPROVIDED');
			$creditor = $transfer->appendChild($doc->createElement('Cdtr'));
			$this->xmlText($doc, $creditor, 'Nm', $transaction['name']);
			$creditorAccount = $transfer->appendChild($doc->createElement('CdtrAcct'));
			$creditorAccountId = $creditorAccount->appendChild($doc->createElement('Id'));
			$this->xmlText($doc, $creditorAccountId, 'IBAN', $transaction['iban']);
			$remittance = $transfer->appendChild($doc->createElement('RmtInf'));
			$this->xmlText($doc, $remittance, 'Ustrd', mb_substr($reference, 0, 140));
		}

		$this->repository->audit($actorUid, 'period_exported', 'period', $periodId, ['format' => 'sepa_pain_001_001_09']);
		return $doc->saveXML() ?: '';
	}

	/** @return array<string, mixed> */
	public function publish(int $periodId, string $actorUid): array {
		$period = $this->exportablePeriod($periodId);
		$periodKey = $this->periodKey($period);
		$basePath = self::STORAGE_ROOT . '/Payroll/Calculations/' . $periodKey;
		$this->repository->updatePeriodPackage($periodId, 'generating', $basePath, null);
		$errors = [];
		$files = [];
		try {
			$folder = $this->ensureFolder($this->dataManagerFolder(), $basePath);
			$files[] = $this->write($folder, 'Payroll register - ' . $periodKey . '.csv', $this->csv($periodId, $actorUid), $basePath);
			try {
				$files[] = $this->write($folder, 'SEPA credit transfer - ' . $periodKey . '.xml', $this->sepa($periodId, $actorUid), $basePath);
			} catch (Throwable $e) {
				$errors[] = $e->getMessage();
			}
			$sharedPayslips = $this->ensureFolder($folder, 'Payslips');
			foreach ($this->repository->listPayslips($periodId) as $payslip) {
				try {
					$pdf = $this->payslipPdf((int)$payslip['id']);
					$fileName = $this->payslipFileName($periodKey, (string)($payslip['display_name'] ?: $payslip['id_user']));
					$this->write($sharedPayslips, $fileName, $pdf, $basePath . '/Payslips');
					$employee = $this->repository->findEmployee((int)$payslip['employee_id']);
					$employeePath = self::STORAGE_ROOT . '/' . $this->employeeFolderName($employee) . '/Official documents/Payroll';
					$employeeFolder = $this->ensureFolder($this->dataManagerFolder(), $employeePath);
					$personalFile = $this->write($employeeFolder, $fileName, $pdf, $employeePath);
					$this->repository->updatePayslipDocument((int)$payslip['id'], 'ready', $personalFile['file_id'], $personalFile['path'], null);
				} catch (Throwable $e) {
					$this->repository->updatePayslipDocument((int)$payslip['id'], 'error', null, null, $e->getMessage());
					$errors[] = (string)($payslip['display_name'] ?: $payslip['id_user']) . ': ' . $e->getMessage();
				}
			}
			$status = $errors === [] ? 'ready' : 'error';
			$this->repository->updatePeriodPackage($periodId, $status, $basePath, $errors === [] ? null : implode("\n", $errors));
			$this->repository->audit($actorUid, 'period_package_published', 'period', $periodId, ['status' => $status, 'files' => count($files), 'errors' => count($errors)]);
			return ['status' => $status, 'path' => $basePath, 'files' => $files, 'errors' => $errors];
		} catch (Throwable $e) {
			$this->repository->updatePeriodPackage($periodId, 'error', $basePath, $e->getMessage());
			throw $e;
		}
	}

	public function payslipPdf(int $payslipId): string {
		$payslip = $this->repository->findPayslipById($payslipId);
		$period = $this->repository->findPeriod((int)$payslip['period_id']);
		$employee = $this->repository->findEmployee((int)$payslip['employee_id']);
		$l = $this->l10nFactory->get(Application::APP_ID, $this->language((string)$employee['id_user']));
		$lines = $this->repository->listPayslipLines($payslipId);
		$rows = '';
		foreach ($lines as $line) {
			$rows .= '<tr><td>' . $this->html((string)$line['name']) . '</td><td>' . $this->html((string)$line['category']) . '</td><td style="text-align:right">' . $this->html((string)$line['amount']) . '</td></tr>';
		}
		$html = '<style>body{font-family:dejavusans;color:#222}h1{font-size:24px}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{padding:8px;border-bottom:1px solid #ddd}th{text-align:left}.summary{margin-top:24px}.summary td:last-child{text-align:right;font-weight:bold}</style>'
			. '<h1>' . $this->html($l->t('Payslip')) . '</h1>'
			. '<p><strong>' . $this->html($l->t('Employee')) . ':</strong> ' . $this->html((string)($employee['display_name'] ?: $employee['id_user'])) . '<br>'
			. '<strong>' . $this->html($l->t('Period')) . ':</strong> ' . $this->html((string)$period['name']) . ' (' . $this->html((string)$period['date_from']) . ' — ' . $this->html((string)$period['date_until']) . ')<br>'
			. '<strong>' . $this->html($l->t('Hours')) . ':</strong> ' . $this->html((string)$payslip['hours']) . '</p>'
			. '<table><thead><tr><th>' . $this->html($l->t('Item')) . '</th><th>' . $this->html($l->t('Category')) . '</th><th style="text-align:right">' . $this->html($l->t('Amount')) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
			. '<table class="summary"><tr><td>' . $this->html($l->t('Gross')) . '</td><td>' . $this->html((string)$payslip['gross_amount']) . ' ' . $this->html((string)$payslip['currency']) . '</td></tr>'
			. '<tr><td>' . $this->html($l->t('Deductions')) . '</td><td>' . $this->html((string)$payslip['deduction_amount']) . ' ' . $this->html((string)$payslip['currency']) . '</td></tr>'
			. '<tr><td>' . $this->html($l->t('Net')) . '</td><td>' . $this->html((string)$payslip['net_amount']) . ' ' . $this->html((string)$payslip['currency']) . '</td></tr></table>';
		return $this->pdfService->generate($html);
	}

	/** @return array<string, mixed> */
	private function exportablePeriod(int $periodId): array {
		$period = $this->repository->findPeriod($periodId);
		if (!in_array((string)$period['status'], ['approved', 'paid'], true)) {
			throw new RuntimeException('Only approved payroll can be exported or published.');
		}
		return $period;
	}

	private function dataManagerFolder(): Folder {
		$uid = $this->settingsMapper->GetGestor()[0]['data'] ?? null;
		if (!is_string($uid) || trim($uid) === '') {
			throw new RuntimeException('Select the data manager in Employees settings first.');
		}
		$folder = $this->rootFolder->getUserFolder($uid);
		if (!$folder->nodeExists(self::STORAGE_ROOT)) {
			throw new RuntimeException('Employees_storage Team Folder is not available to the data manager.');
		}
		return $folder;
	}

	private function ensureFolder(Folder $base, string $path): Folder {
		$folder = $base;
		foreach (array_filter(explode('/', trim($path, '/'))) as $part) {
			$part = $this->safePath((string)$part);
			if ($folder->nodeExists($part)) {
				$node = $folder->get($part);
				if (!$node instanceof Folder) {
					throw new RuntimeException('A file blocks the payroll folder path: ' . $part);
				}
				$folder = $node;
			} else {
				$folder = $folder->newFolder($part);
			}
		}
		return $folder;
	}

	/** @return array{file_id:int,path:string,name:string} */
	private function write(Folder $folder, string $name, string $content, string $path): array {
		$name = $this->safePath($name);
		if ($folder->nodeExists($name)) {
			$node = $folder->get($name);
			if (!$node instanceof File) {
				throw new RuntimeException('A folder blocks the payroll file: ' . $name);
			}
			$file = $node;
		} else {
			$file = $folder->newFile($name);
		}
		$file->putContent($content);
		return ['file_id' => $file->getId(), 'path' => trim($path, '/') . '/' . $name, 'name' => $name];
	}

	private function periodKey(array $period): string {
		return substr((string)$period['date_from'], 0, 7);
	}

	private function employeeFolderName(array $employee): string {
		return $this->safePath((string)$employee['id_user'] . ' - ' . mb_strtoupper((string)($employee['display_name'] ?: $employee['id_user']), 'UTF-8'));
	}

	private function payslipFileName(string $period, string $employeeName): string {
		return $this->safePath('Payslip - ' . $period . ' - ' . $employeeName . '.pdf');
	}

	private function safePath(string $value): string {
		$value = trim((string)preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $value), '. ');
		return mb_substr($value === '' ? 'Payroll' : $value, 0, 190);
	}

	private function language(string $uid): string {
		return $this->config->getUserValue($uid, 'core', 'lang', 'en');
	}

	private function iban(mixed $value, string $label): string {
		$iban = $this->normalizedIban((string)$value);
		if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) || !$this->validIbanChecksum($iban)) {
			throw new InvalidArgumentException($label . ' is missing or invalid.');
		}
		return $iban;
	}

	private function normalizedIban(string $value): string {
		return strtoupper((string)preg_replace('/\s+/', '', trim($value)));
	}

	private function validIbanChecksum(string $iban): bool {
		$rearranged = substr($iban, 4) . substr($iban, 0, 4);
		$numeric = '';
		foreach (str_split($rearranged) as $char) {
			$numeric .= ctype_alpha($char) ? (string)(ord($char) - 55) : $char;
		}
		$remainder = 0;
		foreach (str_split($numeric) as $digit) {
			$remainder = ($remainder * 10 + (int)$digit) % 97;
		}
		return $remainder === 1;
	}

	private function bic(mixed $value): string {
		$bic = strtoupper((string)preg_replace('/\s+/', '', trim((string)$value)));
		if ($bic !== '' && !preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic)) {
			throw new InvalidArgumentException('Company BIC is invalid.');
		}
		return $bic;
	}

	private function requiredText(mixed $value, string $label, int $max): string {
		$text = trim((string)$value);
		if ($text === '') {
			throw new InvalidArgumentException($label . ' is required.');
		}
		return mb_substr($text, 0, $max);
	}

	private function safeSpreadsheetValue(mixed $value): string {
		$text = (string)$value;
		return preg_match('/^[=+\-@]/u', $text) ? "'" . $text : $text;
	}

	private function sepaId(string $value): string {
		$value = strtoupper((string)preg_replace("/[^A-Za-z0-9\/\-?:().,'+ ]/", '-', $value));
		$value = trim((string)preg_replace('#/{2,}#', '/', $value), '/');
		return mb_substr($value === '' ? 'NOTPROVIDED' : $value, 0, 35);
	}

	private function xmlText(DOMDocument $doc, \DOMElement $parent, string $name, string $value): void {
		$element = $doc->createElement($name);
		$element->appendChild($doc->createTextNode($value));
		$parent->appendChild($element);
	}

	private function html(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
