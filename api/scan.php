<?php
/**
 * App Manager — Scanner
 *
 * Crawls the configured root directory (defaults to C:/laragon/www), detects:
 *   - Stack: composer.json (PHP) + package.json (Node)
 *   - SCM: .git (remote, branch, last commit, status)
 *
 * Upserts everything into the app_manager MySQL database.
 *
 * Usage:
 *   GET  /api/scan.php              — scan all projects in configured root
 *   GET  /api/scan.php?path=<dir>   — scan a single project
 */

require_once __DIR__ . '/../config.php';

// ——— Configuration ———
// Root directory is read from DB config; defaults to C:/laragon/www if not set.
$wwwRoot = db_config('root_path') ?: 'C:/laragon/www';

// Directories to skip during crawl
$skipDirs = ['dist', 'node_modules', 'vendor', '.git', 'api', 'styles', 'output', 'prototype', 'bower_components', 'pengumuman', 'fonts'];

// Root of legacy AngularJS SPA apps that rewrite all traffic into an /app/ subfolder.
// Those folders have no composer.json / package.json / .git at their root, so the
// generic crawler would skip them. We explicitly detect them by their .htaccess
// rewrite pattern ("RewriteRule ^$ ./app/ [L]") or a readable app/index.html.
$legacySpaRoots = ['spmblive_2026_08'];

// Resolved at runtime: prefer the bundled Laragon git, fall back to PATH.
function resolve_git_bin(): string
{
    $candidates = [
        'C:\\laragon\\bin\\git\\bin\\git.exe',
        'C:/laragon/bin/git/bin/git.exe',
        'C:\\laragon\\bin\\git\\mingw64\\bin\\git.exe',
        'C:\\Program Files\\Git\\bin\\git.exe',
    ];
    foreach ($candidates as $c) {
        if (is_file($c)) return $c;
    }
    // Fallback: rely on PATH (proc_open will find it).
    return 'git';
}

// ——— Main ———
header('Content-Type: application/json; charset=utf-8');

// Support both web (GET) and CLI (argv)
$targetPath = $_GET['path'] ?? null;
if ($targetPath === null && PHP_SAPI === 'cli') {
    $opts = getopt('', ['path:']);
    $targetPath = $opts['path'] ?? null;
}

if ($targetPath) {
    // Single project scan
    $absPath = realpath($targetPath) ?: $targetPath;
    if (!is_dir($absPath)) {
        json_response(['error' => 'Directory not found', 'path' => $targetPath], 404);
    }
    $results = [scan_project($absPath)];
} else {
    // Full crawl of www/
    $results = crawl_www($wwwRoot);
}

// Filter out nulls (directories that had no composer.json or package.json)
$results = array_values(array_filter($results, fn($r) => $r !== null));

// Persist to DB
$pdo = db();
$saved = 0;
$skipped = 0;
$errors = [];
foreach ($results as $project) {
    if (save_project($project)) {
        $saved++;
    } else {
        $skipped++;
        $errors[] = $project['name'];
    }
}

// Aggregate latest scan time for the whole run
$latestScan = $pdo->query('SELECT MAX(scanned_at) AS last FROM app_scan_log')->fetch();
$latestScanAt = $latestScan && $latestScan['last'] ? $latestScan['last'] : date('Y-m-d H:i:s');

json_response([
    'scanned'      => count($results),
    'saved'        => $saved,
    'skipped'      => $skipped,
    'errors'       => $errors,
    'projects'     => $results,
    'php_version'  => PHP_VERSION,
    'root_path'    => $wwwRoot,
    'git_bin'      => resolve_git_bin(),
    'last_scan_at' => $latestScanAt,
]);

// ——————————————————————————————————————————————
// Crawl
// ——————————————————————————————————————————————

function crawl_www(string $root): array
{
    global $skipDirs;

    $results = [];
    $items = scandir($root);
    if ($items === false) {
        return $results;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $skipDirs)) continue;

        $fullPath = $root . '/' . $item;
        if (!is_dir($fullPath)) continue;

        $project = scan_project($fullPath);
        if ($project !== null) {
            $results[] = $project;
        }
    }

    return $results;
}

