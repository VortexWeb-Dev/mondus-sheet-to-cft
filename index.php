<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data['created_time']) || empty($data['campaign_name']) || empty($data['full_name']) || empty($data['phone_number'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$logDir = __DIR__ . '/logs/' . date('Y/m/d');
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$logFile = $logDir . '/payload.log';

file_put_contents($logFile, json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data
], JSON_PRETTY_PRINT) . PHP_EOL . str_repeat('-', 80) . PHP_EOL, FILE_APPEND);

require_once __DIR__ . '/crest/crest.php';

define('CFT_SPA_ENTITY_TYPE_ID', 1036);
define('CFT_LEADS_PIPELINE_ID', 6);
define('CFT_DEALS_PIPELINE_ID', 7);
define('DEFAULT_RESPONSIBLE_USER_ID', 1);
define('META_SHEET_SOURCE_ID', 'UC_Y8VFDX');

$contact_fields = [
    'NAME' => $data['full_name'],
    'PHONE' => [
        [
            'VALUE' => $data['phone_number'],
            'VALUE_TYPE' => 'WORK'
        ]
    ]
];

$response = CRest::call(
    'crm.contact.add',
    [
        'fields' => $contact_fields
    ]
);

$contactId = $response['result'] ?? null;

$fields = [
    'title' => "{$data['campaign_name']} - {$data['full_name']} - META SHEET",
    'ufCrm3_1746081027670' => $data['created_time'] ?? '',
    'ufCrm3_1746081053233' => $data['campaign_name'] ?? '',
    'ufCrm3_1746081086144' => $data['full_name'] ?? '',
    'ufCrm3_1746081096058' => $data['phone_number'] ?? '',

    'contactId' => $contactId,
    'sourceId' => META_SHEET_SOURCE_ID,
    'assignedById' => DEFAULT_RESPONSIBLE_USER_ID,
    'categoryId' => CFT_LEADS_PIPELINE_ID
];

$response = CRest::call(
    'crm.item.add',
    [
        'entityTypeId' => CFT_SPA_ENTITY_TYPE_ID,
        'fields' => $fields
    ]
);

if (isset($response['result'])) {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'SPA item added successfully',
        'bitrix_item_id' => $response['result']['item']['id'] ?? null
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to add SPA item',
        'error_description' => $response['error_description'] ?? 'Unknown error',
        'error' => $response['error'] ?? null
    ]);
}
