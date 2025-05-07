<?php
require_once __DIR__ . '/crest/crest.php';

function getAgentId($campaign_name)
{
    $config = require __DIR__ . '/config.php';

    $campaignMapping = $config['CAMPAIGN_DEPT_MAPPING'];
    $deptDetails = $config['DEPT_IDS'];

    $campaign_name_input = strtolower(trim($campaign_name));
    $campaign_name_input_nospace = preg_replace('/\s+/', '', $campaign_name_input);

    $mappedKey = null;

    // Match campaign with mapping
    foreach ($campaignMapping as $key => $labels) {
        $labels = (array) $labels; // Ensure it's an array
        foreach ($labels as $label) {
            $label_normalized = preg_replace('/\s+/', '', strtolower($label));
            if (strpos($campaign_name_input_nospace, $label_normalized) !== false) {
                $mappedKey = $key;
                break 2;
            }
        }
    }

    // Fallback if no match
    if (!$mappedKey || !isset($deptDetails[$mappedKey])) {
        return $config['DEFAULT_RESPONSIBLE_USER_ID'];
    }

    $deptId = $mappedKey;
    $headId = $deptDetails[$deptId]['HEAD_ID'] ?? null;
    $headMaxLeads = $deptDetails[$deptId]['HEAD_MAX_LEADS'] ?? 0;

    if (!$headId) {
        return $config['DEFAULT_RESPONSIBLE_USER_ID'];
    }

    $agentIds = getAgentIds($deptDetails[$deptId]['DEPT_ID']);

    // Check if head is under max leads
    $headAvailable = isAvailable($headId);
    $headLeadsCount = getLeadCount($headId, 'today', $config);

    if ($headAvailable && $headLeadsCount < $headMaxLeads) {
        return $headId;
    }

    // Filter other available agents (exclude head)
    $availableAgents = array_values(array_filter($agentIds, function ($id) use ($headId) {
        return $id !== $headId && isAvailable($id);
    }));

    if (empty($availableAgents)) {
        return $config['DEFAULT_RESPONSIBLE_USER_ID'];
    }

    // Round-robin assignment
    $indexFile = __DIR__ . '/round_robin_index.json';
    $indexData = is_file($indexFile) ? json_decode(file_get_contents($indexFile), true) : [];

    $currentIndex = $indexData[$deptId] ?? 0;
    $assignedAgent = $availableAgents[$currentIndex % count($availableAgents)];

    $indexData[$deptId] = $currentIndex + 1;
    file_put_contents($indexFile, json_encode($indexData));

    return $assignedAgent;
}

function getAgentIds($deptId)
{
    $response = CRest::call(
        'user.get',
        [
            'filter' => [
                'UF_DEPARTMENT' => $deptId
            ],
            'select' => ['ID']
        ]
    );

    return array_column($response['result'], 'ID');
}

function isAvailable($agentId)
{
    $response = CRest::call('timeman.status', [
        'USER_ID' => $agentId
    ]);

    return $response['result']['STATUS'] === 'OPEN';
}

function getLeadCount($agentId, $date, $config)
{
    $date = $date === 'today' ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));
    $response = CRest::call('crm.item.list', [
        'entityTypeId' => $config['CFT_SPA_ENTITY_TYPE_ID'],
        'filter' => [
            'assignedById' => $agentId,
            '>createdTime' => $date
        ]
    ]);

    return $response['total'] ?? 0;
}
