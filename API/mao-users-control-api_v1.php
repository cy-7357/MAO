<?php
const DB_SERVER   = "sql102.infinityfree.com";
const DB_USERNAME = "if0_38646811";
const DB_PASSWORD = "CcYy1108";
const DB_NAME     = "if0_38646811_cy_mao";

function create_connection()
{
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if (! $conn) {
        echo json_encode(["state" => false, "message" => "連線失敗!"]);
        exit;
    }
    return $conn;
}

function get_json_input()
{
    $data = file_get_contents("php://input");
    return json_decode($data, true);
}

function respond($state, $message, $data = null)
{
    echo json_encode(["state" => $state, "message" => $message, "data" => $data]);
}

function register_user()
{
    $input = get_json_input();
    if (empty($input)) {
        $input = $_POST;
        error_log("使用 POST 資料: " . print_r($input, true));
        error_log("FILES 內容: " . print_r($_FILES, true)); 
    } else {
        error_log("使用 JSON 資料: " . print_r($input, true));
    }

    if (isset($input["username"], $input["password"], $input["email"], $input["region"])) {
        $p_username = trim($input["username"]);
        $p_password = password_hash(trim($input["password"]), PASSWORD_DEFAULT);
        $p_email    = trim($input["email"]);
        $p_region   = trim($input["region"]);
        $p_level    = isset($input["level"]) && trim($input["level"]) !== "" ? (int) trim($input["level"]) : 10;

        if ($p_username && $p_password && $p_email && $p_region) {
            $conn           = create_connection();
            $uploaded_photo = null;

            if (! empty($_FILES['photo']['name'])) {
                $upload_result = upload_user_photo();
                if (! $upload_result["state"]) {
                    respond(false, $upload_result["message"]);
                    return;
                }
                $uploaded_photo = $upload_result["name"];
            } elseif (isset($input["photo"]) && trim($input["photo"])) {
                $uploaded_photo = trim($input["photo"]);
            }

            $stmt = $conn->prepare("INSERT INTO users (Username, Password, Email, Region, Level, Userphoto) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssis", $p_username, $p_password, $p_email, $p_region, $p_level, $uploaded_photo);

            if ($stmt->execute()) {
                respond(true, "註冊成功!");
            } else {
                respond(false, "註冊失敗: " . $stmt->error);
            }
            $stmt->close();
            $conn->close();
        } else {
            respond(false, "欄位不得為空!");
        }
    } else {
        respond(false, "欄位錯誤!");
    }
}

function login_user()
{
    $input = get_json_input();
    if (isset($input["username"], $input["password"])) {
        $p_username = trim($input["username"]);
        $p_password = trim($input["password"]);
        if ($p_username && $p_password) {
            $conn = create_connection();
            $stmt = $conn->prepare("SELECT * FROM users WHERE Username = ?");
            $stmt->bind_param("s", $p_username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                if (password_verify($p_password, $row["Password"])) {
                    $uid01       = substr(bin2hex(random_bytes(16)), 9, 4) . substr(hash('md5', time()), 9, 4);
                    $update_stmt = $conn->prepare("UPDATE users SET Uid01 = ? WHERE Username = ?");
                    $update_stmt->bind_param('ss', $uid01, $p_username);
                    if ($update_stmt->execute()) {
                        $user_stmt = $conn->prepare("SELECT User_id, Username, Email, Region, Level, Userphoto, Uid01 FROM users WHERE Username = ?");
                        $user_stmt->bind_param("s", $p_username);
                        $user_stmt->execute();
                        $user_data = $user_stmt->get_result()->fetch_assoc();
                        respond(true, "登入成功", $user_data); 
                    } else {
                        respond(false, "UID更新失敗");
                    }
                    $update_stmt->close();
                    $user_stmt->close();
                } else {
                    respond(false, "登入失敗，帳號或密碼錯誤");
                }
            } else {
                respond(false, "登入失敗，該帳號不存在");
            }
            $stmt->close();
            $conn->close();
        } else {
            respond(false, "欄位不得為空!");
        }
    } else {
        respond(false, "欄位錯誤!");
    }
}

function check_uid()
{
    $input = get_json_input();
    if (isset($input["uid01"])) {
        $p_uid = trim($input["uid01"]);
        if ($p_uid) {
            $conn = create_connection();

            $stmt = $conn->prepare("SELECT * FROM users WHERE Uid01 = ?");
            $stmt->bind_param("s", $p_uid);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $userdata = $result->fetch_assoc();
                unset($userdata["Password"]);
                respond(true, "驗證成功!", $userdata);
            } else {
                respond(false, "驗證失敗!");
            }
            $stmt->close();
            $conn->close();
        } else {
            respond(false, "欄位不得為空!");
        }
    } else {
        respond(false, "欄位錯誤!");
    }
}

function register_checkuni()
{
    $input = get_json_input();
    if (isset($input["username"])) {
        $p_uni = trim($input["username"]);
        if ($p_uni) {
            $conn = create_connection();

            $stmt = $conn->prepare("SELECT Username FROM users WHERE Username = ?");
            $stmt->bind_param("s", $p_uni);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                respond(false, "帳號存在, 不可以使用");
            } else {
                respond(true, "帳號不存在, 可以使用");
            }
            $stmt->close();
            $conn->close();
        } else {
            respond(false, "欄位不得為空!");
        }
    } else {
        respond(false, "欄位錯誤!");
    }
}

