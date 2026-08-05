<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$mapper = (string)file_get_contents($root . '/lib/Db/FeePaymentMapper.php');
$feeMapper = (string)file_get_contents($root . '/lib/Db/ProfessionalFeeMapper.php');
$controller = (string)file_get_contents($root . '/lib/Controller/FeePaymentsController.php');
$failures = [];

if (!str_contains($mapper, "'fee_payments'")) {
	$failures[] = 'FeePaymentMapper does not use the canonical fee_payments table';
}
if (str_contains($mapper, "'fee_installments'")) {
	$failures[] = 'FeePaymentMapper still references the retired fee_installments table';
}

preg_match_all('/public function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $mapper, $methodMatches);
$publicMethods = array_fill_keys($methodMatches[1], true);

preg_match_all('/\$this->(?:feePaymentMapper|FeePaymentMapper)\s*->\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $feeMapper . "\n" . $controller, $callMatches);
foreach (array_unique($callMatches[1]) as $method) {
	if (!isset($publicMethods[$method])) {
		$failures[] = "FeePaymentMapper call has no public implementation: {$method}";
	}
}

foreach (['findByFee', 'deleteByFee', 'markPaid', 'markInvoiced', 'generateInstallments', 'hasRegisteredPayments', 'cancelPayment', 'addRetainerInstallment', 'sumByFees'] as $method) {
	if (!isset($publicMethods[$method])) {
		$failures[] = "FeePaymentMapper is missing {$method}";
	}
}

foreach (['id_installment', 'id_fee', 'amount_installment', 'installment_start_date', 'installment_end_date', 'date_payment'] as $column) {
	if (!str_contains($mapper, "'{$column}'")) {
		$failures[] = "FeePaymentMapper is missing canonical column {$column}";
	}
}

if ($failures !== []) {
	fwrite(STDERR, implode("\n", $failures) . "\n");
	exit(1);
}

echo 'FEE_PAYMENTS_MAPPER_CONTRACT_OK methods=' . count($publicMethods) . PHP_EOL;
