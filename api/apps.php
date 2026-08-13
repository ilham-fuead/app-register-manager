<?php
/**
 * App Manager — API
 * 
 * REST endpoints consumed by the Vue 3 dashboard.
 * 
 * GET  /api/apps.php              — list all apps
 * GET  /api/apps.php?name=<name>  — single app detail
 * GET  /api/apps.php?status=dirty — filter by SCM status
 * GET  /api/apps.php?stack=php    — filter by stack type
 * GET  /api/apps.php?search=<q>   — text search across name, framework, branch
 * 
 * POST /api/apps.php              — manual add (JSON body)
 * POST /api/apps.php?name=<name>  — add a catatan/ulasan note (JSON body: { "note": "..." })
 * PUT  /api/apps.php?name=<name>  — edit app profile (JSON body)
 * DELETE /api/apps.php?name=<name>&note_id=<id> — delete a specific note
 */

require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get();
        break;
    case 'POST':
        handle_post();
        break;
    case 'PUT':
        handle_put();
        break;
    case 'DELETE':
        handle_delete();
        break;
    default:
        json_response(['error' => 'Method not allowed'], 405);
}

// ——————————————————————————————————————————————
// GET — List / Single app
// ——————————————————————————————————————————————

function handle_get(): void
{
    $pdo = db();

    // Single app by name
    if (!empty($_GET['name'])) {
        $app = get_app_by_name($pdo, $_GET['name']);
        if ($app === null) {
            json_response(['error' => 'App not found'], 404);
        }
        json_response($app);
    }

    // Build filtered query
    $where = [];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = 'scm.status = :status';
        $params['status'] = $_GET['status'];
    }

    if (!empty($_GET['stack'])) {
        $where[] = 'EXISTS (SELECT 1 FROM app_stack ast WHERE ast.app_id = a.id AND ast.type = :stack)';
        $params['stack'] = $_GET['stack'];
    }

    if (!empty($_GET['search'])) {
        $where[] = '(a.name LIKE :search1 OR stk.framework LIKE :search2 OR scm.branch LIKE :search3)';
        $params['search1'] = '%' . $_GET['search'] . '%';
        $params['search2'] = '%' . $_GET['search'] . '%';
        $params['search3'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = '';
    if (!empty($where)) {
        $whereClause = 'WHERE ' . implode(' AND ', $where);
    }

    $sql = "
        SELECT 
            a.id, a.name, a.path, a.notes, a.is_active, a.is_pinned, a.pinned_at,
            a.created_at, a.updated_at,
            scm.remote_url, scm.branch, scm.last_commit_hash,
            scm.last_commit_message, scm.last_commit_author, scm.last_commit_date,
            scm.status AS scm_status, scm.changed_files_count, scm.untracked_files_count,
            sl.scanned_at AS last_scan_at
        FROM apps a
        LEFT JOIN app_scm scm ON scm.app_id = a.id
        LEFT JOIN app_stack stk ON stk.app_id = a.id
        LEFT JOIN app_scan_log sl ON sl.app_id = a.id
        $whereClause
        GROUP BY a.id
        ORDER BY a.is_pinned DESC, a.is_active DESC, a.pinned_at DESC, scm.last_commit_date DESC, a.updated_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $apps = $stmt->fetchAll();

    // Latest scan across all apps — used by the dashboard "Diimbas" stat
    $lastScanRow = $pdo->query('SELECT MAX(scanned_at) AS last FROM app_scan_log')->fetch();
    $lastScanAt = $lastScanRow && $lastScanRow['last'] ? $lastScanRow['last'] : null;

    // Enrich each app with stacks, changed files, and services
    foreach ($apps as &$app) {
        $app['stacks'] = get_app_stacks($pdo, $app['id']);
        $app['services'] = get_app_services($pdo, $app['id']);
        $app['notes'] = get_app_notes($pdo, $app['id']);
        
        // Flatten stack tags for filtering
        $app['stack_tags'] = [];
        foreach ($app['stacks'] as $s) {
            $app['stack_tags'][] = $s['type'];
            if ($s['framework']) $app['stack_tags'][] = strtolower($s['framework']);
        }
        $app['stack_tags'] = array_unique($app['stack_tags']);

        // Get changed files if dirty
        if ($app['scm_status'] === 'dirty') {
            $app['changed_files'] = get_changed_files($pdo, $app['id']);
        } else {
            $app['changed_files'] = [];
        }

        // Check if folder still exists
        $app['folder_exists'] = is_dir($app['path']);
    }
    unset($app);

    json_response([
        'count'        => count($apps),
        'apps'         => $apps,
        'last_scan_at' => $lastScanAt,
    ]);
}

function get_app_by_name(PDO $pdo, string $name): ?array
{
    $stmt = $pdo->prepare('
        SELECT a.id, a.name, a.path, a.notes, a.is_active, a.is_pinned, a.pinned_at,
               a.created_at, a.updated_at
        FROM apps a WHERE a.name = :name
    ');
    $stmt->execute(['name' => $name]);
    $app = $stmt->fetch();
    if (!$app) return null;

    $app['stacks'] = get_app_stacks($pdo, $app['id']);
    $app['services'] = get_app_services($pdo, $app['id']);
    $app['notes'] = get_app_notes($pdo, $app['id']);
    // SCM
    $scmStmt = $pdo->prepare('
        SELECT * FROM app_scm WHERE app_id = :app_id
    ');
    $scmStmt->execute(['app_id' => $app['id']]);
    $scm = $scmStmt->fetch();
    if ($scm) {
        // Prevent app_scm's id/created_at/updated_at from overwriting apps' columns
        unset($scm['id'], $scm['created_at'], $scm['updated_at']);
        $app = array_merge($app, $scm);
        $app['scm_status'] = $scm['status'];
        $app['changed_files'] = get_changed_files($pdo, $app['id']);
    } else {
        $app['scm_status'] = 'no_git';
        $app['changed_files'] = [];
    }

    return $app;
}

function get_app_stacks(PDO $pdo, int $appId): array
{
    $stmt = $pdo->prepare('
        SELECT type, framework, language_version, dependencies
        FROM app_stack WHERE app_id = :app_id
        ORDER BY FIELD(type, "php", "node", "python", "other")
    ');
    $stmt->execute(['app_id' => $appId]);
    $stacks = $stmt->fetchAll();
    foreach ($stacks as &$s) {
        $s['dependencies'] = $s['dependencies'] ? json_decode($s['dependencies'], true) : [];
    }
    return $stacks;
}

function get_app_services(PDO $pdo, int $appId): array
{
    $stmt = $pdo->prepare('
        SELECT id, service_name, service_type, provider, endpoint_url, notes
        FROM app_services WHERE app_id = :app_id
        ORDER BY service_type, service_name
    ');
    $stmt->execute(['app_id' => $appId]);
    return $stmt->fetchAll();
}

function get_changed_files(PDO $pdo, int $appId): array
{
    $stmt = $pdo->prepare('
        SELECT cf.file_path, cf.status
        FROM app_changed_files cf
        JOIN app_scm scm ON scm.id = cf.scm_id
        WHERE scm.app_id = :app_id
        ORDER BY cf.file_path
    ');
    $stmt->execute(['app_id' => $appId]);
    return $stmt->fetchAll();
}

// ——————————————————————————————————————————————
// POST — Manual add / Add note
// ——————————————————————————————————————————————

function handle_post(): void
{
    // Sub-route: ?name=<name> → add a catatan/ulasan note to that app
    if (!empty($_GET['name'])) {
        handle_post_note();
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || empty($body['name']) || empty($body['path'])) {
        json_response(['error' => 'name and path are required'], 400);
    }

    $name = trim($body['name']);
    $path = trim($body['path']);
    $notes = trim($body['notes'] ?? '');

    // Validate path exists
    if (!is_dir($path)) {
        json_response(['error' => 'Directory not found', 'path' => $path], 400);
    }

    $pdo = db();

    // Check for duplicate
    $existing = $pdo->prepare('SELECT id FROM apps WHERE name = :name');
    $existing->execute(['name' => $name]);
    if ($existing->fetch()) {
        json_response(['error' => 'An app with this name already exists'], 409);
    }

    $stmt = $pdo->prepare('
        INSERT INTO apps (name, path, notes) VALUES (:name, :path, :notes)
    ');
    $stmt->execute(['name' => $name, 'path' => str_replace('\\', '/', $path), 'notes' => $notes ?: null]);
    $appId = $pdo->lastInsertId();

    json_response([
        'message' => 'App added. Run scan to auto-detect stack and SCM.',
        'app' => [
            'id'   => (int)$appId,
            'name' => $name,
            'path' => str_replace('\\', '/', $path),
        ],
    ], 201);
}

// —————————————————————————————————————
// POST note: /api/apps.php?name=<name>  body { "note": "..." }
// ——————————————————————————————
function handle_post_note(): void
{
    $name = $_GET['name'];
    $pdo = db();
    $app = get_app_by_name($pdo, $name);
    if (!$app) {
        json_response(['error' => 'App not found'], 404);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $content = trim((string)($body['note'] ?? $body['content'] ?? ''));
    if ($content === '') {
        json_response(['error' => 'note content is required'], 400);
    }

    $stmt = $pdo->prepare('
        INSERT INTO app_notes (app_id, content) VALUES (:app_id, :content)
    ');
    $stmt->execute(['app_id' => $app['id'], 'content' => $content]);

    $note = get_note_by_id($pdo, $pdo->lastInsertId());
    json_response(['message' => 'Catatan ditambah', 'note' => $note], 201);
}

// —————————————————————————————
// DELETE note: /api/apps.php?name=<name>&note_id=<id>
// ——————————————————————
function handle_delete(): void
{
    if (empty($_GET['name'])) {
        json_response(['error' => 'Query parameter ?name= is required'], 400);
    }
    $pdo = db();
    $app = get_app_by_name($pdo, $_GET['name']);
    if (!$app) {
        json_response(['error' => 'App not found'], 404);
    }

    // Delete a specific note
    if (!empty($_GET['note_id'])) {
        $stmt = $pdo->prepare('DELETE FROM app_notes WHERE id = :id AND app_id = :app_id');
        $stmt->execute(['id' => $_GET['note_id'], 'app_id' => $app['id']]);
        if ($stmt->rowCount() === 0) {
            json_response(['error' => 'Note not found or not owned by this app'], 404);
        }
        json_response(['message' => 'Catatan dipadam', 'deleted_id' => (int)$_GET['note_id']]);
    }

    // If no note_id, we could support bulk-clear later — for now reject
    json_response(['error' => 'note_id is required to delete a note'], 400);
}

// —————————————————————
// Helpers
// ———————————————

function get_app_notes(PDO $pdo, int $appId): array
{
    $stmt = $pdo->prepare('
        SELECT id, content, created_at, updated_at
        FROM app_notes
        WHERE app_id = :app_id
        ORDER BY created_at DESC
    ');
    $stmt->execute(['app_id' => $appId]);
    return $stmt->fetchAll();
}

function get_note_by_id(PDO $pdo, int $noteId): ?array
{
    $stmt = $pdo->prepare('
        SELECT id, content, created_at, updated_at
        FROM app_notes
        WHERE id = :id
    ');
    $stmt->execute(['id' => $noteId]);
    return $stmt->fetch();
}

// ——————————————————————————————————————————————
// PUT — Edit app profile
// ——————————————————————————————————————————————

function handle_put(): void
{
    $name = $_GET['name'] ?? null;
    if (!$name) {
        json_response(['error' => 'Query parameter ?name= is required for PUT'], 400);
    }

    $pdo = db();
    $app = get_app_by_name($pdo, $name);
    if (!$app) {
        json_response(['error' => 'App not found'], 404);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) {
        json_response(['error' => 'JSON body required'], 400);
    }

    $pdo->beginTransaction();
    try {
        // Update app metadata — name/path/notes all editable. Renaming must not collide.
        if (isset($body['name']) && $body['name'] !== $app['name']) {
            $newName = trim((string)$body['name']);
            if ($newName === '') {
                json_response(['error' => 'name cannot be empty'], 400);
            }
            $check = $pdo->prepare('SELECT id FROM apps WHERE name = :name AND id <> :id');
            $check->execute(['name' => $newName, 'id' => $app['id']]);
            if ($check->fetch()) {
                json_response(['error' => 'An app with that name already exists'], 409);
            }
            $pdo->prepare('UPDATE apps SET name = :name, updated_at = NOW() WHERE id = :id')
                ->execute(['name' => $newName, 'id' => $app['id']]);
        }

        if (isset($body['path'])) {
            $newPath = str_replace('\\', '/', trim((string)$body['path']));
            if ($newPath === '' || !is_dir($newPath)) {
                json_response(['error' => 'path must be an existing directory', 'path' => $newPath], 400);
            }
            $pdo->prepare('UPDATE apps SET path = :path, updated_at = NOW() WHERE id = :id')
                ->execute(['path' => $newPath, 'id' => $app['id']]);
        }

        if (array_key_exists('notes', $body)) {
            $pdo->prepare('UPDATE apps SET notes = :notes, updated_at = NOW() WHERE id = :id')
                ->execute(['notes' => $body['notes'] !== null ? (string)$body['notes'] : null, 'id' => $app['id']]);
        }

        if (array_key_exists('active', $body)) {
            $active = !empty($body['active']) ? 1 : 0;
            // Validate folder exists if activating
            if ($active === 1 && is_dir($app['path']) === false) {
                json_response(['error' => 'Cannot activate: folder not found', 'path' => $app['path']], 400);
            }
            $pdo->prepare('UPDATE apps SET is_active = :active, updated_at = NOW() WHERE id = :id')
                ->execute(['active' => $active, 'id' => $app['id']]);
        }

        // Pin / unpin — pins float to the top of the dashboard
        if (array_key_exists('pinned', $body)) {
            $pinned = !empty($body['pinned']) ? 1 : 0;
            $stmt = $pdo->prepare('
                UPDATE apps
                SET is_pinned = :pinned1,
                    pinned_at = IF(:pinned2 = 1, NOW(), NULL),
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->execute(['pinned1' => $pinned, 'pinned2' => $pinned, 'id' => $app['id']]);
        }

        // Update services — replace entire set for this app
        if (isset($body['services'])) {
            $pdo->prepare('DELETE FROM app_services WHERE app_id = ?')->execute([$app['id']]);

            $svcStmt = $pdo->prepare('
                INSERT INTO app_services (app_id, service_name, service_type, provider, endpoint_url, notes)
                VALUES (:app_id, :service_name, :service_type, :provider, :endpoint_url, :notes)
            ');
            foreach ($body['services'] as $svc) {
                if (empty($svc['service_name']) || empty($svc['service_type'])) continue;
                $svcStmt->execute([
                    'app_id'       => $app['id'],
                    'service_name' => $svc['service_name'],
                    'service_type' => $svc['service_type'],
                    'provider'     => $svc['provider'] ?? null,
                    'endpoint_url' => $svc['endpoint_url'] ?? null,
                    'notes'        => $svc['notes'] ?? null,
                ]);
            }
        }

        $pdo->commit();
        json_response(['message' => 'App profile updated', 'app' => get_app_by_name($pdo, $name)]);

    } catch (\Throwable $e) {
        $pdo->rollBack();
        json_response(['error' => $e->getMessage()], 500);
    }
}
