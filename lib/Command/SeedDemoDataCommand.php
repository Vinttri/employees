<?php

declare(strict_types=1);

namespace OCA\Employees\Command;

use OCA\Employees\Service\DemoDataSeeder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SeedDemoDataCommand extends Command {
	protected static $defaultName = 'employees:seed-demo-data';

	public function __construct(private DemoDataSeeder $seeder) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setDescription('Idempotently fills every Employees module with synthetic TEST data.')
			->addOption('confirm-test', null, InputOption::VALUE_REQUIRED, 'Must be exactly TEST for a mutating run.')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Shows the planned scope without changing data.')
			->addOption('json', null, InputOption::VALUE_NONE, 'Prints the result as JSON.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dryRun = (bool)$input->getOption('dry-run');
		if (!$dryRun && $input->getOption('confirm-test') !== 'TEST') {
			$output->writeln('<error>Refusing to seed data. Use --confirm-test=TEST on the TEST environment.</error>');
			return Command::INVALID;
		}

		try {
			$result = $this->seeder->seed($dryRun);
		} catch (\Throwable $e) {
			$output->writeln('<error>Demo data seeding failed: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}

		if ((bool)$input->getOption('json')) {
			$output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
			return Command::SUCCESS;
		}

		$output->writeln($dryRun ? '<info>Employees demo-data dry run</info>' : '<info>Employees demo data seeded</info>');
		$output->writeln('Available users: ' . $result['available_users']);
		if ($result['missing_users'] !== []) {
			$output->writeln('<comment>Missing users: ' . implode(', ', $result['missing_users']) . '</comment>');
		}
		foreach (($result['counts'] ?? []) as $name => $count) {
			$output->writeln(sprintf('%-32s %d', $name, $count));
		}
		if (isset($result['folders'])) {
			$output->writeln(sprintf(
				'Employee folders: %d created, %d verified',
				$result['folders']['created'],
				$result['folders']['verified'],
			));
		}

		return Command::SUCCESS;
	}
}
