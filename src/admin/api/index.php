<?php
/**
 * User Management API
 *
 * A RESTful API that handles all CRUD operations for user management
 * and password changes for the Admin Portal.
 * Uses PDO to interact with a MySQL database.
 *
 * Database Table (ground truth: see schema.sql):
 * Table: users
 * Columns:
 *   - id         (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - name       (VARCHAR(100), NOT NULL)
 *   - email      (VARCHAR(100), NOT NULL, UNIQUE)
 *   - password   (VARCHAR(255), NOT NULL) - bcrypt hash
 *   - is_admin   (TINYINT(1), NOT NULL, DEFAULT 0)
 *   - created_at (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
 *
 * HTTP Methods Supported:
 *   - GET    : Retrieve all users (with optional search/sort query params)
 *   - GET    : Retrieve a single user by id (?id=1)
 *   - POST   : Create a new user
 *   - POST   : Change a user's password (?action=change_password)
 *   - PUT    : Update an existing user's name, email, or is_admin
 *   - DELETE : Delete a user by id (?id=1)
 *
 * Response Format: JSON
 * All responses have the shape:
 *   { "success": true,  "data": ... }
 *   { "success": false, "message": "..." }
 */


// TODO: Set headers for JSON response and CORS.
// Set Content-Type to application/json.
// Allow cross-origin requests (CORS) if needed.
// Allow specific HTTP methods: GET, POST, PUT, DELETE, OPTIONS.
// Allow specific headers: Content-Type, Authorization.
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


// TODO: Handle preflight OPTIONS request.
// If the request method is OPTIONS, return HTTP 200 and exit.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// TODO: Include the database connection file.
// Assume a function getDBConnection() is available that returns a PDO instance
// configured for the 'course' database (see schema.sql).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    // For GET requests with an 'id' parameter, we will route to getUserById(),
    // which needs the id as an integer. We can read it here and pass it down.
    $id = (int) $_GET['id'];
} else if ($_SERVER['REQUEST_METHOD'] === 'POST'&& isset($_POST['id'])) {
    // For POST requests with an 'id' parameter, we will route to changePassword(),
    // which also needs the id as an integer. We can read it here and pass it down.
    $id = (int) $_POST['id'];
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    // For POST requests with an 'action' parameter of 'change_password', we will route to changePassword(),
    // which needs the id from the JSON body, not the query string. We will read it in the changePassword() function.
} else {
    // For other requests, we will route to getUsers(), createUser(), updateUser(), or deleteUser(),
    // which do not require an id from the query string. We can ignore it here.
}

// TODO: Get the PDO database connection by calling getDBConnection().
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
} else {
    $id = null;
}

// TODO: Read the HTTP request method from $_SERVER['REQUEST_METHOD'].
if (isset($_GET['method'])) {
    $method = $_SERVER['REQUEST_METHOD'];
} else {
    $method = null;
}

// TODO: Read the raw request body for POST and PUT requests.
// Use file_get_contents('php://input') and decode with json_decode($raw, true).
if (isset($_GET['method']) && ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT')) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
} else {
    $data = null;
}

// TODO: Read query string parameters.
// Relevant parameters:
//   - id            (int)    : identifies a specific user by primary key
//   - action        (string) : 'change_password' to route password-change requests
//   - search        (string) : free-text filter for GET requests
//   - sort          (string) : field name to sort by
//   - order         (string) : 'asc' or 'desc'

if (isset($_GET['search'])) {
    $search = $_GET['search'];
} else {
    $search = null;
}

if (isset($_GET['sort'])) {
    $sort = $_GET['sort'];
} else {
    $sort = null;
}

if (isset($_GET['order'])) {
    $order = $_GET['order'];
} else {
    $order = null;
}
if (isset($_GET['action']) && $_GET['action'] === 'change_password') {
    $action = $_GET['action'];
} else {
    $action = null;
}
/**
 * Function: Get all users, or search/filter users.
 * Method: GET (no ?id parameter)
 *
 * Supported query parameters:
 *   - search (string) : filters rows where name LIKE or email LIKE the term
 *   - sort   (string) : column to sort by; allowed values: name, email, is_admin
 *   - order  (string) : sort direction; allowed values: asc, desc (default: asc)
 *
 * Notes:
 *   - Never return the password column in the response.
 *   - Validate the 'sort' value against the whitelist (name, email, is_admin)
 *     to prevent SQL injection before interpolating it into the ORDER BY clause.
 *   - Validate the 'order' value; only accept 'asc' or 'desc'.
 */
