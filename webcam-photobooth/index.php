<?php
// Ensure photos folder exists
if (!is_dir('photos')) mkdir('photos', 0777, true);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSC Photobooth</title>
    <script src="webcam.min.js"></script>
    <link rel="stylesheet" href="style.css?v=3">
    <link rel="icon" type="image/png" href="resources/MSC%20Logo%20Transparent.png">
</head>
<body>
    <!-- Banner -->
    <div class="banner">
        <div class="banner-content">
            <img src="resources/MSC%20Logo%20Transparent.png" alt="MSC Logo" class="logo-image">
            <h1>MSC - Photobooth</h1>
        </div>
    </div>

    <div class="container">
        <!-- Email Input Screen -->
        <div id="emailScreen" class="screen active">
            <div class="content-box">
                <h2>Welcome to the Photobooth!</h2>
                <p>Enter your email to get started</p>
                <input type="email" id="emailInput" placeholder="your.email@example.com" />
                <div class="form-group">
                    <label for="intervalSelect">Select Auto-Snap Interval:</label>
                    <select id="intervalSelect" class="styled-dropdown">
                        <option value="1">1 Second</option>
                        <option value="3" selected>3 Seconds</option>
                        <option value="5">5 Seconds</option>
                        <option value="10">10 Seconds</option>
                    </select>
                </div>
                <button onclick="startSession()">Start Session</button>
            </div>
        </div>

        <!-- Camera Screen -->
        <div id="cameraScreen" class="screen">
            <div class="camera-wrapper">
                <div class="photo-counter">
                    <span id="photoCount">0</span> / 4 Photos
                </div>
                <div id="thumbnailContainer" class="thumbnail-container"></div>
                <div id="camera"></div>
                <div id="flashEffect" class="flash-effect"></div>
                <div id="timerDisplay" class="timer-display">Ready</div>
                <button id="cancelBtn" onclick="cancelAutoCapture()" class="btn-secondary" style="display: none; margin-top: 15px;">Cancel Auto-Capture</button>
                <div class="button-group">
                    <button id="captureBtn" onclick="startCapture()" class="btn-primary">Start Auto-Capture</button>
                    <button onclick="endSession()" class="btn-secondary">End Session</button>
                </div>
            </div>
        </div>

        <!-- Completion Screen -->
        <div id="completionScreen" class="screen">
            <div class="content-box">
                <div id="loadingSpinner" class="loading-spinner" style="display: none;">
                    <div class="spinner"></div>
                    <p>Creating your collage...</p>
                </div>
                <div id="collageContent" style="display: none;">
                    <h2>Session Complete!</h2>
                    <p id="completionMessage">Your photos have been saved.</p>
                    <div id="collageContainer" class="collage-container">
                        <img id="collageImage" src="" alt="Your Collage" />
                    </div>
                    <button onclick="location.reload()">Start New Session</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <script>
        let currentEmail = '';
        let photoCount = 0;
        const MAX_PHOTOS = 4;
        let timerActive = false;
        let autoCapturingInterval = null;
        let captureInterval = 3; // default interval in seconds
        let audioContext = null;
        let canCapture = true;
        const CAPTURE_COOLDOWN = 500; // 0.5 second cooldown

        // Initialize audio context for beeps
        function initAudioContext() {
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
        }

        // Play beep sound
        function playBeep(frequency = 800, duration = 200) {
            initAudioContext();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            oscillator.frequency.value = frequency;
            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration / 1000);
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + duration / 1000);
        }

        // Show toast notification
        function showToast(message, duration = 2000) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, duration);
        }

        // Configure webcam
        Webcam.set({
            width: 450,
            height: 600,
            image_format: 'jpeg',
            jpeg_quality: 90
        });

        function showScreen(screenId) {
            document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
            document.getElementById(screenId).classList.add('active');
        }

        function startSession() {
            const emailInput = document.getElementById('emailInput');
            const email = emailInput.value.trim();

            if (!email || !email.includes('@')) {
                alert('Please enter a valid email address');
                return;
            }

            currentEmail = email;
            photoCount = 0;
            
            // Get selected interval
            captureInterval = parseInt(document.getElementById('intervalSelect').value);
            
            showScreen('cameraScreen');
            
            // Clear previous thumbnails
            document.getElementById('thumbnailContainer').innerHTML = '';
            
            // Attach webcam with error handling
            try {
                Webcam.attach('#camera');
            } catch(e) {
                console.error('Camera error:', e);
                alert('Unable to access camera. Please check:\n1. Camera permissions are enabled\n2. Another app isn\'t using the camera\n3. Try a different browser');
                showScreen('emailScreen');
            }
        }

        function startCapture() {
            if (photoCount >= MAX_PHOTOS) {
                alert('You have reached the maximum of 4 photos!');
                return;
            }

            timerActive = true;
            const captureBtn = document.getElementById('captureBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            
            captureBtn.disabled = true;
            captureBtn.textContent = 'Auto-Capturing...';
            cancelBtn.style.display = 'inline-block';

            // Auto-capture every interval seconds
            autoCapturingInterval = setInterval(() => {
                if (photoCount >= MAX_PHOTOS) {
                    clearInterval(autoCapturingInterval);
                    autoCapturingInterval = null;
                    captureBtn.disabled = false;
                    captureBtn.textContent = 'Start Auto-Capture';
                    cancelBtn.style.display = 'none';
                    timerActive = false;
                    alert('You have reached the maximum of 4 photos!');
                    return;
                }
                startCountdown();
            }, captureInterval * 1000);

            // Start the first capture immediately
            startCountdown();
        }

        function startCountdown() {
            // Don't start countdown if we've already reached max photos
            if (photoCount >= MAX_PHOTOS) {
                return;
            }
            
            let countdown = captureInterval;
            const timerDisplay = document.getElementById('timerDisplay');

            // Delay before countdown starts
            setTimeout(() => {
                timerDisplay.classList.add('counting');

                const timerInterval = setInterval(() => {
                    timerDisplay.textContent = countdown;
                    
                    // Play beep sound for countdown (3, 2, 1) - only if we haven't reached max
                    if (countdown <= 3 && countdown > 0 && photoCount < MAX_PHOTOS) {
                        playBeep(1000, 150);
                    }
                    countdown--;

                    if (countdown < 0) {
                        clearInterval(timerInterval);
                        // Only take snapshot and play shutter if we haven't reached max yet
                        if (photoCount < MAX_PHOTOS && canCapture) {
                            playBeep(1200, 100);
                            takeSnapshot();
                        }
                        timerDisplay.textContent = 'Ready';
                        timerDisplay.classList.remove('counting');
                    }
                }, 1000);
            }, 500); // 0.5 second delay before countdown starts
        }

        function cancelAutoCapture() {
            if (autoCapturingInterval) {
                clearInterval(autoCapturingInterval);
                autoCapturingInterval = null;
            }
            
            timerActive = false;
            const captureBtn = document.getElementById('captureBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            
            captureBtn.disabled = false;
            captureBtn.textContent = 'Start Auto-Capture';
            cancelBtn.style.display = 'none';
            
            const timerDisplay = document.getElementById('timerDisplay');
            timerDisplay.textContent = 'Ready';
            timerDisplay.classList.remove('counting');
        }

        function flashScreen() {
            const flashEffect = document.getElementById('flashEffect');
            flashEffect.classList.remove('flash-active');
            // Trigger reflow to restart animation
            void flashEffect.offsetWidth;
            flashEffect.classList.add('flash-active');
        }

        function addThumbnail(dataUri) {
            const container = document.getElementById('thumbnailContainer');
            const thumbnail = document.createElement('img');
            thumbnail.src = dataUri;
            thumbnail.classList.add('thumbnail');
            container.appendChild(thumbnail);
        }

        function takeSnapshot() {
            // Prevent rapid captures
            canCapture = false;
            setTimeout(() => {
                canCapture = true;
            }, CAPTURE_COOLDOWN);

            Webcam.snap(function(data_uri) {
                photoCount++;
                document.getElementById('photoCount').textContent = photoCount;
                
                // Show success toast
                showToast('📸 Photo ' + photoCount + ' captured!');
                
                // Add thumbnail
                addThumbnail(data_uri);
                
                // Flash effect
                flashScreen();

                // Send to PHP
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'save_image.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if(xhr.status === 200) {
                        console.log('Photo saved: ' + photoCount);
                        if (photoCount >= MAX_PHOTOS) {
                            // Cancel auto-capture when max is reached
                            if (autoCapturingInterval) {
                                clearInterval(autoCapturingInterval);
                                autoCapturingInterval = null;
                            }
                            timerActive = false;
                            
                            const captureBtn = document.getElementById('captureBtn');
                            const cancelBtn = document.getElementById('cancelBtn');
                            captureBtn.disabled = false;
                            captureBtn.textContent = 'Start Auto-Capture';
                            cancelBtn.style.display = 'none';
                            
                            // Show loading spinner
                            document.getElementById('loadingSpinner').style.display = 'flex';
                            document.getElementById('collageContent').style.display = 'none';
                            showScreen('completionScreen');
                            
                            setTimeout(() => {
                                // Create collage
                                var collageXhr = new XMLHttpRequest();
                                collageXhr.open('POST', 'create_collage.php', true);
                                collageXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                collageXhr.onload = function() {
                                    if(collageXhr.status === 200) {
                                        try {
                                            var response = JSON.parse(collageXhr.responseText);
                                            if(response.success) {
                                                document.getElementById('completionMessage').textContent = 
                                                    'Your 4 photos have been saved!';
                                                var collageUrl = 'photos/' + response.folderName + '/collage.jpg?' + new Date().getTime();
                                                console.log('Loading collage from:', collageUrl);
                                                document.getElementById('collageImage').src = collageUrl;
                                                document.getElementById('collageContainer').style.display = 'block';
                                                
                                                // Hide spinner, show collage
                                                document.getElementById('loadingSpinner').style.display = 'none';
                                                document.getElementById('collageContent').style.display = 'block';
                                                
                                                showToast('✨ Collage ready!');
                                            } else {
                                                console.error('Collage error:', response.message);
                                                document.getElementById('completionMessage').textContent = 
                                                    'Photos saved but collage creation failed: ' + response.message;
                                                document.getElementById('loadingSpinner').style.display = 'none';
                                                document.getElementById('collageContent').style.display = 'block';
                                                showToast('⚠️ Error creating collage', 3000);
                                            }
                                        } catch(e) {
                                            console.error('Parse error:', e, 'Response:', collageXhr.responseText);
                                            document.getElementById('completionMessage').textContent = 'Error: ' + e.message;
                                            document.getElementById('loadingSpinner').style.display = 'none';
                                            document.getElementById('collageContent').style.display = 'block';
                                            showToast('❌ Error processing collage', 3000);
                                        }
                                    } else {
                                        console.error('HTTP error:', collageXhr.status);
                                        document.getElementById('completionMessage').textContent = 
                                            'Error: Failed to create collage (HTTP ' + collageXhr.status + ')';
                                        document.getElementById('loadingSpinner').style.display = 'none';
                                        document.getElementById('collageContent').style.display = 'block';
                                        showToast('❌ Server error', 3000);
                                    }
                                };
                                collageXhr.send('email=' + encodeURIComponent(currentEmail));
                            }, 1000);
                        }
                    }
                };
                xhr.send('image=' + encodeURIComponent(data_uri) + '&email=' + encodeURIComponent(currentEmail));
            });
        }

        function endSession() {
            if (autoCapturingInterval) {
                clearInterval(autoCapturingInterval);
                autoCapturingInterval = null;
            }
            
            if (confirm('End session with ' + photoCount + ' photos? You can start a new session after.')) {
                document.getElementById('completionMessage').textContent = 
                    'Session ended. ' + photoCount + ' photos saved to: ' + currentEmail;
                showScreen('completionScreen');
            }
        }
    </script>
</body>
</html>
