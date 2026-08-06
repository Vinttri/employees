<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\Employees\Db\Absence;
use OCA\Employees\Db\AbsenceMapper;
use OCA\Employees\Db\DepartmentMapper;
use OCA\Employees\Db\DirectorySyncMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\EmployeeOrgChartMapper;
use OCA\Employees\Db\PositionMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\TeamMapper;
use OCA\Employees\Db\UserSavings;
use OCA\Employees\Db\UserSavingsMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Teams\ITeamManager;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

/** Insert-only synchronization from the Nextcloud directory into Employees. */
class DirectorySyncService {
	private const STORAGE_FOLDER = 'Employees_storage';
	private const SOURCE_NEXTCLOUD = 'nextcloud';
	private const SOURCE_CONTACTS = 'contacts';
	private const SOURCE_TEAMS = 'teams';

	public function __construct(
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IRootFolder $rootFolder,
		private IDBConnection $db,
		private EmployeeMapper $employeeMapper,
		private DepartmentMapper $departmentMapper,
		private PositionMapper $positionMapper,
		private TeamMapper $teamMapper,
		private AbsenceMapper $absenceMapper,
		private UserSavingsMapper $userSavingsMapper,
		private EmployeeOrgChartMapper $orgChartMapper,
		private DirectorySyncMapper $syncMapper,
		private SettingsMapper $settingsMapper,
		private CardDavBackend $cardDavBackend,
		private ITeamManager $teamManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param list<string>|null $onlyUids null synchronizes every enabled user
	 * @return array<string, mixed>
	 */
	public function sync(?array $onlyUids = null): array {
		$startedAt = date('c');
		$summary = [
			'status' => 'ok',
			'started_at' => $startedAt,
			'finished_at' => null,
			'created' => ['employees' => 0, 'departments' => 0, 'positions' => 0, 'teams' => 0, 'relations' => 0],
			'adopted' => ['employees' => 0, 'departments' => 0, 'positions' => 0, 'teams' => 0, 'relations' => 0],
			'unchanged' => 0,
			'suppressed' => 0,
			'incomplete' => [],
			'warnings' => [],
		];

		try {
			$dataManagerUid = $this->getDataManagerUid();
			$dataManagerFolder = $this->getDataManagerFolder($dataManagerUid);
			$users = $this->getEnabledUsers($onlyUids);
			$contacts = $this->loadContacts($dataManagerUid, $users, $summary);
			[$nextcloudTeams, $userTeamIds] = $this->loadNextcloudTeams($users, $summary);

			$departmentIds = [];
			$positionIds = [];
			$teamIds = [];

			foreach ($contacts as $uid => $contact) {
				$parentId = null;
				$pathParts = [];
				foreach ($contact['organization_path'] as $part) {
					$pathParts[] = $part;
					$path = implode(' / ', $pathParts);
					$parentId = $this->ensureDepartment($path, $part, $parentId, $summary);
				}
				if ($parentId !== null) {
					$departmentIds[$uid] = $parentId;
					$teamIds['department:' . $uid] = $this->ensureTeam(
						self::SOURCE_CONTACTS,
						$this->sourceKey('department-team', implode('|', $contact['organization_path'])),
						(string)end($contact['organization_path']),
						$summary,
					);
				}

				if ($contact['position'] !== '') {
					$positionIds[$uid] = $this->ensurePosition($contact['position'], $summary);
				}
			}

			foreach ($nextcloudTeams as $sourceId => $name) {
				$teamIds['nextcloud:' . $sourceId] = $this->ensureTeam(
					self::SOURCE_TEAMS,
					$this->sourceKey('team', $sourceId),
					$name,
					$summary,
				);
			}

			$employeeIds = [];
			foreach ($users as $uid => $user) {
				$contact = $contacts[$uid] ?? null;
				$candidateTeamIds = [];
				foreach ($userTeamIds[$uid] ?? [] as $sourceId) {
					$id = $teamIds['nextcloud:' . $sourceId] ?? null;
					if ($id !== null) {
						$candidateTeamIds[$id] = true;
					}
				}
				if ($candidateTeamIds === [] && isset($teamIds['department:' . $uid])) {
					$candidateTeamIds[$teamIds['department:' . $uid]] = true;
				}
				$teamId = count($candidateTeamIds) === 1 ? (int)array_key_first($candidateTeamIds) : null;
				if (count($candidateTeamIds) > 1) {
					$this->addIncomplete($summary, $uid, 'team', 'Multiple Nextcloud teams match; select the employee team manually.');
				}

				$employeeId = $this->ensureEmployee(
					$user,
					$contact,
					$departmentIds[$uid] ?? null,
					$positionIds[$uid] ?? null,
					$teamId,
					$dataManagerFolder,
					$summary,
				);
				if ($employeeId !== null) {
					$employeeIds[$uid] = $employeeId;
				}
				if ($contact === null) {
					$this->addIncomplete($summary, $uid, 'contact', 'No unambiguous Contacts entry was matched.');
				}
			}

			$contactUidByName = [];
			foreach ($contacts as $uid => $contact) {
				$key = $this->normalize((string)$contact['display_name']);
				if ($key !== '') {
					$contactUidByName[$key][] = $uid;
				}
			}
			foreach ($contacts as $dependentUid => $contact) {
				$managerName = (string)$contact['manager_name'];
				if ($managerName === '') {
					continue;
				}
				$matches = $contactUidByName[$this->normalize($managerName)] ?? [];
				if (count($matches) !== 1) {
					$this->addIncomplete($summary, $dependentUid, 'manager', 'Manager could not be matched unambiguously.');
					continue;
				}
				$managerUid = (string)$matches[0];
				if (!isset($employeeIds[$managerUid], $employeeIds[$dependentUid])) {
					$this->addIncomplete($summary, $dependentUid, 'manager', 'Manager does not have a synchronized employee record.');
					continue;
				}
				$this->ensureRelation(
					$managerUid,
					$dependentUid,
					$employeeIds[$managerUid],
					$employeeIds[$dependentUid],
					$summary,
				);
			}
		} catch (\Throwable $e) {
			$summary['status'] = 'error';
			$summary['warnings'][] = $e->getMessage();
			$this->logger->error('Employees directory synchronization failed', [
				'app' => 'employees',
				'exception' => $e,
			]);
		}

		$summary['finished_at'] = date('c');
		$summary['tracking'] = $this->syncMapper->getStats();
		$this->syncMapper->setState('last_run', (string)$summary['finished_at']);
		$this->syncMapper->setState('last_result', (string)json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

		return $summary;
	}

	/** @return array<string, mixed> */
	public function getStatus(): array {
		$config = [];
		foreach ($this->settingsMapper->GetConfig() as $row) {
			$config[(string)($row['name'] ?? '')] = (string)($row['data'] ?? '');
		}
		$lastRun = $this->syncMapper->getState('last_run');
		$result = json_decode($this->syncMapper->getState('last_result') ?? '', true);

		return [
			'enabled' => ($config['directory_sync_enabled'] ?? '1') === '1',
			'interval_minutes' => 15,
			'last_run' => $lastRun,
			'last_result' => is_array($result) ? $result : null,
			'tracking' => $this->syncMapper->getStats(),
		];
	}

	/** @return list<array<string, mixed>> */
	public function previewContacts(): array {
		$summary = ['incomplete' => [], 'warnings' => []];
		$users = $this->getEnabledUsers(null);
		$contacts = $this->loadContacts($this->getDataManagerUid(), $users, $summary);
		$result = [];
		foreach ($contacts as $uid => $contact) {
			$result[] = [
				'uid' => $uid,
				'display_name' => $contact['display_name'],
				'email' => $contact['email'],
				'department' => implode(' / ', $contact['organization_path']),
				'organization_path' => $contact['organization_path'],
				'position' => $contact['position'],
				'team' => '',
				'manager_name' => $contact['manager_name'],
				'existing_employee' => $this->employeeMapper->findByUserId($uid) !== null,
				'importable' => true,
				'reason' => '',
			];
		}
		usort($result, static fn(array $a, array $b): int => strcasecmp((string)$a['display_name'], (string)$b['display_name']));

		return $result;
	}

	private function getDataManagerUid(): string {
		$uid = trim((string)($this->settingsMapper->GetGestor()[0]['data'] ?? ''));
		if ($uid === '') {
			throw new \RuntimeException('Select the data manager in global Employees settings first.');
		}

		return $uid;
	}

	private function getDataManagerFolder(string $uid): Folder {
		$folder = $this->rootFolder->getUserFolder($uid);
		if (!$folder->nodeExists(self::STORAGE_FOLDER)) {
			throw new \RuntimeException('Employees_storage Team Folder is not available to the selected data manager.');
		}

		return $folder;
	}

	/** @param list<string>|null $onlyUids @return array<string, IUser> */
	private function getEnabledUsers(?array $onlyUids): array {
		$allowed = $onlyUids === null ? null : array_fill_keys(array_map('strval', $onlyUids), true);
		$users = [];
		foreach ($this->userManager->search('') as $user) {
			$uid = $user->getUID();
			if (($allowed !== null && !isset($allowed[$uid])) || (method_exists($user, 'isEnabled') && !$user->isEnabled())) {
				continue;
			}
			$users[$uid] = $user;
		}

		return $users;
	}

	/** @param array<string, IUser> $users @param array<string, mixed> $summary @return array<string, array<string, mixed>> */
	private function loadContacts(string $dataManagerUid, array $users, array &$summary): array {
		$usersByEmail = [];
		$usersByName = [];
		foreach ($users as $uid => $user) {
			$email = $this->normalize((string)($user->getEMailAddress() ?? ''));
			$name = $this->normalize($user->getDisplayName());
			if ($email !== '') { $usersByEmail[$email][] = $uid; }
			if ($name !== '') { $usersByName[$name][] = $uid; }
		}

		$rawContacts = [];
		$addressBooks = $this->cardDavBackend->getAddressBooksForUser('principals/users/' . $dataManagerUid);
		foreach ($addressBooks as $addressBook) {
			$rows = $this->cardDavBackend->getCards((int)$addressBook['id']);
			foreach ($rows as $row) {
				try {
					$rawContacts[] = $this->vCardRowToArray($row);
				} catch (\Throwable $e) {
					$summary['warnings'][] = 'A Contacts card could not be read: ' . $e->getMessage();
				}
			}
		}
		$contacts = [];
		$ambiguousUsers = [];
		foreach ($rawContacts as $raw) {
			$rawUid = $this->contactScalar($raw['UID'] ?? '');
			$email = $this->contactScalar($raw['EMAIL'] ?? '');
			$displayName = $this->contactScalar($raw['FN'] ?? '');
			$matches = [];
			if ($rawUid !== '' && isset($users[$rawUid])) {
				$matches = [$rawUid];
			} elseif ($email !== '') {
				$matches = $usersByEmail[$this->normalize($email)] ?? [];
			} elseif ($displayName !== '') {
				$matches = $usersByName[$this->normalize($displayName)] ?? [];
			}
			if (count($matches) !== 1) {
				if ($displayName !== '' || $email !== '') {
					$this->addIncomplete($summary, $rawUid !== '' ? $rawUid : $displayName, 'contact', 'Contact does not match exactly one enabled Nextcloud user.');
				}
				continue;
			}
			$uid = (string)$matches[0];
			if (isset($ambiguousUsers[$uid])) {
				continue;
			}
			if (isset($contacts[$uid])) {
				$this->addIncomplete($summary, $uid, 'contact', 'Multiple Contacts entries match this user.');
				unset($contacts[$uid]);
				$ambiguousUsers[$uid] = true;
				continue;
			}
			$position = $this->contactScalar($raw['TITLE'] ?? '');
			if ($position === '') {
				$position = $this->contactScalar($raw['ROLE'] ?? '');
			}
			$contacts[$uid] = [
				'display_name' => $displayName !== '' ? $displayName : $users[$uid]->getDisplayName(),
				'email' => $email,
				'organization_path' => $this->contactOrganizationPath($raw['ORG'] ?? []),
				'position' => $position,
				'manager_name' => $this->contactScalar($raw['X-MANAGERSNAME'] ?? ''),
			];
		}

		return $contacts;
	}

	/** @param array<string, mixed> $row @return array<string, mixed> */
	private function vCardRowToArray(array $row): array {
		$cardData = $row['carddata'] ?? '';
		if (is_resource($cardData)) {
			$cardData = stream_get_contents($cardData);
		}
		$vCard = Reader::read((string)$cardData);
		$result = [];
		foreach (['FN', 'EMAIL', 'ORG', 'TITLE', 'ROLE', 'UID', 'X-MANAGERSNAME'] as $propertyName) {
			$values = [];
			foreach ($vCard->select($propertyName) as $property) {
				if ($propertyName === 'ORG' && method_exists($property, 'getParts')) {
					$values[] = array_values(array_filter(array_map(
						static fn(mixed $part): string => trim((string)$part),
						$property->getParts(),
					), static fn(string $part): bool => $part !== ''));
				} else {
					$values[] = trim((string)$property);
				}
			}
			if ($values !== []) {
				$result[$propertyName] = count($values) === 1 ? $values[0] : $values;
			}
		}

		return $result;
	}

	/** @param array<string, IUser> $users @param array<string, mixed> $summary @return array{0:array<string,string>,1:array<string,list<string>>} */
	private function loadNextcloudTeams(array $users, array &$summary): array {
		$teams = [];
		$memberships = [];
		try {
			if (!$this->teamManager->hasTeamSupport()) {
				return [$teams, $memberships];
			}

			// Collectives are backed by Teams/Circles, but their resource provider
			// cannot be queried from cron because it is session-user scoped. Read
			// only the stable Circle ids from the Collectives registry, then use the
			// public Teams API for display names and memberships.
			$collectiveIds = [];
			$qb = $this->db->getQueryBuilder();
			$result = $qb->select('c.circle_unique_id', 'circle.name')
				->from('collectives', 'c')
				->innerJoin('c', 'circles_circle', 'circle', $qb->expr()->eq('circle.unique_id', 'c.circle_unique_id'))
				->where($qb->expr()->orX(
					$qb->expr()->isNull('c.trash_timestamp'),
					$qb->expr()->eq('c.trash_timestamp', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)),
				))
				->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$id = trim((string)($row['circle_unique_id'] ?? ''));
				$name = trim((string)($row['name'] ?? ''));
				if ($id !== '' && $name !== '') {
					$collectiveIds[$id] = $name;
					$teams[$id] = $name;
				}
			}
			$result->closeCursor();

			foreach ($users as $uid => $_user) {
				try {
					$userTeams = $this->teamManager->getTeamsForUser($uid);
				} catch (\Throwable) {
					// A missing Circles federated-user record must not prevent the
					// Collectives catalogue itself from being synchronized.
					continue;
				}
				foreach ($userTeams as $team) {
					$id = trim($team->getId());
					$name = trim($team->getDisplayName());
					if ($id === '' || $name === '') { continue; }
					if (!isset($collectiveIds[$id])) { continue; }
					$teams[$id] = $name;
					$memberships[$uid][] = $id;
				}
			}
		} catch (\Throwable $e) {
			$summary['warnings'][] = 'Nextcloud Teams/Collectives could not be read: ' . $e->getMessage();
		}

		return [$teams, $memberships];
	}