function getUsers($db) {
    // TODO: Build a SELECT query for id, name, email, is_admin, created_at.
    //       Do NOT select the password column.
    $search = $_GET['search'] ?? ''; 
    $sort = $_GET['sort'] ?? 'name';
    $order = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    $sql = '';

    // TODO: If the 'search' query parameter is present, append a WHERE clause:
    //       WHERE name LIKE :search OR email LIKE :search
    //       Wrap the search term with '%' wildcards when binding.
    $stmt = $db->prepare($sql);

    // TODO: If the 'sort' query parameter is present and is one of the allowed
    //       fields (name, email, is_admin), append an ORDER BY clause.
    //       If 'order' is 'desc', use DESC; otherwise default to ASC.
    $stmt->bindParam(':search', $search);
    $stmt->bindParam(':sort', $sort);
    $stmt->bindParam(':order', $order);

    // TODO: Prepare the statement, bind any parameters, and execute.
    $stmt->execute();

    // TODO: Fetch all rows as an associative array.
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // TODO: Call sendResponse() with the array and HTTP status 200.
    sendResponse($users, 200);
}


/**
 * Function: Get a single user by primary key.
 * Method: GET with ?id=<int>
 *
 * Query parameters:
 *   - id (int, required) : the user's primary key in the users table
 */
function getUserById($db, $id) {
    // TODO: Prepare SELECT query: SELECT id, name, email, is_admin, created_at
    //       FROM users WHERE id = :id
    //       Do NOT select the password column.
        $sql = 'SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id';

    // TODO: Bind :id and execute.
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // TODO: Fetch one row.
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
sendResponse($user,200);

    // TODO: If no row is found, call sendResponse() with an error message and HTTP 404.
    //       If found, call sendResponse() with the row and HTTP 200.
    if (!$user) {
        sendResponse("User not found", 404);
    } else {
        sendResponse($user, 200);
    }
}


/**
 * Function: Create a new user.
 * Method: POST (no ?action parameter)
 *
 * Expected JSON body:
 *   - name     (string, required)
 *   - email    (string, required) - must be a valid email address and unique
 *   - password (string, required) - plaintext; will be hashed before storage
 *   - is_admin (int, optional)    - 0 (student) or 1 (admin); defaults to 0
 */
function createUser($db, $data) {
    // TODO: Check that name, email, and password are all present and non-empty.
    //       If any are missing, call sendResponse() with HTTP 400.
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            sendResponse("Name, email, and password are required", 400);
        }

    // TODO: Trim whitespace from name, email, and password.
    //       Validate email format with filter_var(FILTER_VALIDATE_EMAIL).
    //       If invalid, call sendResponse() with HTTP 400.
    $name = trim($data['name']);
    $email = trim($data['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse("Invalid email format", 400);
    }
    $password = trim($data["password"]);
    if (!filter_var($password, FILTER_VALIDATE_EMAIL)) {
        sendResponse("Invalid password format", 400);
    }
    // TODO: Validate that password is at least 8 characters.
    //       If not, call sendResponse() with HTTP 400.
    if (strlen($password) < 8) {
        sendResponse("Password must be at least 8 characters", 400);
    }
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    // TODO: Check whether the email already exists in the users table.
    //       If it does, call sendResponse() with an appropriate message and HTTP 409.
    if ($stmt->fetch()) {
        sendResponse("Email already exists", 409);
    } else {
        sendResponse("Email is available", 200);
    }

    // TODO: Hash the password using password_hash($password, PASSWORD_DEFAULT).
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // TODO: Read is_admin from $data; default to 0 if not provided.
    //       Accept only the values 0 or 1.
    $is_admin = isset($data['is_admin']) ? (int) $data['is_admin'] : 0;
    if ($is_admin !== 0 && $is_admin !== 1) {
        sendResponse("is_admin must be 0 or 1", 400);
    }

    // TODO: Prepare and execute an INSERT INTO users (name, email, password, is_admin)
    //       VALUES (:name, :email, :password, :is_admin).
    $sql = "INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)";
    $stmt = $db->prepare($sql);

    // TODO: If the insert succeeds, call sendResponse() with the new user's id and HTTP 201.
    //       If it fails, call sendResponse() with HTTP 500.
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':is_admin', $is_admin, PDO::PARAM_INT);

    if ($stmt->execute()) {
        sendResponse("User created successfully", 201);
    } else {
        sendResponse("Failed to create user", 500);
    }
}


/**
 * Function: Update an existing user.
 * Method: PUT
 *
 * Expected JSON body:
 *   - id       (int, required)    : primary key of the user to update
 *   - name     (string, optional) : new name
 *   - email    (string, optional) : new email (must remain unique)
 *   - is_admin (int, optional)    : 0 or 1
 *
 * Note: password changes are handled by the separate changePassword endpoint.
 */
