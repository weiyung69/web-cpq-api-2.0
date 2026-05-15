<?php
error_reporting(0);
ini_set('display_errors', 0);

// --- CORS & Headers ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit(http_response_code(200)); }

$dataDir = __DIR__ . "/data";
$method  = $_SERVER["REQUEST_METHOD"];
$type = isset($_GET['type']) ? $_GET['type'] : 'orders';

/**
 * 💡 1. 扩展允许的文件类型：增加 'langs'
 */
$allowedFiles = ['orders', 'cpq', 'langs']; 

if (!in_array($type, $allowedFiles)) {
    echo json_encode(["error" => "Invalid type"]);
    exit(http_response_code(403));
}

$dataFile = $dataDir . "/" . $type . ".json";
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

// 初始化文件：如果是 orders 存 [], 如果是 cpq 或 langs 存 {}
if (!file_exists($dataFile)) {
    $initial = ($type === 'orders') ? [] : ["_init" => true];
    file_put_contents($dataFile, json_encode($initial));
}

// --- 处理 GET ---
if ($method === "GET") {
    echo file_get_contents($dataFile) ?: json_encode([]);
    exit;
}

// --- 处理 POST ---
if ($method === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        echo json_encode(["error" => "Invalid JSON"]);
        exit(http_response_code(400));
    }

    if ($type === 'cpq' || $type === 'langs') {
        /**
         * 💡 2. 策略与语言包永远执行 [全量覆盖]
         * 这样 Admin 无论修改了哪个词或哪个价格，直接一键同步全量数据。
         */
        $finalData = $input;
    } else if ($type === 'orders') {
        if (isset($input[0])) {
            $finalData = $input; // Admin 批量更新订单状态
        } else {
            $currentData = json_decode(file_get_contents($dataFile), true) ?: [];
            $input["server_time"] = date("Y-m-d H:i:s");
            $input["follow_up"] = false; 
            $currentData[] = $input;
            $finalData = $currentData;
        }
    }

    if (file_put_contents($dataFile, json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX)) {
        echo json_encode(["status" => "success", "type" => $type]);
    } else {
        echo json_encode(["error" => "Write failed"]);
        exit(http_response_code(500));
    }
    exit;
}