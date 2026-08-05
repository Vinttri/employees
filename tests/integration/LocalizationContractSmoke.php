<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$load = static function (string $lang) use ($root): array {
	$path = $root . '/l10n/' . $lang . '.json';
	$data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
	if (!isset($data['translations']) || !is_array($data['translations'])) {
		throw new RuntimeException("Missing translations in {$path}");
	}
	return $data['translations'];
};

$en = $load('en');
$ru = $load('ru');
$errors = [];

if (($en['Employees'] ?? null) !== 'Employees') {
	$errors[] = 'English Employees title is missing';
}
if (($ru['Employees'] ?? null) !== 'Сотрудники') {
	$errors[] = 'Russian Employees title is missing';
}
if (array_keys($en) !== array_keys($ru)) {
	$errors[] = 'English and Russian catalogue key order/coverage differs';
}

$placeholderPattern = '/(?:\{[A-Za-z0-9_]+\}|%(?:\d+\$)?[bcdeEfFgGosuxX])/u';
$translated = 0;
foreach ($en as $key => $english) {
	$russian = $ru[$key] ?? null;
	if (!is_string($english) || !is_string($russian) || $russian === '') {
		$errors[] = "Invalid translation value for {$key}";
		continue;
	}
	preg_match_all($placeholderPattern, $english, $enMatches);
	preg_match_all($placeholderPattern, $russian, $ruMatches);
	sort($enMatches[0]);
	sort($ruMatches[0]);
	if ($enMatches[0] !== $ruMatches[0]) {
		$errors[] = "Placeholder mismatch for {$key}";
	}
	if ($russian !== $english && preg_match('/[А-Яа-яЁё]/u', $russian)) {
		$translated++;
	}
	if (stripos($russian, 'aonius') !== false) {
		$errors[] = "Forbidden branding in {$key}";
	}
}

if ($translated < (int)(count($en) * 0.85)) {
	$errors[] = "Russian coverage is too low: {$translated}/" . count($en);
}

$info = (string)file_get_contents($root . '/appinfo/info.xml');
if (!str_contains($info, '<name>Employees</name>')) {
	$errors[] = 'info.xml app/navigation name is not Employees';
}
if (!str_contains($info, '<version>2.5.13</version>')) {
	$errors[] = 'info.xml version is not 2.5.13';
}

foreach (['en', 'ru'] as $language) {
	$runtimePath = $root . '/l10n/' . $language . '.js';
	$runtime = is_file($runtimePath) ? (string)file_get_contents($runtimePath) : '';
	if (!str_starts_with($runtime, 'OC.L10N.register(')
		|| !str_contains($runtime, '"empleados"')) {
		$errors[] = "Missing Nextcloud runtime catalogue for {$language}";
	}
}

$main = (string)file_get_contents($root . '/src/main.js');
if (str_contains($main, 'loadTranslations(')) {
	$errors[] = 'Frontend still depends on direct JSON catalogue loading';
}

if ($errors !== []) {
	fwrite(STDERR, implode("\n", array_slice($errors, 0, 40)) . "\n");
	exit(1);
}

echo "LOCALIZATION_CONTRACT_OK keys=" . count($en) . " russian={$translated}" . PHP_EOL;