	/** @param array<string, mixed> $summary */
	private function ensureDepartment(string $path, string $name, ?int $parentId, array &$summary): ?int {
		$sourceKey = $this->sourceKey('department', $path);
		$mapping = $this->syncMapper->find('department', self::SOURCE_CONTACTS, $sourceKey);
		if ($mapping !== null) {
			if ((bool)($mapping['suppressed'] ?? false)) { $summary['suppressed']++; return null; }
			$id = (int)($mapping['local_id'] ?? 0);
			if ($id > 0 && $this->departmentMapper->findDepartmentRow($id) !== null) { $summary['unchanged']++; return $id; }
			$this->syncMapper->suppress('department', self::SOURCE_CONTACTS, $sourceKey);
			$summary['suppressed']++;
			return null;
		}
		$before = count($this->departmentMapper->GetAreasList());
		$id = $this->departmentMapper->findOrCreateByNameAndParent($name, $parentId);
		$after = count($this->departmentMapper->GetAreasList());
		$this->syncMapper->bind('department', self::SOURCE_CONTACTS, $sourceKey, $id, null, $path);
		$summary[$after > $before ? 'created' : 'adopted']['departments']++;
		return $id;
	}

	/** @param array<string, mixed> $summary */
	private function ensurePosition(string $name, array &$summary): ?int {
		$sourceKey = $this->sourceKey('position', $this->normalize($name));
		$mapping = $this->syncMapper->find('position', self::SOURCE_CONTACTS, $sourceKey);
		if ($mapping !== null) {
			if ((bool)($mapping['suppressed'] ?? false)) { $summary['suppressed']++; return null; }
			$id = (int)($mapping['local_id'] ?? 0);
			if ($id > 0 && $this->positionMapper->getById($id) !== null) { $summary['unchanged']++; return $id; }
			$this->syncMapper->suppress('position', self::SOURCE_CONTACTS, $sourceKey);
			$summary['suppressed']++;
			return null;
		}
		$before = count($this->positionMapper->GetPositionsList());
		$id = $this->positionMapper->findOrCreateByName($name);
		$after = count($this->positionMapper->GetPositionsList());
		$this->syncMapper->bind('position', self::SOURCE_CONTACTS, $sourceKey, $id, null, $name);
		$summary[$after > $before ? 'created' : 'adopted']['positions']++;
		return $id;
	}

