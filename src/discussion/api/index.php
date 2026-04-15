<?php
/**
 * Discussion Board API
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db      = getDBConnection();
$method  = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

$action  = $_GET['action']   ?? null;
$id      = $_GET['id']       ?? null;
$topicId = $_GET['topic_id'] ?? null;


// ============================================================================
// TOPICS FUNCTIONS
// ============================================================================

function getAllTopics(PDO $db): void
{
    $search = $_GET['search'] ?? null;

    $sql    = 'SELECT id, subject, message, author, created_at FROM topics';
    $params = [];

    if (!empty($search)) {
        $sql           .= ' WHERE subject LIKE :search
                             OR message  LIKE :search
                             OR author   LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $allowedSort  = ['subject', 'author', 'created_at'];
    $allowedOrder = ['asc', 'desc'];

    $sort  = in_array($_GET['sort']  ?? '', $allowedSort)  ? $_GET['sort']  : 'created_at';
    $order = in_array($_GET['order'] ?? '', $allowedOrder) ? $_GET['order'] : 'desc';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $topics]);
}


function getTopicById(PDO $db, $id): void
{
    if (!isset($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing topic id.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, subject, message, author, created_at
         FROM topics
         WHERE id = ?'
    );
    $stmt->execute([(int) $id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        sendResponse(['success' => true, 'data' => $topic]);
    } else {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }
}


function createTopic(PDO $db, array $data): void
{
    if (empty($data['subject']) || empty($data['message']) || empty($data['author'])) {
        sendResponse(['success' => false, 'message' => 'subject, message, and author are required.'], 400);
    }

    $subject = sanitizeInput($data['subject']);
    $message = sanitizeInput($data['message']);
    $author  = sanitizeInput($data['author']);

    $stmt = $db->prepare(
        'INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)'
    );
    $stmt->execute([$subject, $message, $author]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Topic created successfully.',
            'id'      => (int) $db->lastInsertId()
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create topic.'], 500);
    }
}


function updateTopic(PDO $db, array $data): void
{
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Topic id is required.'], 400);
    }

    $id = (int) $data['id'];

    $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $setClauses = [];
    $params     = [];

    if (!empty($data['subject'])) {
        $setClauses[] = 'subject = ?';
        $params[]     = sanitizeInput($data['subject']);
    }
    if (!empty($data['message'])) {
        $setClauses[] = 'message = ?';
        $params[]     = sanitizeInput($data['message']);
    }

    if (empty($setClauses)) {
        sendResponse(['success' => false, 'message' => 'No updatable fields provided.'], 400);
    }

    $params[] = $id;
    $sql      = 'UPDATE topics SET ' . implode(', ', $setClauses) . ' WHERE id = ?';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Topic updated successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update topic.'], 500);
    }
}


function deleteTopic(PDO $db, $id): void
{
    if (!isset($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing topic id.'], 400);
    }

    $id = (int) $id;

    $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM topics WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Topic deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete topic.'], 500);
    }
}


// ============================================================================
// REPLIES FUNCTIONS
// ============================================================================

function getRepliesByTopicId(PDO $db, $topicId): void
{
    if (!isset($topicId) || !is_numeric($topicId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing topic_id.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, topic_id, text, author, created_at
         FROM replies
         WHERE topic_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([(int) $topicId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $replies]);
}


function createReply(PDO $db, array $data): void
{
    if (empty($data['topic_id']) || empty($data['text']) || empty($data['author'])) {
        sendResponse(['success' => false, 'message' => 'topic_id, text, and author are required.'], 400);
    }

    if (!is_numeric($data['topic_id'])) {
        sendResponse(['success' => false, 'message' => 'topic_id must be numeric.'], 400);
    }

    $topicId = (int) $data['topic_id'];

    $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $check->execute([$topicId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $text   = sanitizeInput($data['text']);
    $author = sanitizeInput($data['author']);

    $stmt = $db->prepare(
        'INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)'
    );
    $stmt->execute([$topicId, $text, $author]);

    if ($stmt->rowCount() > 0) {
        $newId = (int) $db->lastInsertId();

        $fetch = $db->prepare(
            'SELECT id, topic_id, text, author, created_at FROM replies WHERE id = ?'
        );
        $fetch->execute([$newId]);
        $reply = $fetch->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            'success' => true,
            'message' => 'Reply created successfully.',
            'id'      => $newId,
            'data'    => $reply
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create reply.'], 500);
    }
}


function deleteReply(PDO $db, $replyId): void
{
    if (!isset($replyId) || !is_numeric($replyId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing reply id.'], 400);
    }

    $replyId = (int) $replyId;

    $check = $db->prepare('SELECT id FROM replies WHERE id = ?');
    $check->execute([$replyId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Reply not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM replies WHERE id = ?');
    $stmt->execute([$replyId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Reply deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete reply.'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        if ($action === 'replies') {
            getRepliesByTopicId($db, $topicId);
        } elseif (isset($id)) {
            getTopicById($db, $id);
        } else {
            getAllTopics($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'reply') {
            createReply($db, $data);
        } else {
            createTopic($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateTopic($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_reply') {
            deleteReply($db, $id);
        } else {
            deleteTopic($db, $id);
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


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
