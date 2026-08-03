<?php
/**
 * Simple test page for image generation API
 * Visit: https://yourdomain.com/cardobot/test-image.php
 */
require_once __DIR__ . '/../../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

$assetPath = get_asset_path();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Image Generation</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="test-image-page">
    <div class="container test-image-container">
        <div class="admin-section">
            <div class="admin-section-header">
                <h1>🧪 Test Image Generation API</h1>
            </div>
            <div class="admin-section-body">
                <form id="testForm">
            <div class="form-group">
                <label for="prompt">Prompt:</label>
                <textarea id="prompt" required>A friendly robot character for a trading card, colorful, detailed</textarea>
            </div>
            
            <div class="form-group">
                <label for="model">Model:</label>
                <select id="model">
                    <option value="">Use default (chatgpt-image-latest)</option>
                    <option value="chatgpt-image-latest">chatgpt-image-latest</option>
                    <option value="gpt-image-1.5">gpt-image-1.5</option>
                    <option value="dall-e-3">dall-e-3</option>
                    <option value="dall-e-2">dall-e-2</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="size">Size:</label>
                <select id="size">
                    <option value="1024x1024">1024x1024 (Square)</option>
                    <option value="1792x1024">1792x1024 (Landscape)</option>
                    <option value="1024x1792">1024x1792 (Portrait)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="quality">Quality:</label>
                <select id="quality">
                    <option value="high" selected>High (Recommended)</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                    <option value="auto">Auto</option>
                </select>
            </div>
            
                    <button type="submit" id="submitBtn" class="btn btn-primary">Generate Image</button>
                </form>
                
                <div id="loadingBar">
            <div class="progress-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div class="loading-text">
                <span class="spinner"></span>
                <span id="loadingMessage">Generating your image... This may take 10-30 seconds</span>
            </div>
                </div>
                
                <div id="result"></div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('testForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const resultDiv = document.getElementById('result');
            
            // Get form values
            const prompt = document.getElementById('prompt').value.trim();
            const model = document.getElementById('model').value;
            const size = document.getElementById('size').value;
            const quality = document.getElementById('quality').value;
            
            // Build payload
            const payload = { prompt, size, quality };
            if (model) payload.model = model;
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.textContent = 'Generating...';
            resultDiv.classList.remove('visible');
            
            // Show loading bar
            const loadingBar = document.getElementById('loadingBar');
            const progressBar = document.getElementById('progressBar');
            const loadingMessage = document.getElementById('loadingMessage');
            loadingBar.classList.add('active');
            
            // Initialize progress bar
            progressBar.style.width = '5%';
            
            // Update loading message periodically (without adding duplicate spinner)
            const messages = [
                'Generating your image... This may take 10-30 seconds',
                'Creating artwork... Please wait',
                'Processing your request... Almost there',
                'Finalizing image... Just a moment'
            ];
            let messageIndex = 0;
            const startTime = Date.now();
            const messageInterval = setInterval(() => {
                messageIndex = (messageIndex + 1) % messages.length;
                loadingMessage.textContent = messages[messageIndex];
                
                // Update progress based on elapsed time (more realistic)
                const elapsed = (Date.now() - startTime) / 1000; // seconds
                if (elapsed < 5) {
                    progressBar.style.width = Math.min(20 + (elapsed * 5), 40) + '%';
                } else if (elapsed < 15) {
                    progressBar.style.width = Math.min(40 + ((elapsed - 5) * 3), 85) + '%';
                } else {
                    progressBar.style.width = Math.min(85 + ((elapsed - 15) * 0.5), 95) + '%';
                }
            }, 2000);
            
            try {
                const response = await fetch('../../api/generate-image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const data = await response.json();
                
                if (response.ok && data.data && data.data[0]) {
                    // Success - check for URL or base64
                    const imageData = data.data[0];
                    let imageSrc = '';
                    let imageInfo = '';
                    
                    if (imageData.url) {
                        // URL-based image (DALL-E 2/3)
                        imageSrc = imageData.url;
                        imageInfo = `<small>Image URL: <a href="${imageData.url}" target="_blank">${imageData.url}</a></small>`;
                    } else if (imageData.b64_json) {
                        // Base64-encoded image (chatgpt-image-latest)
                        imageSrc = 'data:image/png;base64,' + imageData.b64_json;
                        imageInfo = '<small>Image format: Base64 (PNG)</small>';
                    }
                    
                    if (imageSrc) {
                        resultDiv.className = 'success';
                        resultDiv.innerHTML = `
                            <strong>✅ Success!</strong><br>
                            <img src="${imageSrc}" alt="Generated image"><br>
                            ${imageInfo}
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        `;
                    } else {
                        // No image data found
                        resultDiv.className = 'error';
                        resultDiv.innerHTML = `
                            <strong>❌ Error: No image data found</strong><br>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        `;
                    }
                } else {
                    // Error
                    resultDiv.className = 'error';
                    resultDiv.innerHTML = `
                        <strong>❌ Error (HTTP ${response.status})</strong><br>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'error';
                resultDiv.innerHTML = `
                    <strong>❌ Network Error</strong><br>
                    <pre>${error.message}</pre>
                `;
            } finally {
                // Hide loading bar and complete progress
                clearInterval(messageInterval);
                progressBar.style.width = '100%';
                setTimeout(() => {
                    loadingBar.classList.remove('active');
                    progressBar.style.width = '0%';
                }, 300);
                
                resultDiv.classList.add('visible');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Generate Image';
            }
        });
    </script>
</body>
</html>