	/** @param array<string, mixed> $summary */
	private function ensureTeam(string $sourceType, string $sourceKey, string $name, array &$summary): ?int {
		$mapping = $this->syncMapper->find('team', $sourceType, $sourceKey);
		if ($mapping !== null) {
			if ((bool)($mapping['suppressed'] ?? false)) { $summary['suppressed']++; return null; }
			$id = (int)($mapping['local_id'] ?? 0);
			if ($id > 0 && $this->teamMapper->getById((string)$id) !== null) { $summary['unchanged']++; return $id; }
			$this->syncMapper->suppress('team', $sourceType, $sourceKey);
			$summary['suppressed']++;
			return null;
		}
		$before = count($this->teamMapper->GetTeamsList());
		$id = $this->teamMapper->findOrCreateByName($name);
		$after = count($this->teamMapper->GetTeamsList());
		$this->syncMapper->bind('team', $sourceType, $sourceKey, $id, null, $name);
		$summary[$after > $before ? 'created' : 'adopted']['teams']++;
		return $id;
	}

	/** @param array<string, mixed>|null $contact @param array<string, mixed> $summary */
	private function ensureEmployee(IUser $user, ?array $contact, ?int $departmentId, ?int $positionId, ?int $teamId, Folder $dataManagerFolder, array &$summary): ?int {
		$uid = $user->getUID();
		$sourceKey = $this->sourceKey('employee', $uid);
		$mapping = $this->syncMapper->find('employee', self::SOURCE_NEXTCLOUD, $sourceKey);
		if ($mapping !== null) {
			if ((bool)($mapping['suppressed'] ?? false)) { $summary['suppressed']++; return null; }
			$id = (int)($mapping['local_id'] ?? 0);
			$row = $this->employeeMapper->findByUserId($uid);
			if ($id > 0 && $row !== null && (int)$row['id_employees'] === $id) { $summary['unchanged']++; return $id; }
			$this->syncMapper->suppress('employee', self::SOURCE_NEXTCLOUD, $sourceKey);
			$summary['suppressed']++;
			return null;
		}

		$existing = $this->employeeMapper->findByUserId($uid);
		if ($existing !== null) {
			$id = (int)$existing['id_employees'];
			$this->syncMapper->bind('employee', self::SOURCE_NEXTCLOUD, $sourceKey, $id, null, $uid);
			$summary['adopted']['employees']++;
			return $id;
		}

		$email = trim((string)($contact['email'] ?? $user->getEMailAddress() ?? ''));
		$this->db->beginTransaction();
		try {
			$id = $this->employeeMapper->createBaseRecord($uid, $email !== '' ? $email : null);
			$this->employeeMapper->updateDirectoryProfile($id, $email !== '' ? $email : null, $departmentId, $positionId, $teamId, null);
			$absence = new Absence();
			$absence->setIdEmployee($id);
			$absence->setTimestamp(new \DateTime());
			$this->absenceMapper->insert($absence);
			$savings = new UserSavings();
			$savings->setIdUser($id);
			$savings->setIdPermission('0');
			$savings->setState('0');
			$savings->setLastModified(date('Y-m-d'));
			$this->userSavingsMapper->insert($savings);
			$this->syncMapper->bind('employee', self::SOURCE_NEXTCLOUD, $sourceKey, $id, null, $uid);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		try {
			$group = $this->groupManager->get('employees') ?? $this->groupManager->createGroup('employees');
			if ($group !== null && !$group->inGroup($user)) { $group->addUser($user); }
			$base = self::STORAGE_FOLDER . '/' . $uid . ' - ' . mb_strtoupper($user->getDisplayName(), 'UTF-8');
			foreach (['', '/Training', '/Official documents', '/Identity documents', '/Memorandums', '/Supporting documents'] as $suffix) {
				if (!$dataManagerFolder->nodeExists($base . $suffix)) { $dataManagerFolder->newFolder($base . $suffix); }
			}
		} catch (\Throwable $e) {
			$summary['warnings'][] = $uid . ': ' . $e->getMessage();
		}
		$summary['created']['employees']++;
		return $id;
	}

	/** @param array<string, mixed> $summary */
	private function ensureRelation(string $managerUid, string $dependentUid, int $managerId, int $dependentId, array &$summary): void {
		$sourceKey = $this->sourceKey('relation', $managerUid, $dependentUid);
		$mapping = $this->syncMapper->find('org_relation', self::SOURCE_CONTACTS, $sourceKey);
		if ($mapping !== null) {
			if ((bool)($mapping['suppressed'] ?? false)) { $summary['suppressed']++; return; }
			if ($this->orgChartMapper->ExisteRelacion($managerId, $dependentId)) { $summary['unchanged']++; return; }
			$this->syncMapper->suppress('org_relation', self::SOURCE_CONTACTS, $sourceKey);
			$summary['suppressed']++;
			return;
		}
		$exists = $this->orgChartMapper->ExisteRelacion($managerId, $dependentId);
		if (!$exists) { $this->orgChartMapper->CrearRelacion($managerId, $dependentId); }
		$this->syncMapper->bind('org_relation', self::SOURCE_CONTACTS, $sourceKey, $managerId, $dependentId, $managerUid . ' > ' . $dependentUid);
		$summary[$exists ? 'adopted' : 'created']['relations']++;
	}

	/** @return list<string> */
	private function contactOrganizationPath(mixed $value): array {
		$values = $this->flattenContactValues($value);
		$parts = [];
		foreach ($values as $entry) {
			foreach (preg_split('/(?<!\\\\);/', $entry) ?: [] as $part) {
				$part = trim(str_replace('\\;', ';', $part));
				if ($part !== '' && !in_array($part, $parts, true)) { $parts[] = $part; }
			}
		}
		return $parts;
	}

	/** @return list<string> */
	private function flattenContactValues(mixed $value): array {
		if (is_scalar($value)) { return [trim((string)$value)]; }
		if (!is_array($value)) { return []; }
		if (array_key_exists('value', $value)) { return $this->flattenContactValues($value['value']); }
		$result = [];
		foreach ($value as $item) { array_push($result, ...$this->flattenContactValues($item)); }
		return array_values(array_filter($result, static fn(string $item): bool => $item !== ''));
	}

	private function contactScalar(mixed $value): string {
		return $this->flattenContactValues($value)[0] ?? '';
	}

	private function normalize(string $value): string {
		return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value), 'UTF-8');
	}

	private function sourceKey(string ...$parts): string {
		return hash('sha256', implode("\x1f", array_map([$this, 'normalize'], $parts)));
	}

	/** @param array<string, mixed> $summary */
	private function addIncomplete(array &$summary, string $uid, string $field, string $reason): void {
		if (count($summary['incomplete']) >= 200) { return; }
		$summary['incomplete'][] = ['uid' => $uid, 'field' => $field, 'reason' => $reason];
	}
}
