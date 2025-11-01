<?php
/**
 * FlexPBX User Portal - Extension Settings
 * Manage SIP user details and extension settings
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$user_extension = $_SESSION['user_extension'] ?? null;
$user_username = $_SESSION['user_username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extension Settings - FlexPBX User Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .subtitle {
            color: #666;
            margin-bottom: 2rem;
        }

        .moh-option {
            background: #f8f9fa;
            border: 3px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .moh-option:hover {
            border-color: #667eea;
            transform: translateX(5px);
        }

        .moh-option.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }

        .moh-option input[type="radio"] {
            margin-right: 1rem;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .moh-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .moh-description {
            color: #666;
            font-size: 0.95rem;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>

        <div class="card">
            <h1>⚙️ SIP User Details & Extension Settings</h1>
            <p class="subtitle">Manage your SIP account and extension preferences - changes sync automatically</p>

            <div id="alert" class="alert"></div>

            <div id="loading" class="loading">
                Loading available music options...
            </div>

            <form id="moh-form" style="display: none;">
                <div id="moh-options"></div>

                <button type="submit" class="btn">Save Music Preference</button>
                <a href="index.php" class="btn btn-secondary" style="margin-left: 1rem;">Cancel</a>
            </form>
        </div>

        <div class="card" id="current-moh" style="display: none;">
            <h2>Current Hold Music</h2>
            <p id="current-moh-name" style="font-size: 1.2rem; color: #667eea; font-weight: 600;"></p>
            <p id="current-moh-desc" style="color: #666; margin-top: 0.5rem;"></p>
        </div>
    </div>

    <script>
        const extension = '<?php echo htmlspecialchars($user_extension); ?>';
        let currentMOH = 'default';
        let mohClasses = [];

        // Load available MOH classes
        async function loadMOHClasses() {
            try {
                const response = await fetch('/api/extensions.php?path=moh-classes');
                const data = await response.json();

                if (data.success) {
                    mohClasses = data.moh_classes;
                    displayMOHOptions();
                    loadCurrentMOH();
                }
            } catch (error) {
                showAlert('Failed to load music options', 'error');
            }
        }

        // Display MOH options
        function displayMOHOptions() {
            const container = document.getElementById('moh-options');
            container.innerHTML = '';

            mohClasses.forEach(moh => {
                const optionDiv = document.createElement('div');
                optionDiv.className = 'moh-option';
                optionDiv.innerHTML = `
                    <label style="display: flex; align-items: start; cursor: pointer;">
                        <input type="radio" name="moh_class" value="${moh.name}"
                               ${moh.name === currentMOH ? 'checked' : ''}>
                        <div>
                            <div class="moh-title">${moh.display_name}</div>
                            <div class="moh-description">${moh.description}</div>
                        </div>
                    </label>
                `;

                // Click anywhere on the option to select
                optionDiv.addEventListener('click', (e) => {
                    if (e.target.tagName !== 'INPUT') {
                        const radio = optionDiv.querySelector('input[type="radio"]');
                        radio.checked = true;
                        updateSelectedStates();
                    }
                });

                container.appendChild(optionDiv);
            });

            document.getElementById('loading').style.display = 'none';
            document.getElementById('moh-form').style.display = 'block';

            updateSelectedStates();
        }

        // Update visual selection states
        function updateSelectedStates() {
            document.querySelectorAll('.moh-option').forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                if (radio.checked) {
                    option.classList.add('selected');
                } else {
                    option.classList.remove('selected');
                }
            });

            // Update listeners
            document.querySelectorAll('input[name="moh_class"]').forEach(radio => {
                radio.addEventListener('change', updateSelectedStates);
            });
        }

        // Load current MOH setting
        async function loadCurrentMOH() {
            // For now, assume default - in production, fetch from endpoint details
            currentMOH = 'default';
            updateCurrentDisplay();
        }

        // Update current MOH display
        function updateCurrentDisplay() {
            const currentClass = mohClasses.find(m => m.name === currentMOH);
            if (currentClass) {
                document.getElementById('current-moh-name').textContent = currentClass.display_name;
                document.getElementById('current-moh-desc').textContent = currentClass.description;
                document.getElementById('current-moh').style.display = 'block';
            }
        }

        // Save MOH preference
        document.getElementById('moh-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const selectedMOH = document.querySelector('input[name="moh_class"]:checked').value;

            try {
                const response = await fetch(`/api/extensions.php?path=update-moh&id=${extension}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        moh_class: selectedMOH
                    })
                });

                const data = await response.json();

                if (data.success) {
                    currentMOH = selectedMOH;
                    updateCurrentDisplay();
                    showAlert('Music on hold preference saved successfully!', 'success');

                    // Scroll to top to see the success message
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    showAlert(data.message || 'Failed to save preference', 'error');
                }
            } catch (error) {
                showAlert('Error saving preference: ' + error.message, 'error');
            }
        });

        // Show alert message
        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';

            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        // Initialize
        loadMOHClasses();
    </script>
</body>
</html>
