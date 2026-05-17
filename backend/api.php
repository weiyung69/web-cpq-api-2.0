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
$allowedFiles = ['orders', 'cpq', 'langs']; 

if (!in_array($type, $allowedFiles)) {
    echo json_encode(["error" => "Invalid type"]);
    exit(http_response_code(403));
}

$dataFile = $dataDir . "/" . $type . ".json";
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

/**
 * 💡 默认 UI 翻译模板（基于 1.0 提取）
 */
$uiTemplate = [
    "en" => [
        "UI_TITLE" => "✨ Instant Quote", "UI_NAME_LABEL" => "👤 Full Name", "UI_PHONE_LABEL" => "📞 Phone Number",
        "UI_EMAIL_LABEL" => "📧 Email Address", "UI_SUBMIT" => "🚀 Submit Request", "UI_TOTAL" => "💰 Total Amount",
        "UI_SELECT_TYPE" => "👤 Identity", "UI_SELECT_PLAN" => "📋 Service Plan", "UI_SELECT_OPTION" => "⚙️ Selection",
        "UI_SUCCESS" => "✅ Submitted Successfully!", "CAT_PERSONAL" => "👤 Personal Class", "SEG_YOUTH" => "👱 Adult"
    ],
    "cn" => [
        "UI_TITLE" => "✨ 即时报价", "UI_NAME_LABEL" => "👤 您的姓名", "UI_PHONE_LABEL" => "📞 联系电话",
        "UI_EMAIL_LABEL" => "📧 电子邮箱", "UI_SUBMIT" => "🚀 提交申请", "UI_TOTAL" => "💰 总计费用",
        "UI_SELECT_TYPE" => "👤 身份类别", "UI_SELECT_PLAN" => "📋 服务计划", "UI_SELECT_OPTION" => "⚙️ 具体选项",
        "UI_SUCCESS" => "✅ 提交成功！", "CAT_PERSONAL" => "👤 私人课", "SEG_YOUTH" => "👱 青年人"
    ],
    "ms" => [
        "UI_TITLE" => "✨ Sebut Harga Segera", "UI_NAME_LABEL" => "👤 Nama Penuh", "UI_PHONE_LABEL" => "📞 Nombor Telefon",
        "UI_EMAIL_LABEL" => "📧 Alamat Emel", "UI_SUBMIT" => "🚀 Hantar Permohonan", "UI_TOTAL" => "💰 Jumlah Keseluruhan",
        "UI_SELECT_TYPE" => "👤 Kategori", "UI_SELECT_PLAN" => "📋 Pelan Perkhidmatan", "UI_SELECT_OPTION" => "⚙️ Pilihan",
        "UI_SUCCESS" => "✅ Berjaya Dihantar!", "CAT_PERSONAL" => "👤 Kelas Peribadi", "SEG_YOUTH" => "👱 Dewasa"
    ]
];

/**
 * 💡 1.0 标准初始数据结构
 */
$defaults = [
    'cpq' => [
        "settings" => [
            "langs" => ["en-sgd", "ms-myr", "cn-rmb"],
            "currency" => "$", "tax_rate" => 6, "brand_name" => "Golf Training 2.0",
            "notes" => ["langs" => "❗CAT_ and SEG_ base", "catalog" => "Items", "orders" => "remark"]
        ],
        "catalog" => [
            "prod_001" => [
                "i18n" => "CAT_PERSONAL", "sort" => 1,
                "segments" => ["SEG_YOUTH"],
                "items" => ["⏱️ 10 class" => 4500, "⏱️ 1 class" => 500]
            ]
        ]
    ],
    'langs' => [
        "en-sgd" => $uiTemplate['en'],
        "ms-myr" => $uiTemplate['ms'],
        "cn-rmb" => $uiTemplate['cn']
    ],
    'orders' => []
];

// --- 处理 GET ---
if ($method === "GET") {
    if (!file_exists($dataFile)) {
        $content = $defaults[$type];
    } else {
        $content = json_decode(file_get_contents($dataFile), true);
        if (!$content || isset($content['_init'])) { $content = $defaults[$type]; }
        if ($type === 'cpq' && is_array($content)) {
            if (!isset($content['settings'])) $content['settings'] = $defaults['cpq']['settings'];
            if (!isset($content['catalog'])) $content['catalog'] = $defaults['cpq']['catalog'];
        }
    }
    echo json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 处理 POST ---
if ($method === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) { echo json_encode(["error" => "Invalid JSON"]); exit(http_response_code(400)); }

    if ($type === 'cpq' || $type === 'langs') {
        $finalData = $input;
    } else if ($type === 'orders') {
        if (isset($input[0])) {
            $finalData = $input;
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
        echo json_encode(["error" => "Write failed"]); exit(http_response_code(500));
    }
    exit;
}