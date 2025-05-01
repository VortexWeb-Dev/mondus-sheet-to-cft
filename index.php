<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data['data']['id']) || empty($data['data']['created_time']) || empty($data['data']['ad_id'])) {
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

$fields = [
    'uf_crm_sheet_id' => $data['data']['id'] ?? '',
    'uf_crm_created_time' => $data['data']['created_time'] ?? '',
    'uf_crm_ad_id' => $data['data']['ad_id'] ?? '',
    'uf_crm_ad_name' => $data['data']['ad_name'] ?? '',
    'uf_crm_adset_id' => $data['data']['adset_id'] ?? '',
    'uf_crm_adset_name' => $data['data']['adset_name'] ?? '',
    'uf_crm_campaign_id' => $data['data']['campaign_id'] ?? '',
    'uf_crm_campaign_name' => $data['data']['campaign_name'] ?? '',
    'uf_crm_form_id' => $data['data']['form_id'] ?? '',
    'uf_crm_form_name' => $data['data']['form_name'] ?? '',
    'uf_crm_is_organic' => $data['data']['is_organic'] ?? '',
    'uf_crm_platform' => $data['data']['platform'] ?? '',
    'uf_crm_full_name' => $data['data']['full_name'] ?? '',
    'uf_crm_phone_number' => $data['data']['phone_number'] ?? '',
    'uf_crm_email' => $data['data']['email'] ?? '',
    'uf_crm_city' => $data['data']['city'] ?? '',
    'uf_crm_is_qualified' => $data['data']['is_qualified'] ?? '',
    'uf_crm_is_quality' => $data['data']['is_quality'] ?? '',
    'uf_crm_is_converted' => $data['data']['is_converted'] ?? '',
    'uf_crm_followup_1' => $data['data']['FOLLOW UP 1'] ?? '',

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