// ——————————————————————————————————————————————
// Project Scanner
// ——————————————————————————————————————————————

function scan_project(string $path): ?array
{
    $composer = read_json_file($path . '/composer.json');
    $package  = read_json_file($path . '/package.json');
    $hasGit   = is_dir($path . '/.git');
    $isLegacy = is_legacy_spa($path);

    // Skip directories with no detectable stack
    if ($composer === null && $package === null && !$hasGit && !$isLegacy) {
        return null;
    }

    $project = [
        'name'  => basename($path),
        'path'  => str_replace('\\', '/', $path),
        'stacks'=> [],
        'scm'   => null,
    ];

    // Parse PHP stack
    if ($composer !== null) {
        $stack = ['type' => 'php'];
        $stack['framework'] = detect_php_framework($composer);
        $stack['language_version'] = $composer['require']['php'] ?? null;
        $stack['dependencies'] = extract_key_deps($composer['require'] ?? [], 'php');
        $project['stacks'][] = $stack;
    }

    // Parse Node stack
    if ($package !== null) {
        $stack = ['type' => 'node'];
        $stack['framework'] = detect_node_framework($package);
        $stack['language_version'] = $package['engines']['node'] ?? null;
        $allDeps = array_merge(
            $package['dependencies'] ?? [],
            $package['devDependencies'] ?? []
        );
        $stack['dependencies'] = extract_key_deps($allDeps, 'node');
        $project['stacks'][] = $stack;
    }

    // Legacy AngularJS folder with a rewrite to /app/ — fake a PHP-only stack marker
    if ($isLegacy && $project['stacks'] === []) {
        $project['stacks'] = [
            [
                'type'              => 'php',
                'framework'         => 'AngularJS',
                'language_version'  => null,
                'dependencies'      => ['legacy_spa' => 'htaccess /app/'],
            ],
        ];
    }

    // Parse Git
    if ($hasGit) {
        $project['scm'] = parse_git($path);
    } elseif ($isLegacy) {
        // Legacy scans may still find a .git deeper inside the folder tree
        // (e.g. <project>/SCM/.git). Look for the nearest one, max depth 2.
        $nestedGit = find_nested_git($path);
        if ($nestedGit !== null) {
            $project['scm'] = parse_git($nestedGit);
        }
    }

    return $project;
}

/**
 * Detect a legacy AngularJS-project root such as the <project>/app/ rewrite.
 * Cheap heuristic: folder is in $legacySpaRoots, OR root .htaccess contains a
 * "RewriteRule ^$ ./app/" style rule, OR app/index.html exists.
 */