function updateUser($db, $data) {
    // TODO: Check that id is present in $data.
    //       If not, call sendResponse() with HTTP 400.
        if (empty($data['id'])) {
            sendResponse("User ID is required", 400);
        }
        $id = (int) $data['id'];

    // TODO: Look up the user by id. If not found, call sendResponse() with HTTP 404.
    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if (!$stmt->fetch()) {
        sendResponse("User not found", 404);
    }
    $sql = "UPDATE users SET name = :name, email = :email, is_admin = :is_admin WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    // TODO: Dynamically build the SET clause for only the fields provided
    //       (name, email, is_admin). Skip any field not present in $data.
        if (isset($data['name'])) {
            $name = trim($data['name']);
            $stmt->bindParam(':name', $name);
        } else {
            $stmt->bindValue(':name', null, PDO::PARAM_NULL);
        }

    // TODO: If email is being updated, check it is not already used by another user
    //       (exclude the current user's id from the duplicate check).
    //       If a duplicate is found, call sendResponse() with HTTP 409.
    $stmt->execute();
    if (!$stmt->rowCount()) {
        sendResponse("Email already exists", 409);
    } else {
        sendResponse("Email is available", 200);
    }

     if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendResponse("Invalid email format", 400);
            }
            $stmt->bindParam(':email', $email);
        } else {
            $stmt->bindValue(':email', null, PDO::PARAM_NULL);
        }

     if (isset($data['is_admin'])) {
            $is_admin = (int) $data['is_admin'];
            if ($is_admin !== 0 && $is_admin !== 1) {
                sendResponse("is_admin must be 0 or 1", 400);
            }
            $stmt->bindParam(':is_admin', $is_admin, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':is_admin', null, PDO::PARAM_NULL);
        }

    // TODO: Prepare the UPDATE statement, bind parameters, and execute.
    $stmt->execute();
    if (!$stmt->rowCount()) {
        sendResponse("No changes made", 200);
    } else {
        sendResponse("User updated successfully", 200);
    }

    // TODO: If successful, call sendResponse() with a success message and HTTP 200.
    //       If no rows were affected, still return HTTP 200 (no change is not an error).
    //       If the query fails, call sendResponse() with HTTP 500.
    if (isset($data["name"]) || isset($data["email"]) || isset($data["is_admin"])) {
        sendResponse("User updated successfully", 200);
    } else {
        sendResponse("Failed to update user", 500);
    }
}


/**
 * Function: Delete a user by primary key.
 * Method: DELETE
 *
 * Query parameter:
 *   - id (int, required) : primary key of the user to delete
 */
function deleteUser($db, $id) {
    // TODO: Check that $id is present and non-zero.
    //       If not, call sendResponse() with HTTP 400.
    if (!isset($id) || $id === 0) {
        sendResponse("Invalid user ID", 400);
        return;
    }
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");

    // TODO: Check that a user with this id exists.
    //       If not, call sendResponse() with HTTP 404.
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    if (!$stmt->rowCount()) {
        sendResponse("User not found", 404);
    } else {
        sendResponse("User deleted successfully", 200);
    }

    // TODO: Prepare and execute: DELETE FROM users WHERE id = :id
    if (isset($data["name"]) || isset($data["email"]) || isset($data["is_admin"])) {
        sendResponse("User deleted successfully", 200);
    } else {
        sendResponse("Failed to delete user", 500);
    }

    // TODO: If successful, call sendResponse() with a success message and HTTP 200.
    //       If the query fails, call sendResponse() with HTTP 500.
    if (isset($data["name"]) || isset($data["email"]) || isset($data["is_admin"])) {
        sendResponse("User deleted successfully", 200);
    } else {
        sendResponse("Failed to delete user", 500);
    }
}


/**
 * Function: Change a user's password.
 * Method: POST with ?action=change_password
 *
 * Expected JSON body:
 *   - id               (int, required)    : primary key of the user whose password is changing
 *   - current_password (string, required) : must match the stored bcrypt hash
 *   - new_password     (string, required) : plaintext; will be hashed before storage
 */
