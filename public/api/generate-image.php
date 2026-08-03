<?php
/**
 * Generate Image API Endpoint
 * Uses OpenAI image generation (chatgpt-image-latest or dall-e-3)
 */

require_once __DIR__ . '/../includes/env.php';

header('Content-Type: application/json; charset=utf-8');

// Get request data
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
  $data = [];
}

$prompt = trim($data['prompt'] ?? '');
$model = trim($data['model'] ?? get_image_model());
$size = trim($data['size'] ?? '1024x1024');
$quality = trim($data['quality'] ?? 'high');

// Validate prompt
if (empty($prompt)) {
  http_response_code(400);
  echo json_encode(['error' => 'prompt is required']);
  exit;
}

// Validate size
$validSizes = ['1024x1024', '1792x1024', '1024x1792'];
if (!in_array($size, $validSizes)) {
  $size = '1024x1024'; // Default to safe size
}

// Validate quality
$validQualities = ['low', 'medium', 'high', 'auto'];
if (!in_array($quality, $validQualities)) {
  $quality = 'high';
}

// Get API key
$key = get_openai_key();

// Build payload
$payload = [
  'model' => $model,
  'prompt' => $prompt,
  'size' => $size,
  'quality' => $quality,
  'n' => 1 // Number of images to generate
];

// Make API request
$ch = curl_init('https://api.openai.com/v1/images/generations');
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 60, // Image generation can take time
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $key,
    'Content-Type: application/json',
  ],
  CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle errors
if ($curlError) {
  http_response_code(500);
  echo json_encode(['error' => 'cURL error: ' . $curlError]);
  exit;
}

// Set HTTP status code
http_response_code($httpCode);

// Return response
if ($httpCode >= 200 && $httpCode < 300) {
  echo $response;
} else {
  // Try to parse error from response
  $errorData = json_decode($response, true);
  $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
  echo json_encode([
    'error' => $errorMessage,
    'http_code' => $httpCode,
    'raw_response' => $response
  ]);
}