function is_legacy_spa(string $path): bool
{
    global $legacySpaRoots;
    if (in_array(basename($path), $legacySpaRoots, true)) {
        return true;
    }

    if (is_file($path . '/.htaccess')) {
        $ht = @file_get_contents($path . '/.htaccess');
        if ($ht !== false && preg_match('/RewriteRule\s+\^\\$\s+\.\/app\//i', $ht)) {
            return true;
        }
    }

    if (is_file($path . '/app/index.html')) {
        $html = @file_get_contents($path . '/app/index.html');
        if ($html !== false && stripos($html, 'angular') !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Look for a .git directory inside subfolders (max depth 2).
 * Returns the directory containing .git, or null.
 */
function find_nested_git(string $root): ?string
{
    foreach (['SCM', 'git', '.git-inner'] as $sub) {
        if (is_dir($root . '/' . $sub . '/.git')) {
            return $root . '/' . $sub;
        }
    }
    // Second level: <root>/<dir>/<subdir>/.git
    foreach (scandir($root) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $sub = $root . '/' . $item;
        if (!is_dir($sub)) continue;
        foreach (['SCM', 'git'] as $inner) {
            if (is_dir($sub . '/' . $inner . '/.git')) {
                return $sub . '/' . $inner;
            }
        }
    }
    return null;
}

// ——————————————————————————————————————————————
// Framework Detection
// ——————————————————————————————————————————————

function detect_php_framework(array $composer): ?string
{
    $require = array_keys($composer['require'] ?? []);
    if (in_array('laravel/framework', $require)) return 'Laravel';
    if (in_array('symfony/http-kernel', $require) || in_array('symfony/framework-bundle', $require)) return 'Symfony';
    if (in_array('slim/slim', $require)) return 'Slim';
    if (in_array('cakephp/cakephp', $require)) return 'CakePHP';
    if (in_array('yiisoft/yii2', $require)) return 'Yii2';
    if (in_array('codeigniter4/framework', $require)) return 'CodeIgniter';
    return 'PHP';
}

function detect_node_framework(array $package): ?string
{
    $allDeps = array_keys(array_merge(
        $package['dependencies'] ?? [],
        $package['devDependencies'] ?? []
    ));
    if (in_array('vue', $allDeps)) return 'Vue';
    if (in_array('react', $allDeps)) return 'React';
    if (in_array('next', $allDeps)) return 'Next.js';
    if (in_array('nuxt', $allDeps)) return 'Nuxt';
    if (in_array('svelte', $allDeps)) return 'Svelte';
    if (in_array('angular', $allDeps)) return 'Angular';
    if (in_array('astro', $allDeps)) return 'Astro';
    if (in_array('express', $allDeps)) return 'Express';
    if (in_array('nestjs', $allDeps) || in_array('@nestjs/core', $allDeps)) return 'NestJS';
    return 'Node';
}

// ——————————————————————————————————————————————
// Key Dependency Extraction
// ——————————————————————————————————————————————

function extract_key_deps(array $require, string $type): array
{
    $keyDeps = [];

    if ($type === 'php') {
        $keywords = [
            'laravel/framework', 'symfony/', 'inertiajs/inertia-laravel',
            'spatie/', 'livewire/livewire', 'filament/filament',
            'barryvdh/laravel-debugbar', 'guzzlehttp/guzzle',
            'phpunit/phpunit', 'pestphp/pest',
            'doctrine/', 'eloquent/', 'redis', 'predis/',
        ];
    } else {
        $keywords = [
            'vue', 'react', 'react-dom', '@inertiajs/vue3', '@inertiajs/react',
            'tailwindcss', 'vite', 'vitest', 'typescript',
            'pinia', 'vuex', 'zustand', 'redux',
            'axios', 'express', 'next', 'nuxt', 'svelte',
            'eslint', 'prettier', 'playwright', 'cypress',
        ];
    }

    foreach ($require as $pkg => $version) {
        foreach ($keywords as $kw) {
            if (str_starts_with($pkg, rtrim($kw, '/'))) {
                // Strip caret/tilde/operators for clean display
                $cleanVersion = preg_replace('/^[\^~>=<!\s|]+/', '', $version);
                $keyDeps[$pkg] = $cleanVersion;
                break;
            }
        }
    }

    // If nothing matched keywords, include top 5 by package name length (heuristic for "real" deps)
    if (empty($keyDeps)) {
        $filtered = array_filter($require, fn($v, $k) => !str_starts_with($k, 'ext-') && $k !== 'php', ARRAY_FILTER_USE_BOTH);
        $keyDeps = array_slice($filtered, 0, 5, true);
    }

    return $keyDeps;
}

// ——————————————————————————————————————————————
// Git Parser
// ——————————————————————————————————————————————

function parse_git(string $path): ?array
{
    $scm = [
        'remote_url'          => null,
        'branch'              => null,
        'last_commit_hash'    => null,
        'last_commit_message' => null,
        'last_commit_author'  => null,
        'last_commit_date'    => null,
        'status'              => 'no_git',
        'changed_files_count' => 0,
        'untracked_files_count' => 0,
        'changed_files'       => [],
    ];

    // Use git via shell — prefer the Laragon-bundled path, fall back to PATH.
    $git = resolve_git_bin();

    // Remote URL
    $remote = shell_exec_cwd($path, escape_cmd([$git, 'remote', 'get-url', 'origin']));
    if ($remote !== null && !str_contains($remote, 'not a git repository')) {
        $scm['remote_url'] = trim($remote);
    } else {
        return $scm; // not a git repo
    }

    // Branch
    $branch = shell_exec_cwd($path, escape_cmd([$git, 'rev-parse', '--abbrev-ref', 'HEAD']));
    $scm['branch'] = trim($branch ?? '');

    // Last commit — use a separator unlikely to appear in commit messages.
    $log = shell_exec_cwd($path, escape_cmd([$git, 'log', '-1', '--format=%H::GSPLIT::%s::GSPLIT::%an::GSPLIT::%aI']));
    if ($log && !str_contains($log, 'fatal')) {
        $parts = explode('::GSPLIT::', trim($log, "\n\r"), 4);
        $scm['last_commit_hash']    = $parts[0] ?? null;
        $scm['last_commit_message'] = $parts[1] ?? null;
        $scm['last_commit_author']  = $parts[2] ?? null;
        $scm['last_commit_date']    = $parts[3] ?? null;
    }

    // Status: check for uncommitted changes
    $statusOutput = shell_exec_cwd($path, escape_cmd([$git, 'status', '--porcelain']));
    if ($statusOutput !== null && trim($statusOutput) !== '') {
        $scm['status'] = 'dirty';
        $lines = array_filter(explode("\n", trim($statusOutput)));
        $scm['changed_files_count'] = 0;
        $scm['untracked_files_count'] = 0;

        foreach ($lines as $line) {
            if (strlen($line) < 3) continue;
            $st = $line[0] . $line[1];
            $file = trim(substr($line, 3));
            $fileStatus = 'M';

            if (str_contains($st, '?')) {
                $scm['untracked_files_count']++;
                $fileStatus = 'A'; // untracked treated as "new"
            } else {
                $scm['changed_files_count']++;
                if ($st[0] === 'D' || $st[1] === 'D') $fileStatus = 'D';
                elseif ($st[0] === 'A' || $st[1] === 'A') $fileStatus = 'A';
            }

            $scm['changed_files'][] = [
                'file_path' => $file,
                'status'    => $fileStatus,
            ];
        }
    } else {
        $scm['status'] = 'clean';
    }

    return $scm;
}

/**
 * Build a shell-safe command string from an argument list.
 * Quotes each argument so paths with spaces (e.g. "Program Files")
 * survive the round-trip through cmd.exe on Windows.
 */
function escape_cmd(array $parts): string
{
    $out = [];
    foreach ($parts as $p) {
        // cmd.exe needs double quotes escaped as "" inside a quoted arg.
        $escaped = '"' . str_replace('"', '""', (string)$p) . '"';
        $out[] = $escaped;
    }
    // Append stderr redirect so callers can still detect fatal git errors.
    return implode(' ', $out) . ' 2>&1';
}

/**
 * Execute a shell command in a specific working directory.
 * Returns trimmed stdout, or null on failure.
 */
function shell_exec_cwd(string $cwd, string $command): ?string
{
    $descriptorspec = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes, $cwd);
    if (!is_resource($process)) {
        return null;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return $stdout !== false ? trim($stdout) : null;
}

// ——————————————————————————————————————————————————————
// Database Persistence / save_project — unchanged otherwise
// ——————————————————————————————————————————————————————

function save_project(array $project): bool
{
    try {
        $pdo = db();
        $pdo->beginTransaction();

        // Upsert app
        $stmt = $pdo->prepare('
            INSERT INTO apps (name, path, updated_at)
            VALUES (:name, :path, NOW())
            ON DUPLICATE KEY UPDATE
                path = VALUES(path),
                updated_at = NOW()
        ');
        $stmt->execute([
            'name' => $project['name'],
            'path' => $project['path'],
        ]);

        // Get app_id (new or existing)
        $appId = $pdo->lastInsertId();
        if ($appId == 0) {
            $appId = $pdo->query("SELECT id FROM apps WHERE name = " . $pdo->quote($project['name']))->fetchColumn();
        }

        // Save stacks — clear and re-insert
        $pdo->prepare('DELETE FROM app_stack WHERE app_id = ?')->execute([$appId]);

        $stackStmt = $pdo->prepare('
            INSERT INTO app_stack (app_id, type, framework, language_version, dependencies)
            VALUES (:app_id, :type, :framework, :language_version, :dependencies)
        ');
        foreach ($project['stacks'] as $stack) {
            $stackStmt->execute([
                'app_id'           => $appId,
                'type'             => $stack['type'],
                'framework'        => $stack['framework'] ?? null,
                'language_version' => $stack['language_version'] ?? null,
                'dependencies'     => !empty($stack['dependencies']) ? json_encode($stack['dependencies']) : null,
            ]);
        }

        // Save SCM
        if ($project['scm'] !== null) {
            $scm = $project['scm'];

            $scmStmt = $pdo->prepare('
                INSERT INTO app_scm (app_id, remote_url, branch, last_commit_hash, 
                    last_commit_message, last_commit_author, last_commit_date,
                    status, changed_files_count, untracked_files_count)
                VALUES (:app_id, :remote_url, :branch, :hash,
                    :message, :author, :date,
                    :status, :changed, :untracked)
                ON DUPLICATE KEY UPDATE
                    remote_url = VALUES(remote_url),
                    branch = VALUES(branch),
                    last_commit_hash = VALUES(last_commit_hash),
                    last_commit_message = VALUES(last_commit_message),
                    last_commit_author = VALUES(last_commit_author),
                    last_commit_date = VALUES(last_commit_date),
                    status = VALUES(status),
                    changed_files_count = VALUES(changed_files_count),
                    untracked_files_count = VALUES(untracked_files_count),
                    updated_at = NOW()
            ');
            $scmStmt->execute([
                'app_id'    => $appId,
                'remote_url'=> $scm['remote_url'],
                'branch'    => $scm['branch'],
                'hash'      => $scm['last_commit_hash'],
                'message'   => $scm['last_commit_message'],
                'author'    => $scm['last_commit_author'],
                'date'      => $scm['last_commit_date'],
                'status'    => $scm['status'],
                'changed'   => $scm['changed_files_count'],
                'untracked' => $scm['untracked_files_count'],
            ]);

            // Get scm_id
            $scmId = $pdo->query("SELECT id FROM app_scm WHERE app_id = " . (int)$appId)->fetchColumn();

            // Clear and re-insert changed files
            $pdo->prepare('DELETE FROM app_changed_files WHERE scm_id = ?')->execute([$scmId]);

            if (!empty($scm['changed_files'])) {
                $fileStmt = $pdo->prepare('
                    INSERT INTO app_changed_files (scm_id, file_path, status)
                    VALUES (:scm_id, :file_path, :status)
                ');
                foreach ($scm['changed_files'] as $f) {
                    $fileStmt->execute([
                        'scm_id'    => $scmId,
                        'file_path' => $f['file_path'],
                        'status'    => $f['status'],
                    ]);
                }
            }
        }

        // Note: app_services is NOT touched by the scanner — it's manual-entry only

        // Record scan timestamp for the dashboard "last scan" stat
        $src = (PHP_SAPI === 'cli') ? 'cli' : 'manual';
        $pdo->prepare('
            INSERT INTO app_scan_log (app_id, scanned_at, source)
            VALUES (:app_id, NOW(), :source)
            ON DUPLICATE KEY UPDATE scanned_at = NOW(), source = VALUES(source)
        ')->execute(['app_id' => $appId, 'source' => $src]);

        $pdo->commit();
        return true;

    } catch (\Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("App Manager: Failed to save '{$project['name']}': " . $e->getMessage());
        return false;
    }
}
