<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

const DB_SERVER   = "sql102.infinityfree.com";
const DB_USERNAME = "if0_38646811";
const DB_PASSWORD = "CcYy1108";
const DB_NAME     = "if0_38646811_cy_mao";

function create_connection()
{
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if (!$conn) {
        respond(false, "連線失敗: " . mysqli_connect_error());
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

function upload_product_photo()
{
    if (!isset($_FILES['file']) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
        return ["state" => false, "message" => "沒有上傳檔案或上傳錯誤"];
    }

    $allowed_types = ["image/jpeg", "image/png", "image/jpg"];
    $max_file_size = 20 * 1024 * 1024; // 20MB
    $upload_dir = "uploads/";
    $file_name = $_FILES["file"]["name"];
    $file_tmp = $_FILES["file"]["tmp_name"];
    $file_size = $_FILES["file"]["size"];
    $file_type = $_FILES["file"]["type"];

    if (!in_array($file_type, $allowed_types)) {
        return ["state" => false, "message" => "不支持的檔案類型"];
    }
    if ($file_size > $max_file_size) {
        return ["state" => false, "message" => "檔案過大"];
    }
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = date("YmdHis") . "_" . $file_name;
    $target_file = $upload_dir . $filename;

    if (move_uploaded_file($file_tmp, $target_file)) {
        return [
            "state" => true,
            "message" => "檔案上傳成功",
            "name" => $filename,
            "location" => $target_file
        ];
    } else {
        return ["state" => false, "message" => "檔案儲存失敗"];
    }
}

function insert_product()
{
    $input = get_json_input();
    if (empty($input)) {
        $input = $_POST;
    }

    if (isset($input["ptype"], $input["pname"], $input["price"], $input["premark"], $input["pstatus"])) {
        $p_ptype = trim($input["ptype"]);
        $p_pname = trim($input["pname"]);
        $p_price = (int)trim($input["price"]);
        $p_premark = trim($input["premark"]);
        $p_pstatus = trim($input["pstatus"]);
        $p_photo = isset($input["photo"]) ? trim($input["photo"]) : null;

        if ($p_ptype && $p_pname && $p_price && $p_pstatus) {
            $conn = create_connection();

            if (!empty($_FILES['file']['name'])) {
                $upload_result = upload_product_photo();
                if (!$upload_result["state"]) {
                    respond(false, $upload_result["message"]);
                    return;
                }
                $p_photo = $upload_result["name"];
            }

            $stmt = $conn->prepare("INSERT INTO products (Ptype, Pname, Price, Photo, Premark, Pstatus) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisss", $p_ptype, $p_pname, $p_price, $p_photo, $p_premark, $p_pstatus);

            if ($stmt->execute()) {
                respond(true, "新增成功");
            } else {
                respond(false, "新增失敗: " . $stmt->error);
            }
            $stmt->close();
            $conn->close();
        } else {
            respond(false, "欄位不得為空白");
        }
    } else {
        respond(false, "欄位錯誤");
    }
}

function get_all_products()
{
    $conn = create_connection();
    $sql = "SELECT Product_id, Ptype, Pname, Price, Photo, Premark, Pstatus, Created_at FROM products ORDER BY Product_id DESC";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $mydata = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $mydata[] = $row;
        }
        respond(true, "讀取成功", $mydata);
    } else {
        respond(false, "讀取失敗");
    }
    mysqli_close($conn);
}

