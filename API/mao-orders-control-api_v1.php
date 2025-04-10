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
    if (! $conn) {
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

function validate_field($field, $value, $rules)
{
    foreach ($rules as $rule => $rule_value) {
        switch ($rule) {
            case "required":
                if ($rule_value && (is_null($value) || $value === "")) {
                    return "$field 不得為空";
                }
                break;
            case "min_length":
                if (strlen($value) < $rule_value) {
                    return "$field 長度不得小於 $rule_value";
                }
                break;
            case "max_length":
                if (strlen($value) > $rule_value) {
                    return "$field 長度不得超過 $rule_value";
                }
                break;
            case "positive_int":
                if (! is_numeric($value) || (int) $value <= 0) {
                    return "$field 必須為正整數";
                }
                break;
            case "phone":
                if (! preg_match("/^09\d{8}$/", $value)) {
                    return "$field 必須為有效台灣手機號碼 (09 開頭，10 位)";
                }
                break;
            case "in_array":
                if (! in_array($value, $rule_value)) {
                    return "$field 必須為以下之一: " . implode(", ", $rule_value);
                }
                break;
            case "datetime":
                $d = DateTime::createFromFormat("Y-m-d H:i:s", $value);
                if (! $d || $d->format("Y-m-d H:i:s") !== $value || $d < new DateTime()) {
                    return "$field 必須為有效未來日期 (YYYY-MM-DD HH:MM:SS)";
                }
                break;
        }
    }
    return true;
}

function update_user_order_count_and_level($conn, $user_id)
{
    $stmt = $conn->prepare("UPDATE users SET Order_count = (SELECT COUNT(*) FROM orders WHERE User_id = ?) WHERE User_id = ?");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users 
                            SET Level = CASE 
                                WHEN Order_count >= 20 THEN 30 
                                WHEN Order_count >= 10 THEN 20 
                                ELSE 10 
                            END 
                            WHERE User_id = ? AND Level != 100");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

function insert_order()
{
    $input = get_json_input();
    if (empty($input)) {
        respond(false, "無輸入資料");
        return;
    }

    if (isset($input["user_id"], $input["total_price"], $input["cart_items"]) && ! empty($input["cart_items"])) {
        $user_id          = (int) $input["user_id"];
        $total_price      = (int) $input["total_price"];
        $discount_applied = isset($input["discount_applied"]) ? (int) $input["discount_applied"] : null;
        $shipping_address = isset($input["shipping_address"]) ? trim($input["shipping_address"]) : null;
        $contact_phone    = isset($input["contact_phone"]) ? trim($input["contact_phone"]) : null;
        $payment_method   = isset($input["payment_method"]) ? trim($input["payment_method"]) : null;
        $cart_items       = $input["cart_items"];

        error_log("cart_items: " . json_encode($cart_items));
        if (empty($cart_items) || ! is_array($cart_items)) {
            respond(false, "購物車資料無效或為空");
            return;
        }

        $conn = create_connection();
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO orders (User_id, Total_price, Discount_applied, Shipping_address, Contact_phone, Payment_method) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisss", $user_id, $total_price, $discount_applied, $shipping_address, $contact_phone, $payment_method);
            $stmt->execute();
            $order_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO order_details (Order_id, Product_id, Quantity, Rental_date, Rental_end_date, Rental_days, Subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($cart_items as $index => $item) {
                $product_id      = (int) $item["product_id"];
                $quantity        = (int) $item["quantity"];
                $rental_date     = date('Y-m-d', strtotime($item["rental_date"]));
                $rental_end_date = date('Y-m-d', strtotime($item["rental_end_date"]));
                $rental_days     = (int) $item["rental_days"];
                $subtotal        = (int) $item["subtotal"];
                $stmt->bind_param("iiissii", $order_id, $product_id, $quantity, $rental_date, $rental_end_date, $rental_days, $subtotal);
                if (! $stmt->execute()) {
                    error_log("Failed to insert order detail at index $index: " . $stmt->error);
                    throw new Exception("無法插入訂單明細: " . $stmt->error);
                }
            }
            $stmt->close();

            update_user_order_count_and_level($conn, $user_id);

            $conn->commit();
            respond(true, "訂單新增成功", ["order_id" => $order_id]);
        } catch (Exception $e) {
            error_log("訂單新增失敗: " . $e->getMessage());
            $conn->rollback();
            respond(false, "訂單新增失敗: " . $e->getMessage());
        }
        $conn->close();
    } else {
        respond(false, "缺少必要欄位或購物車為空");
    }
}

function get_all_orders()
{
    $conn = create_connection();
    $sql  = "
        SELECT o.*, od.Detail_id, od.Product_id, od.Quantity, od.Rental_date, od.Rental_end_date, od.Rental_days, od.Subtotal, p.Pname as Product_name
        FROM orders o
        LEFT JOIN order_details od ON o.Order_id = od.Order_id
        LEFT JOIN products p ON od.Product_id = p.Product_id
        ORDER BY o.Order_id DESC";
    $result = mysqli_query($conn, $sql);
    error_log("SQL Query: $sql");                          
    error_log("Result rows: " . mysqli_num_rows($result));

    if (mysqli_num_rows($result) > 0) {
        $orders           = [];
        $current_order_id = null;
        while ($row = mysqli_fetch_assoc($result)) {
            if ($current_order_id != $row["Order_id"]) {
                $current_order_id          = $row["Order_id"];
                $orders[$current_order_id] = [
                    "Order_id"         => $row["Order_id"],
                    "User_id"          => $row["User_id"],
                    "Total_price"      => $row["Total_price"],
                    "Discount_applied" => $row["Discount_applied"],
                    "Shipping_address" => $row["Shipping_address"],
                    "Contact_phone"    => $row["Contact_phone"],
                    "Payment_method"   => $row["Payment_method"],
                    "Payment_status"   => $row["Payment_status"],
                    "Status"           => $row["Status"],
                    "Created_at"       => $row["Created_at"],
                    "Updated_at"       => $row["Updated_at"],
                    "details"          => [],
                ];
            }
            if ($row["Detail_id"]) {
                $orders[$current_order_id]["details"][] = [
                    "Detail_id"       => $row["Detail_id"],
                    "Product_id"      => $row["Product_id"],
                    "Quantity"        => $row["Quantity"],
                    "Rental_date"     => $row["Rental_date"],
                    "Rental_end_date" => $row["Rental_end_date"],
                    "Rental_days"     => $row["Rental_days"],
                    "Subtotal"        => $row["Subtotal"],
                    "Product_name"    => $row["Product_name"],
                ];
            }
        }
        error_log("Orders data: " . json_encode(array_values($orders)));
        respond(true, "讀取成功", array_values($orders));
    } else {
        respond(false, "無訂單資料");
    }
    mysqli_close($conn);
}

function update_order()
{
    $input = get_json_input();
    if (empty($input)) {
        $input = $_POST;
    }

    if (! isset($input["order_id"]) || ! isset($input["role"])) {
        respond(false, "缺少訂單ID或角色");
        return;
    }

    $order_id = (int) $input["order_id"];
    $role     = trim($input["role"]);
    if (validate_field("order_id", $order_id, ["positive_int" => true]) !== true) {
        respond(false, validate_field("order_id", $order_id, ["positive_int" => true]));
        return;
    }
    if (validate_field("role", $role, ["in_array" => ["user", "admin"]]) !== true) {
        respond(false, validate_field("role", $role, ["in_array" => ["user", "admin"]]));
        return;
    }

    $conn = create_connection();
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT o.Status, u.Level, u.Order_count FROM orders o JOIN users u ON o.User_id = u.User_id WHERE o.Order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order  = $result->fetch_assoc();
        $stmt->close();

        if (! $order) {
            $conn->rollback();
            respond(false, "訂單不存在");
            return;
        }

        if ($role === "user") {
            $user_fields = [];
            $user_types  = "";
            $user_values = [];

            $allowed_user_fields = [
                "shipping_address" => ["required" => true, "min_length" => 1, "max_length" => 255],
                "contact_phone"    => ["required" => true, "phone" => true, "max_length" => 20],
                "payment_method"   => ["required" => true, "in_array" => ["信用卡", "貨到付款"], "max_length" => 30],
                "status"           => ["required" => true, "in_array" => ["已下單", "已取消"], "max_length" => 30],
            ];

            foreach ($allowed_user_fields as $field => $rules) {
                if (isset($input[$field])) {
                    $validation = validate_field($field, $input[$field], $rules);
                    if ($validation !== true) {
                        $conn->rollback();
                        respond(false, $validation);
                        return;
                    }
                    if ($field === "status" && $order["Status"] !== "已下單" && $input[$field] !== "已取消") {
                        $conn->rollback();
                        respond(false, "訂單狀態只能從已下單改為已取消");
                        return;
                    }
                    $user_fields[] = "$field = ?";
                    $user_types .= "s";
                    $user_values[] = trim($input[$field]);
                }
            }

            if (! empty($user_fields)) {
                $sql = "UPDATE orders SET " . implode(", ", $user_fields) . " WHERE Order_id = ?";
                $user_types .= "i";
                $user_values[] = $order_id;
                $stmt          = $conn->prepare($sql);
                $stmt->bind_param($user_types, ...$user_values);
                $stmt->execute();
                $stmt->close();
            }

            if (isset($input["details"]) && is_array($input["details"])) {
                $stmt = $conn->prepare("SELECT Detail_id, Product_id, Quantity, Rental_date, Rental_days FROM order_details WHERE Order_id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $result          = $stmt->get_result();
                $current_details = [];
                while ($row = $result->fetch_assoc()) {
                    $current_details[$row["Detail_id"]] = $row;
                }
                $stmt->close();

                $update_stmt = $conn->prepare("UPDATE order_details SET Quantity = ?, Rental_date = ?, Rental_end_date = ?, Rental_days = ?, Subtotal = ? WHERE Detail_id = ?");
                foreach ($input["details"] as $detail) {
                    $detail_id = (int) $detail["detail_id"];
                    if (! isset($current_details[$detail_id])) {
                        $conn->rollback();
                        respond(false, "無效的明細ID: $detail_id");
                        return;
                    }

                    $quantity    = isset($detail["quantity"]) ? (int) $detail["quantity"] : $current_details[$detail_id]["Quantity"];
                    $rental_date = isset($detail["rental_date"]) ? $detail["rental_date"] : $current_details[$detail_id]["Rental_date"];
                    $rental_days = isset($detail["rental_days"]) ? (int) $detail["rental_days"] : $current_details[$detail_id]["Rental_days"];
                    $product_id  = $current_details[$detail_id]["Product_id"];

                    if (isset($detail["quantity"]) && validate_field("quantity", $quantity, ["positive_int" => true]) !== true) {
                        $conn->rollback();
                        respond(false, validate_field("quantity", $quantity, ["positive_int" => true]));
                        return;
                    }
                    if (isset($detail["rental_date"]) && validate_field("rental_date", $rental_date, ["datetime" => true]) !== true) {
                        $conn->rollback();
                        respond(false, validate_field("rental_date", $rental_date, ["datetime" => true]));
                        return;
                    }
                    if (isset($detail["rental_days"]) && validate_field("rental_days", $rental_days, ["positive_int" => true]) !== true) {
                        $conn->rollback();
                        respond(false, validate_field("rental_days", $rental_days, ["positive_int" => true]));
                        return;
                    }

                    $rental_end_date = date('Y-m-d H:i:s', strtotime("$rental_date + $rental_days days"));
                    $price_stmt      = $conn->prepare("SELECT Price FROM products WHERE Product_id = ?");
                    $price_stmt->bind_param("i", $product_id);
                    $price_stmt->execute();
                    $price_result = $price_stmt->get_result();
                    $price_row    = $price_result->fetch_assoc();
                    if (! $price_row) {
                        $conn->rollback();
                        respond(false, "商品ID $product_id 不存在");
                        return;
                    }
                    $price = $price_row["Price"];
                    $price_stmt->close();

                    $subtotal = $price * $quantity * $rental_days;
                    $update_stmt->bind_param("issiii", $quantity, $rental_date, $rental_end_date, $rental_days, $subtotal, $detail_id);
                    $update_stmt->execute();
                }
                $update_stmt->close();

                $stmt = $conn->prepare("SELECT SUM(Subtotal) as total FROM order_details WHERE Order_id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $total  = $result->fetch_assoc()["total"];
                $stmt->close();

                $discount_rate = 0;
                if ($order["Level"] === 100) {
                    $discount_rate = 1;
                } elseif ($order["Level"] === 30 && $order["Order_count"] >= 20) {
                    $discount_rate = 0.2;
                } elseif ($order["Level"] === 20 && $order["Order_count"] >= 10) {
                    $discount_rate = 0.1;
                }

                $discount_applied = (int) ($total * $discount_rate);
                $total_price      = $total - $discount_applied;

                $stmt = $conn->prepare("UPDATE orders SET Total_price = ?, Discount_applied = ? WHERE Order_id = ?");
                $stmt->bind_param("iii", $total_price, $discount_applied, $order_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        if ($role === "admin") {
            $admin_fields = [];
            $admin_types  = "";
            $admin_values = [];

            $allowed_admin_fields = [
                "payment_status" => ["required" => true, "in_array" => ["待付款", "已付款", "付款失敗"], "max_length" => 20],
                "status"         => ["required" => true, "in_array" => ["已下單", "已出貨", "已完成", "已取消"], "max_length" => 30],
            ];

            foreach ($allowed_admin_fields as $field => $rules) {
                if (isset($input[$field])) {
                    $validation = validate_field($field, $input[$field], $rules);
                    if ($validation !== true) {
                        $conn->rollback();
                        respond(false, $validation);
                        return;
                    }
                    $admin_fields[] = "$field = ?";
                    $admin_types .= "s";
                    $admin_values[] = trim($input[$field]);
                }
            }

            if (! empty($admin_fields)) {
                $sql = "UPDATE orders SET " . implode(", ", $admin_fields) . " WHERE Order_id = ?";
                $admin_types .= "i";
                $admin_values[] = $order_id;
                $stmt           = $conn->prepare($sql);
                $stmt->bind_param($admin_types, ...$admin_values);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
        respond(true, "更新成功");
    } catch (Exception $e) {
        $conn->rollback();
        respond(false, "更新失敗: " . $e->getMessage());
    }
    $conn->close();
}

function delete_order()
{
    $input = get_json_input();
    if (empty($input)) {
        $input = $_POST;
    }

    if (isset($input["order_id"])) {
        $order_id = (int) $input["order_id"];
        $conn     = create_connection();
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT User_id FROM orders WHERE Order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $conn->rollback();
                respond(false, "訂單不存在");
                return;
            }
            $user_id = $row["User_id"];

            $stmt = $conn->prepare("DELETE FROM orders WHERE Order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            if ($stmt->affected_rows === 1) {
                update_user_order_count_and_level($conn, $user_id);

                $conn->commit();
                respond(true, "刪除成功");
            } else {
                $conn->rollback();
                respond(false, "無資料被刪除");
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            respond(false, "刪除失敗: " . $e->getMessage());
        }
        $conn->close();
    } else {
        respond(false, "缺少訂單ID");
    }
}

function get_user_data()
{
    $input = get_json_input();
    if (empty($input) || ! isset($input["user_id"])) {
        respond(false, "缺少用戶 ID");
        return;
    }

    $user_id = (int) $input["user_id"];
    $conn    = create_connection();

    $stmt = $conn->prepare("SELECT User_id, Username, Email, Region, Userphoto, Level, Order_count, Uid01 FROM users WHERE User_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    if ($user) {
        respond(true, "用戶資料獲取成功", $user);
    } else {
        respond(false, "用戶不存在");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'insert':
            insert_order();
            break;
        case 'update':
            update_order();
            break;
        case 'get_user_data':
            get_user_data();
            break;
        default:
            respond(false, "無效的操作");
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'getalldata':
            get_all_orders();
            break;
        default:
            respond(false, "無效的操作");
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'delete':
            delete_order();
            break;
        default:
            respond(false, "無效的操作");
    }
} else {
    respond(false, "無效的請求方法!");
}
?>