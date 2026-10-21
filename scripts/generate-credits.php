<?php

/**
 * Builds resources/credits/credits.json - the data behind the /credits (Copyright & Usage) page.
 *
 * Combines:
 *  - resources/credits/manual.json  (hand-maintained: CDN libraries, fonts, icons, data & services)
 *  - composer.lock                   (PHP packages the site runs on)
 *  - package.json + package-lock.json (front-end build tools)
 *
 * Plain PHP with no dependencies, so the "Update Credits" GitHub Action can run it without installing anything.
 * The output has no timestamp, so it only changes when the credits actually change.
 *
 * Usage: php scripts/generate-credits.php [--check]
 *   --check  exit 1 (without writing) if credits.json is out of date
 */

$root = dirname(__DIR__);
$outputPath = "{$root}/resources/credits/credits.json";

function readJson(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }

    $data = json_decode(file_get_contents($path), true);
    if (! is_array($data)) {
        fwrite(STDERR, "Invalid JSON: {$path}\n");
        exit(1);
    }

    return $data;
}

function licenseText($license): string
{
    $license = is_array($license) ? implode(' OR ', $license) : (string) $license;

    return $license !== '' ? $license : 'See project';
}

// Package source/homepage as a browsable https URL
function projectUrl(?string $homepage, ?string $source): ?string
{
    $url = $homepage ?: $source;
    if (! $url) {
        return null;
    }

    $url = preg_replace('#^git\+#', '', $url);
    $url = preg_replace('#^git://#', 'https://', $url);

    return preg_replace('#\.git$#', '', $url);
}

$manual = readJson("{$root}/resources/credits/manual.json");
$sections = [];

foreach ($manual['sections'] ?? [] as $section) {
    $sections[] = [
        'title'       => $section['title'],
        'description' => $section['description'] ?? '',
        'items'       => array_values($section['items'] ?? []),
    ];
}

### PHP packages (composer.lock - production packages only)
$composerJson = readJson("{$root}/composer.json");
$composerLock = readJson("{$root}/composer.lock");
$direct = array_keys($composerJson['require'] ?? []);

$phpItems = [];
foreach ($composerLock['packages'] ?? [] as $package) {
    $phpItems[] = [
        'name'    => $package['name'],
        'version' => ltrim($package['version'] ?? '', 'v'),
        'license' => licenseText($package['license'] ?? ''),
        'url'     => projectUrl($package['homepage'] ?? null, $package['source']['url'] ?? null),
        'usage'   => $package['description'] ?? '',
        'direct'  => in_array($package['name'], $direct, true),
    ];
}

// Packages OzBays installs directly first, then the libraries they depend on - each alphabetical
usort($phpItems, fn ($a, $b) => [$b['direct'], $a['name']] <=> [$a['direct'], $b['name']]);

$sections[] = [
    'title'       => 'PHP Packages',
    'description' => 'The server-side packages OzBays runs on (from composer.lock). Packages marked "direct" are installed by OzBays; the rest are libraries they depend on.',
    'items'       => $phpItems,
];

### Front-end build tools (package.json direct dependencies, versions & licenses from package-lock.json)
$packageJson = readJson("{$root}/package.json");
$packageLock = readJson("{$root}/package-lock.json");

$npmItems = [];
foreach (array_keys(($packageJson['dependencies'] ?? []) + ($packageJson['devDependencies'] ?? [])) as $name) {
    $locked = $packageLock['packages']["node_modules/{$name}"] ?? [];

    $npmItems[] = [
        'name'    => $name,
        'version' => $locked['version'] ?? '',
        'license' => licenseText($locked['license'] ?? ''),
        'url'     => "https://www.npmjs.com/package/{$name}",
        'usage'   => 'Front-end build tooling',
        'direct'  => true,
    ];
}
usort($npmItems, fn ($a, $b) => $a['name'] <=> $b['name']);

$sections[] = [
    'title'       => 'Build Tools',
    'description' => 'Used to build the website\'s assets (from package.json).',
    'items'       => $npmItems,
];

$json = json_encode(['sections' => $sections], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

$current = is_file($outputPath) ? file_get_contents($outputPath) : null;

if (in_array('--check', $argv, true)) {
    if ($current !== $json) {
        fwrite(STDERR, "resources/credits/credits.json is out of date - run: php scripts/generate-credits.php\n");
        exit(1);
    }

    echo "Credits are up to date.\n";
    exit(0);
}

if ($current === $json) {
    echo "Credits unchanged.\n";
    exit(0);
}

file_put_contents($outputPath, $json);
$count = array_sum(array_map(fn ($s) => count($s['items']), $sections));
echo "Wrote resources/credits/credits.json ({$count} entries in ".count($sections)." sections).\n";
