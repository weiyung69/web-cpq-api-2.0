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
 * 💡 默认 UI 翻译模板 (万能行业版)
 * 包含了你 1.0 的所有核心 UI 键值，确保前端不报错
 */
$uiTemplate = [
    "en" => [
        "UI_TITLE" => "✨ Instant Quote", 
        "UI_NAME_LABEL" => "👤 Full Name", 
        "UI_PHONE_LABEL" => "📞 Phone Number",
        "UI_EMAIL_LABEL" => "📧 Email Address", 
        "UI_SUBMIT" => "🚀 Submit Request", 
        "UI_TOTAL" => "💰 Total Amount",
        "UI_SELECT_TYPE" => "⚙️ Type", 
        "UI_SELECT_PLAN" => "📋 Product", 
        "UI_SELECT_OPTION" => "📝 Options",
        "UI_SUCCESS" => "✅ Submitted Successfully!", 
        "CAT_PRODUCT" => "📦 Standard Product", 
        "SEG_TYPE" => "⚙️ General Type"
    ],
    "cn" => [
        "UI_TITLE" => "✨ 即时报价", 
        "UI_NAME_LABEL" => "👤 您的姓名", 
        "UI_PHONE_LABEL" => "📞 联系电话",
        "UI_EMAIL_LABEL" => "📧 电子邮箱", 
        "UI_SUBMIT" => "🚀 提交申请", 
        "UI_TOTAL" => "💰 总计费用",
        "UI_SELECT_TYPE" => "⚙️ 类型选择", 
        "UI_SELECT_PLAN" => "📋 项目选择", 
        "UI_SELECT_OPTION" => "📝 具体选项",
        "UI_SUCCESS" => "✅ 提交成功！", 
        "CAT_PRODUCT" => "📦 标配项目", 
        "SEG_TYPE" => "⚙️ 常规类型"
    ],
    "ms" => [
        "UI_TITLE" => "✨ Sebut Harga Segera", 
        "UI_NAME_LABEL" => "👤 Nama Penuh", 
        "UI_PHONE_LABEL" => "📞 Nombor Telefon",
        "UI_EMAIL_LABEL" => "📧 Alamat Emel", 
        "UI_SUBMIT" => "🚀 Hantar Permohonan", 
        "UI_TOTAL" => "💰 Jumlah Keseluruhan",
        "UI_SELECT_TYPE" => "⚙️ Kategori", 
        "UI_SELECT_PLAN" => "📋 Pelan Perkhidmatan", 
        "UI_SELECT_OPTION" => "⚙️ Pilihan",
        "UI_SUCCESS" => "✅ Berjaya Dihantar!", 
        "CAT_PRODUCT" => "📦 Produk Standar", 
        "SEG_TYPE" => "⚙️ Jenis Biasa"
    ]
];

/**
 * 💡 2.0 万能行业初始数据结构
 */
$defaults = [
    'cpq' => [
        "settings" => [
            "langs" => ["en-sgd", "ms-myr", "cn-rmb"],
            "currency" => "$", 
            "tax_rate" => 0, 
            "brand_name" => "Ali Sdn Bhd",
            "notes" => [
                "langs" => "❗ Setup country-currency", 
                "catalog" => "❗ CAT_PRODUCT and SEG_TYPE base", 
                "orders" => "remark"
            ]
        ],
        "catalog" => [
            "item_001" => [
                "i18n" => "CAT_PRODUCT", 
                "sort" => 1,
                "segments" => ["SEG_TYPE"],
                "items" => [
                    "Option 1" => 100, 
                    "Option 2" => 200
                ]
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
        
        // 核心防护：如果数据损坏或仍是旧的 _init 标志，强制重置为默认值
        if (!$content || isset($content['_init'])) { 
            $content = $defaults[$type]; 
        }
        
        // 自动补全逻辑：如果是 cpq，确保即便有部分数据，也能补齐缺失的 settings 或 catalog
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
    if (!$input) { 
        echo json_encode(["error" => "Invalid JSON"]); 
        exit(http_response_code(400)); 
    }

    if ($type === 'cpq' || $type === 'langs') {
        // Admin 后台一键同步：全量覆盖存储
        $finalData = $input;
    } else if ($type === 'orders') {
        if (isset($input[0])) {
            $finalData = $input; // Admin 批量管理订单
        } else {
            // 客户提交新订单：增量追加
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