function changePassword($db, $data) {
    // TODO: Check that id, current_password, and new_password are all present.
    //       If any are missing, call sendResponse() with HTTP 400.
    if (!isset($data["name"]) || $data["name"] === "" || !isset($data["current_password"]) || $data["current_password"] === "" || !isset($data["new_password"]) || $data["new_password"] === "") {
        sendResponse("User ID, current password, and new password are required", 400);
    }

    // TODO: Validate that new_password is at least 8 characters.
    //       If not, call sendResponse() with HTTP 400.
    if (strlen($data["new_password"]) < 8) {
        sendResponse("New password must be at least 8 characters", 400);
    }

    // TODO: SELECT password FROM users WHERE id = :id to retrieve the current hash.
    //       If no user is found, call sendResponse() with HTTP 404.
    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->bindParam(':id', $data["name"], PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendResponse("User not found", 404);
    }
    if ($row["password"] != $data["current_password"]) {
        sendResponse("Current password is incorrect", 401);
    }

    // TODO: Call password_verify($current_password, $hash).
    //       If verification fails, call sendResponse() with HTTP 401 (Unauthorized).
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");

    // TODO: Hash the new password: password_hash($new_password, PASSWORD_DEFAULT).
    $hashedPassword = password_hash($data["new_password"], PASSWORD_DEFAULT);

    // TODO: Prepare and execute: UPDATE users SET password = :password WHERE id = :id
    $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
    $stmt->bindParam(':id', $data["name"], PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // TODO: If successful, call sendResponse() with a success message and HTTP 200.
    //       If the query fails, call sendResponse() with HTTP 500.
    if ($stmt->rowCount()) {
        sendResponse("Password changed successfully", 200);
    } else {
        sendResponse("Failed to change password", 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {
        // TODO: If the 'id' query parameter is present and non-empty, call getUserById($db, $id).
        // TODO: Otherwise, call getUsers($db) (supports optional search/sort parameters).
        $db = new PDO('mysql:host=localhost;dbname=your_database', $username, $password);
        $stmt = $db->prepare('SELECT * FROM users');
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($method === 'POST') {
        // TODO: If the 'action' query parameter equals 'change_password', call changePassword($db, $data).
        // TODO: Otherwise, call createUser($db, $data).
        $db = new PDO('mysql:host=localhost;dbname=your_database', $username, $password);
        $stmt = $db->prepare('INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)');
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($method === 'PUT') {
        // TODO: Call updateUser($db, $data).
        //       The user id to update comes from the JSON body, not the query string.
        $db = new PDO('mysql:host=localhost;dbname=your_database', $username, $password);
        $stmt = $db->prepare('UPDATE users SET name = :name, email = :email, is_admin = :is_admin WHERE id = :id');
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($method === 'DELETE') {
        // TODO: Read the 'id' query parameter.
        // TODO: Call deleteUser($db, $id).
        $db = new PDO('mysql:host=localhost;dbname=your_database', $username, $password);
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);


    } else {
        // TODO: Return HTTP 405 (Method Not Allowed) with a JSON error message.
            sendResponse("Method not allowed", 405);
    }

} catch (PDOException $e) {
    // TODO: Log the error (e.g. error_log($e->getMessage())).
    // TODO: Call sendResponse() with a generic "Database error" message and HTTP 500.
    //       Do NOT expose the raw exception message to the client.
        error_log($e->getMessage());
        sendResponse("Database error", 500);

} catch (Exception $e) {
    // TODO: Call sendResponse() with the exception message and HTTP 500.
    error_log($e->getMessage());
    sendResponse("An error occurred", 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Sends a JSON response and terminates execution.
 *
 * @param mixed $data       Data to include in the response.
 *                          On success, pass the payload directly.
 *                          On error, pass a string message.
 * @param int   $statusCode HTTP status code (default 200).
 */
function sendResponse($data, $statusCode = 200) {
    // TODO: Call http_response_code($statusCode).
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=UTF-8");

    // TODO: If $statusCode indicates success (< 400), echo:
    //         json_encode(['success' => true, 'data' => $data])
    //       Otherwise echo:
    //         json_encode(['success' => false, 'message' => $data])
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    if ($statusCode < 400) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => $data]);
    }

    // TODO: Call exit to stop further execution.
    exit;
}


/**
 * Validates an email address.
 *
 * @param  string $email
 * @return bool   True if the email passes FILTER_VALIDATE_EMAIL, false otherwise.
 */
function validateEmail($email) {
    // TODO: return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);

}


/**
 * Sanitizes a string input value.
 * Use this before inserting user-supplied strings into the database.
 *
 * @param  string $data
 * @return string Trimmed, tag-stripped, and HTML-escaped string.
 */
function sanitizeInput($data) {
    // TODO: trim($data)
    $data = trim($data);
    // TODO: strip_tags(...)
    $data = strip_tags($data);
    // TODO: htmlspecialchars(..., ENT_QUOTES, 'UTF-8')
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    // TODO: Return the sanitized value.
    return $data;
}

?>
