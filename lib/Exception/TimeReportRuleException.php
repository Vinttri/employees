<?php

declare(strict_types=1);

namespace OCA\Employees\Exception;

class TimeReportRuleException extends \DomainException {
	public function __construct(string $message, private int $httpStatus) {
		parent::__construct($message);
	}

	public function getHttpStatus(): int {
		return $this->httpStatus;
	}
}
