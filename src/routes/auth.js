const express = require('express');
const router = express.Router();

// Login endpoint
router.post('/login', (req, res) => {
    // Stub authentication
    res.json({
        success: true,
        token: 'sample-jwt-token',
        user: {
            id: 1,
            username: 'admin',
            role: 'administrator'
        }
    });
});

// Logout endpoint
router.post('/logout', (req, res) => {
    res.json({ success: true, message: 'Logged out successfully' });
});

module.exports = router;