function update_product()
{
    $input = $_POST; 
    if (isset($input["product_id"], $input["pname"], $input["ptype"], $input["price"], $input["pstatus"])) {
        $product_id = trim($input["product_id"]);
        $p_pname = trim($input["pname"]);
        $p_ptype = trim($input["ptype"]);
        $p_price = (int)trim($input["price"]);
        $p_premark = isset($input["premark"]) ? trim($input["premark"]) : null;
        $p_pstatus = trim($input["pstatus"]);
        $p_photo = null;

        if ($product_id && $p_pname && $p_ptype && $p_price && $p_pstatus) {
            $conn = create_connection();

            $stmt = $conn->prepare("SELECT Photo FROM products WHERE Product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $stmt->bind_result($oldPhoto);
            $stmt->fetch();
            $stmt->close();

            if (!empty($_FILES['file']['name'])) {
                $upload_result = upload_product_photo();
                if (!$upload_result["state"]) {
                    respond(false, $upload_result["message"]);
                    return;
                }
                $p_photo = $upload_result["name"];
                if ($oldPhoto && file_exists("uploads/" . $oldPhoto)) {
                    unlink("uploads/" . $oldPhoto); 
                }
            }

            if ($p_photo) {
                $stmt = $conn->prepare("UPDATE products SET Pname=?, Ptype=?, Price=?, Premark=?, Pstatus=?, Photo=? WHERE Product_id=?");
                $stmt->bind_param("ssisssi", $p_pname, $p_ptype, $p_price, $p_premark, $p_pstatus, $p_photo, $product_id);
            } else {
                $stmt = $conn->prepare("UPDATE products SET Pname=?, Ptype=?, Price=?, Premark=?, Pstatus=? WHERE Product_id=?");
                $stmt->bind_param("ssissi", $p_pname, $p_ptype, $p_price, $p_premark, $p_pstatus, $product_id);
            }

            if ($stmt->execute()) {
                respond(true, "更新成功");
            } else {
                respond(false, "更新失敗: " . $stmt->error);
            }
            $stmt->close();
            $conn->close();
        } else {
            respond(false, "缺少必要欄位");
        }
    } else {
        respond(false, "欄位錯誤");
    }
}

function delete_product()
{
    $input = get_json_input();
    if (empty($input)) {
        $input = $_POST; 
    }

    if (isset($input["id"])) {
        $p_id = trim($input["id"]);
        if ($p_id) {
            $conn = create_connection();

            $stmt = $conn->prepare("SELECT Photo FROM products WHERE Product_id = ?");
            $stmt->bind_param("i", $p_id);
            $stmt->execute();
            $stmt->bind_result($photo);
            $stmt->fetch();
            $stmt->close();

            if ($photo && file_exists("uploads/" . $photo)) {
                unlink("uploads/" . $photo);
            }

            $stmt = $conn->prepare("DELETE FROM products WHERE Product_id = ?");
            $stmt->bind_param("i", $p_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows === 1) {
                    respond(true, "刪除成功");
                } else {
                    respond(false, "無資料被刪除");
                }
            } else {
                respond(false, "刪除失敗: " . $stmt->error);
            }
            $stmt->close();
            $conn->close();
        } else {
            respond(false, "欄位不得為空白");
        }
    } else {
        respond(false, "欄位錯誤");
    }
}

function check_unique_pname()
{
    $input = get_json_input();
    if (isset($input["pname"])) {
        $p_pname = trim($input["pname"]);
        if ($p_pname) {
            $conn = create_connection();
            $stmt = $conn->prepare("SELECT Pname FROM products WHERE Pname = ?");
            $stmt->bind_param("s", $p_pname);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                respond(false, "該產品名稱已經存在不可以使用");
            } else {
                respond(true, "該產品名稱不存在可以使用");
            }
            $stmt->close();
            $conn->close();
        } else {
            respond(false, "欄位不得為空白");
        }
    } else {
        respond(false, "欄位錯誤");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'insert':
            insert_product();
            break;
        case 'update':
            update_product();
            break;
        case 'check_pname':
            check_unique_pname();
            break;
        default:
            respond(false, "無效的操作");
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'getalldata':
            get_all_products();
            break;
        default:
            respond(false, "無效的操作");
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'delete':
            delete_product();
            break;
        default:
            respond(false, "無效的操作");
    }
} else {
    respond(false, "無效的請求方法!");
}
?>