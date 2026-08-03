<?php
/**
 * Environment and Server Test
 * Tests .env loading, API key retrieval, and server connectivity
 */

require_once __DIR__ . '/../../includes/env.php';

header('Content-Type: application/json; charset=utf-8');

$results = [
  'timestamp' => date('Y-m-d H:i:s'),
  'tests' => []
];

// Test 1: .env file loading
try {
  $env = load_env();
  $results['tests']['env_loading'] = [
    'status' => 'pass',
    'message' => '.env file loaded successfully',
    'path' => dirname($_SERVER['DOCUMENT_ROOT']) . '/private/.env'
  ];
} catch (Exception $e) {
  $results['tests']['env_loading'] = [
    'status' => 'fail',
    'message' => $e->getMessage()
  ];
}

// Test 2: OpenAI API key retrieval
try {
  $key = get_openai_key();
  $keyPreview = substr($key, 0, 10) . '...' . substr($key, -4);
  $results['tests']['api_key'] = [
    'status' => 'pass',
    'message' => 'API key retrieved successfully',
    'key_preview' => $keyPreview,
    'length' => strlen($key)
  ];
} catch (Exception $e) {
  $results['tests']['api_key'] = [
    'status' => 'fail',
    'message' => $e->getMessage()
  ];
}

// Test 3: Model configuration
try {
  $imageModel = get_image_model();
  $textModel = get_text_model();
  $maxTokens = get_max_tokens();
  $temperature = get_temperature();
  
  $results['tests']['model_config'] = [
    'status' => 'pass',
    'message' => 'Model configuration loaded',
    'image_model' => $imageModel,
    'text_model' => $textModel,
    'max_tokens' => $maxTokens,
    'temperature' => $temperature
  ];
} catch (Exception $e) {
  $results['tests']['model_config'] = [
    'status' => 'fail',
    'message' => $e->getMessage()
  ];
}

// Test 4: Server connectivity (test API call to OpenAI)
if (isset($key) && $key) {
  try {
    $ch = curl_init('https://api.openai.com/v1/models');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $key,
      ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
      throw new Exception('cURL error: ' . $curlError);
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
      $data = json_decode($response, true);
      $modelCount = isset($data['data']) ? count($data['data']) : 0;
      
      $results['tests']['api_connectivity'] = [
        'status' => 'pass',
        'message' => 'Successfully connected to OpenAI API',
        'http_code' => $httpCode,
        'models_available' => $modelCount
      ];
    } else {
      throw new Exception('HTTP ' . $httpCode . ': ' . substr($response, 0, 200));
    }
  } catch (Exception $e) {
    $results['tests']['api_connectivity'] = [
      'status' => 'fail',
      'message' => $e->getMessage()
    ];
  }
} else {
  $results['tests']['api_connectivity'] = [
    'status' => 'skip',
    'message' => 'Skipped - API key not available'
  ];
}

// Calculate overall status
$allPassed = true;
foreach ($results['tests'] as $test) {
  if ($test['status'] === 'fail') {
    $allPassed = false;
    break;
  }
}

$results['overall'] = $allPassed ? 'pass' : 'fail';
$results['summary'] = $allPassed 
  ? 'All tests passed! Server is ready for Card-o-Bot.' 
  : 'Some tests failed. Check individual test results.';

// Set HTTP status code
http_response_code($allPassed ? 200 : 500);

echo json_encode($results, JSON_PRETTY_PRINT);
