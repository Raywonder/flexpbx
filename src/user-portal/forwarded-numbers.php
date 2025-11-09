<?php
/**
 * FlexPBX User Portal - Forwarded Numbers Management
 * Manage external numbers that forward to your extension
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$extension = $_SESSION['user_extension'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forwarded Numbers - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; font-size: 14px; }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }
        .card h2 {
            color: #2c3e50;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #eee;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        input[type="text"],
        input[type="tel"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .number-list {
            list-style: none;
        }
        .number-item {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .number-item.disabled {
            opacity: 0.6;
        }
        .number-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .number-display {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .number-actions {
            display: flex;
            gap: 0.5rem;
        }
        .number-details {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
            display: inline-block;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 26px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #4ade80;
        }
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .number-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📞 Forwarded Numbers</h1>
            <p class="subtitle">Extension <?= htmlspecialchars($extension) ?> - Manage external numbers forwarded to your extension</p>
        </div>

        <div id="alert-container"></div>

        <!-- Add Number Card -->
        <div class="card">
            <h2>➕ Add Forwarded Number</h2>
            <form id="add-number-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="number">Phone Number *</label>
                        <input
                            type="tel"
                            id="number"
                            name="number"
                            placeholder="2813015784"
                            required
                            aria-required="true"
                            aria-label="Phone number to forward"
                        >
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input
                            type="text"
                            id="description"
                            name="description"
                            placeholder="Google Voice, Mobile, etc."
                            aria-label="Description of phone number"
                        >
                    </div>
                </div>
                <div class="form-group">
                    <label for="ring_time">Ring Time (seconds) *</label>
                    <input
                        type="number"
                        id="ring_time"
                        name="ring_time"
                        value="30"
                        min="5"
                        max="300"
                        required
                        aria-required="true"
                        aria-label="How long to ring before going to voicemail"
                    >
                    <small style="color: #666; display: block; margin-top: 0.3rem;">How long to ring before going to voicemail (5-300 seconds)</small>
                </div>
                <button
                    type="submit"
                    class="btn"
                    id="add-btn"
                    aria-label="Add forwarded number"
                >
                    Add Number
                </button>
            </form>
        </div>

        <!-- Current Numbers Card -->
        <div class="card">
            <h2>📋 Your Forwarded Numbers</h2>
            <div id="loading-message" style="text-align: center; padding: 2rem; color: #666;">
                Loading forwarded numbers...
            </div>
            <ul class="number-list" id="numbers-list" style="display: none;"></ul>
            <div id="empty-message" style="display: none; text-align: center; padding: 2rem; color: #666;">
                No forwarded numbers configured. Add one above to get started!
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="/user-portal/" class="btn btn-secondary" aria-label="Return to user dashboard">
                Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="edit-modal">
        <div class="modal-content">
            <div class="modal-header">✏️ Edit Forwarded Number</div>
            <form id="edit-number-form">
                <input type="hidden" id="edit-number-original">
                <div class="form-group">
                    <label for="edit-number">Phone Number</label>
                    <input
                        type="tel"
                        id="edit-number"
                        readonly
                        disabled
                        style="background: #f0f0f0;"
                        aria-label="Phone number being edited"
                    >
                </div>
                <div class="form-group">
                    <label for="edit-description">Description</label>
                    <input
                        type="text"
                        id="edit-description"
                        name="description"
                        aria-label="Update description"
                    >
                </div>
                <div class="form-group">
                    <label for="edit-ring-time">Ring Time (seconds)</label>
                    <input
                        type="number"
                        id="edit-ring-time"
                        name="ring_time"
                        min="5"
                        max="300"
                        required
                        aria-label="Update ring time"
                    >
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        onclick="closeEditModal()"
                        aria-label="Cancel editing"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="btn"
                        aria-label="Save changes"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const extension = '<?= addslashes($extension) ?>';

        // Load numbers on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadNumbers();
        });

        // Add number form submission
        document.getElementById('add-number-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('add-btn');
            btn.disabled = true;
            btn.textContent = 'Adding...';

            const formData = {
                number: document.getElementById('number').value.replace(/\D/g, ''),
                description: document.getElementById('description').value,
                ring_time: parseInt(document.getElementById('ring_time').value),
                enabled: true
            };

            try {
                const response = await fetch('/api/forwarded-numbers.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'Forwarded number added successfully!');
                    document.getElementById('add-number-form').reset();
                    document.getElementById('ring_time').value = 30;
                    await loadNumbers();
                } else {
                    showAlert('error', data.error || 'Failed to add number');
                }
            } catch (error) {
                console.error('Error adding number:', error);
                showAlert('error', 'An error occurred. Please try again.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Add Number';
            }
        });

        // Load forwarded numbers
        async function loadNumbers() {
            try {
                const response = await fetch('/api/forwarded-numbers.php');
                const data = await response.json();

                const loadingMsg = document.getElementById('loading-message');
                const emptyMsg = document.getElementById('empty-message');
                const numbersList = document.getElementById('numbers-list');

                loadingMsg.style.display = 'none';

                if (data.success && data.forwarded_numbers && data.forwarded_numbers.length > 0) {
                    emptyMsg.style.display = 'none';
                    numbersList.style.display = 'block';
                    renderNumbers(data.forwarded_numbers);
                } else {
                    emptyMsg.style.display = 'block';
                    numbersList.style.display = 'none';
                }
            } catch (error) {
                console.error('Error loading numbers:', error);
                showAlert('error', 'Failed to load forwarded numbers');
            }
        }

        // Render numbers list
        function renderNumbers(numbers) {
            const list = document.getElementById('numbers-list');
            list.innerHTML = '';

            numbers.forEach(number => {
                const li = document.createElement('li');
                li.className = `number-item ${!number.enabled ? 'disabled' : ''}`;

                const formattedNumber = formatPhoneNumber(number.number);
                const addedDate = new Date(number.added_date).toLocaleDateString();

                li.innerHTML = `
                    <div class="number-header">
                        <div class="number-display">${formattedNumber}</div>
                        <div class="number-actions">
                            <label class="toggle-switch" title="${number.enabled ? 'Disable' : 'Enable'} forwarding">
                                <input
                                    type="checkbox"
                                    ${number.enabled ? 'checked' : ''}
                                    onchange="toggleNumber('${number.number}', this.checked)"
                                    aria-label="${number.enabled ? 'Disable' : 'Enable'} forwarding for ${formattedNumber}"
                                >
                                <span class="slider"></span>
                            </label>
                            <button
                                class="btn btn-small"
                                onclick="openEditModal('${number.number}', '${escapeHtml(number.description)}', ${number.ring_time})"
                                aria-label="Edit ${formattedNumber}"
                            >
                                Edit
                            </button>
                            <button
                                class="btn btn-danger btn-small"
                                onclick="deleteNumber('${number.number}')"
                                aria-label="Delete ${formattedNumber}"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                    <div class="number-details">
                        ${number.description ? `<div><strong>Description:</strong> ${escapeHtml(number.description)}</div>` : ''}
                        <div><strong>Ring Time:</strong> ${number.ring_time} seconds</div>
                        <div><strong>Status:</strong> ${number.enabled ? '✓ Enabled' : '✗ Disabled'}</div>
                        <div><strong>Added:</strong> ${addedDate}</div>
                    </div>
                `;

                list.appendChild(li);
            });
        }

        // Toggle number enabled/disabled
        async function toggleNumber(number, enabled) {
            try {
                const response = await fetch('/api/forwarded-numbers.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ number, enabled })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', `Number ${enabled ? 'enabled' : 'disabled'} successfully`);
                    await loadNumbers();
                } else {
                    showAlert('error', data.error || 'Failed to update number');
                    await loadNumbers(); // Reload to reset toggle
                }
            } catch (error) {
                console.error('Error toggling number:', error);
                showAlert('error', 'An error occurred. Please try again.');
                await loadNumbers();
            }
        }

        // Open edit modal
        function openEditModal(number, description, ringTime) {
            document.getElementById('edit-number-original').value = number;
            document.getElementById('edit-number').value = formatPhoneNumber(number);
            document.getElementById('edit-description').value = description || '';
            document.getElementById('edit-ring-time').value = ringTime;
            document.getElementById('edit-modal').classList.add('active');
        }

        // Close edit modal
        function closeEditModal() {
            document.getElementById('edit-modal').classList.remove('active');
        }

        // Edit form submission
        document.getElementById('edit-number-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const number = document.getElementById('edit-number-original').value;
            const description = document.getElementById('edit-description').value;
            const ring_time = parseInt(document.getElementById('edit-ring-time').value);

            try {
                const response = await fetch('/api/forwarded-numbers.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ number, description, ring_time })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'Number updated successfully!');
                    closeEditModal();
                    await loadNumbers();
                } else {
                    showAlert('error', data.error || 'Failed to update number');
                }
            } catch (error) {
                console.error('Error updating number:', error);
                showAlert('error', 'An error occurred. Please try again.');
            }
        });

        // Delete number
        async function deleteNumber(number) {
            if (!confirm(`Are you sure you want to delete ${formatPhoneNumber(number)}?`)) {
                return;
            }

            try {
                const response = await fetch('/api/forwarded-numbers.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ number })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'Number deleted successfully!');
                    await loadNumbers();
                } else {
                    showAlert('error', data.error || 'Failed to delete number');
                }
            } catch (error) {
                console.error('Error deleting number:', error);
                showAlert('error', 'An error occurred. Please try again.');
            }
        }

        // Show alert message
        function showAlert(type, message) {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alert.setAttribute('role', 'alert');
            alert.setAttribute('aria-live', 'polite');

            container.appendChild(alert);

            setTimeout(() => {
                alert.style.transition = 'opacity 0.3s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }

        // Format phone number for display
        function formatPhoneNumber(number) {
            const cleaned = number.replace(/\D/g, '');
            if (cleaned.length === 10) {
                return `(${cleaned.slice(0,3)}) ${cleaned.slice(3,6)}-${cleaned.slice(6)}`;
            } else if (cleaned.length === 11) {
                return `+${cleaned[0]} (${cleaned.slice(1,4)}) ${cleaned.slice(4,7)}-${cleaned.slice(7)}`;
            }
            return number;
        }

        // Escape HTML for safe rendering
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on outside click
        document.getElementById('edit-modal').addEventListener('click', (e) => {
            if (e.target.id === 'edit-modal') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