function update_checkuni()
{
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $username = isset($_GET["username"]) ? trim($_GET["username"]) : '';
        $user_id  = isset($_GET["user_id"]) ? trim($_GET["user_id"]) : null;
    } else {
        $input = $_POST;
        if (empty($input)) {
            $input = get_json_input();
        }
        $username = isset($input["username"]) ? trim($input["username"]) : '';
        $user_id  = isset($input["user_id"]) ? trim($input["user_id"]) : null;
    }

    if ($username) {
        $conn = create_connection();

        $query  = "SELECT User_id FROM users WHERE Username = ?";
        $params = [$username];
        $types  = "s";

        if ($user_id) {
            $query .= " AND User_id != ?";
            $params[] = $user_id;
            $types .= "s";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            respond(false, "帳號已存在，不可以使用");
        } else {
            respond(true, "帳號可用");
        }
        $stmt->close();
        $conn->close();
    } else {
        respond(false, "欄位不得為空!");
    }
}

function get_all_user_data()
{
    $input = get_json_input();
    $conn  = create_connection();

    $stmt = $conn->prepare("SELECT * FROM users ORDER BY User_id DESC");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result > 0) {
        $mydata = [];
        while ($row = $result->fetch_assoc()) {
            unset($row["Password"]);
            unset($row["Uid01"]);
            $mydata[] = $row;
        }
        respond(true, "取得所有會員資料成功", $mydata);
    } else {
        respond(false, "取得所有會員資料失敗");
    }
    $stmt->close();
    $conn->close();
}

function update_user()
{
    $input = $_POST;
    $conn  = create_connection();

    if (isset($input["user_id"])) {
        $user_id  = $input["user_id"];
        $username = isset($input["username"]) ? trim($input["username"]) : null;
        $email    = isset($input["email"]) ? trim($input["email"]) : null;
        $region   = isset($input["region"]) ? trim($input["region"]) : null;
        $level    = isset($input["level"]) ? (int) $input["level"] : null;
        $status   = isset($input["status"]) ? trim($input["status"]) : 'Y';

        if (strlen($status) > 1 || ! in_array($status, ['Y', 'N'])) {
            $status = 'Y';
            error_log("Status 值無效: {$input["status"]}，已強制設為 'Y'");
        }

        $uploaded_photo = null;
        if (! empty($_FILES['photo']['name'])) {
            $upload_result = upload_user_photo();
            if (! $upload_result["state"]) {
                respond(false, $upload_result["message"]);
                return;
            }
            $uploaded_photo = $upload_result["name"];
        }

        $query  = "UPDATE users SET Username = ?, Email = ?, Region = ?, Level = ?, Status = ?";
        $params = [$username, $email, $region, $level, $status];
        $types  = "sssds";

        if ($uploaded_photo) {
            $query .= ", Userphoto = ?";
            $params[] = $uploaded_photo;
            $types .= "s";
        }

        $query .= " WHERE User_id = ?";
        $params[] = $user_id;
        $types .= "s";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $select_stmt = $conn->prepare("SELECT User_id, Username, Email, Region, Level, Userphoto, Status, Created_at FROM users WHERE User_id = ?");
            $select_stmt->bind_param("s", $user_id);
            $select_stmt->execute();
            $result       = $select_stmt->get_result();
            $updated_user = $result->fetch_assoc();
            $select_stmt->close();

            respond(true, "更新成功", $updated_user);
        } else {
            respond(false, "更新失敗: " . $stmt->error);
        }
        $stmt->close();
        $conn->close();
    } else {
        respond(false, "缺少 user_id");
    }
}

function upload_user_photo()
{
    error_log("檢查 FILES['photo']: " . print_r($_FILES['photo'], true)); 
    if (! isset($_FILES['photo']['name']) || $_FILES['photo']['name'] == "") {
        return ["state" => false, "message" => "沒有選擇圖片"];
    }

    if ($_FILES['photo']['type'] !== 'image/jpeg' && $_FILES['photo']['type'] !== 'image/png') {
        return ["state" => false, "message" => "圖片格式錯誤，僅支援 JPEG 或 PNG"];
    }

    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
        return ["state" => false, "message" => "檔案大小超過限制 (5MB)！"];
    }

    $upload_dir = "uploads/users/";
    if (! is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = date("YmdHis") . "_" . basename($_FILES["photo"]["name"]);
    $location = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $location)) {
        return [
            "state"   => true,
            "message" => "圖片上傳成功",
            "name"    => $filename,
        ];
    } else {
        return ["state" => false, "message" => "圖片上傳失敗"];
    }
}

function delete_user()
{
    $input = get_json_input(); 
    if (empty($input)) {
        $input = $_POST; 
    }

    if (isset($input["user_id"])) {
        $m_id = trim($input["user_id"]);
        if ($m_id) {
            $conn = create_connection();

            $stmt = $conn->prepare("DELETE FROM users WHERE User_id = ?");
            $stmt->bind_param("i", $m_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows === 1) {
                    respond(true, "會員刪除成功");
                } else {
                    respond(false, "會員刪除失敗，可能該會員不存在");
                }
            } else {
                respond(false, "會員刪除失敗: " . $stmt->error); 
            }
            $stmt->close();
            $conn->close();
        } else {
            respond(false, "會員 ID 不得為空!");
        }
    } else {
        respond(false, "缺少 user_id 欄位!");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'register';
            register_user();
            break;
        case 'login';
            login_user();
            break;
        case 'checkuid';
            check_uid();
            break;
        case 'checkuni';
            register_checkuni();
            break;
        case 'update';
            update_user();
            break;
        case 'checkuni_update':
            update_checkuni();
            break;
        default:
            respond(false, "無效的操作");
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'getalldata';
            get_all_user_data();
            break;
        case 'checkuni_update';
            update_checkuni();
            break;
        default:
            respond(false, "無效的操作");
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'delete';
            delete_user();
            break;
        default:
            respond(false, "無效的操作");
    }
} else {
    respond(false, "無效的請求方法!");
}
