<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db     = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

$action    = $_GET['action']     ?? null;
$id        = $_GET['id']         ?? null;
$weekId    = $_GET['week_id']    ?? null;
$commentId = $_GET['comment_id'] ?? null;



function getAllWeeks(PDO $db): void
{
    $sql    = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    $search = $_GET['search'] ?? '';
    if (!empty($search)) {
        $sql             .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    $allowedSort = ['title', 'start_date'];
    $sort        = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'start_date';

    $allowedOrder = ['asc', 'desc'];
    $order        = in_array(strtolower($_GET['order'] ?? ''), $allowedOrder)
                    ? strtolower($_GET['order'])
                    : 'asc';

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$row) {
        $row['links'] = json_decode($row['links'], true) ?? [];
    }
    unset($row);

    sendResponse(['success' => true, 'data' => $weeks]);
}


function getWeekById(PDO $db, $id): void
{
    if (!isset($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week id.'], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, title, start_date, description, links, created_at
         FROM weeks WHERE id = ?"
    );
    $stmt->execute([(int)$id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$week) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $week['links'] = json_decode($week['links'], true) ?? [];
    sendResponse(['success' => true, 'data' => $week]);
}


function createWeek(PDO $db, array $data): void
{
    if (empty($data['title']) || empty($data['start_date'])) {
        sendResponse(['success' => false, 'message' => 'title and start_date are required.'], 400);
    }

    $title      = sanitizeInput($data['title']);
    $start_date = trim($data['start_date']);
    $description = sanitizeInput($data['description'] ?? '');

    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid start_date. Use YYYY-MM-DD format.'], 400);
    }

    $links = (isset($data['links']) && is_array($data['links']))
             ? json_encode($data['links'])
             : json_encode([]);

    $stmt = $db->prepare(
        "INSERT INTO weeks (title, start_date, description, links)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$title, $start_date, $description, $links]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Week created successfully.',
            'id'      => (int)$db->lastInsertId()
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create week.'], 500);
    }
}


function updateWeek(PDO $db, array $data): void
{
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'A valid week id is required.'], 400);
    }

    $id = (int)$data['id'];

    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $setClauses = [];
    $params     = [];

    if (isset($data['title'])) {
        $setClauses[] = "title = ?";
        $params[]     = sanitizeInput($data['title']);
    }

    if (isset($data['start_date'])) {
        $start_date = trim($data['start_date']);
        if (!validateDate($start_date)) {
            sendResponse(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD.'], 400);
        }
        $setClauses[] = "start_date = ?";
        $params[]     = $start_date;
    }

    if (isset($data['description'])) {
        $setClauses[] = "description = ?";
        $params[]     = sanitizeInput($data['description']);
    }

    if (isset($data['links'])) {
        $setClauses[] = "links = ?";
        $params[]     = is_array($data['links']) ? json_encode($data['links']) : json_encode([]);
    }

    if (empty($setClauses)) {
        sendResponse(['success' => false, 'message' => 'No updatable fields provided.'], 400);
    }

    $sql      = "UPDATE weeks SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $params[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week updated successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Update failed or no changes made.'], 500);
    }
}


function deleteWeek(PDO $db, $id): void
{
    if (!isset($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week id.'], 400);
    }

    $id = (int)$id;
    
    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete week.'], 500);
    }
}

function getCommentsByWeek(PDO $db, $weekId): void
{
    if (!isset($weekId) || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week_id.'], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, week_id, author, text, created_at
         FROM comments_week
         WHERE week_id = ?
         ORDER BY created_at ASC"
    );
    $stmt->execute([(int)$weekId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Empty array is valid — not an error
    sendResponse(['success' => true, 'data' => $comments]);
}


function createComment(PDO $db, array $data): void
{
    $weekId = $data['week_id'] ?? null;
    $author = trim($data['author'] ?? '');
    $text   = trim($data['text']   ?? '');

    if (empty($weekId) || empty($author) || empty($text)) {
        sendResponse(['success' => false, 'message' => 'week_id, author, and text are required.'], 400);
    }

    if (!is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'week_id must be numeric.'], 400);
    }

    $weekId = (int)$weekId;

    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$weekId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Parent week not found.'], 404);
    }

    $author = sanitizeInput($author);
    $text   = sanitizeInput($text);

    $stmt = $db->prepare(
        "INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)"
    );
    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = (int)$db->lastInsertId();
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id'      => $newId,
            'data'    => [
                'id'         => $newId,
                'week_id'    => $weekId,
                'author'     => $author,
                'text'       => $text,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
    }
}


function deleteComment(PDO $db, $commentId): void
{
    if (!isset($commentId) || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing comment_id.'], 400);
    }

    $commentId = (int)$commentId;

    // Check existence
    $check = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $check->execute([$commentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
    }
}

try {

    if ($method === 'GET') {

        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif (isset($id)) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateWeek($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }

    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

} catch (PDOException $e) {
    error_log('PDOException: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A database error occurred.'], 500);

} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
