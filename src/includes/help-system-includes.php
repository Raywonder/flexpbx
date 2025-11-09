<?php
/**
 * FlexPBX Help System Includes
 * Include this file in any page to add help functionality
 *
 * Usage: require_once(__DIR__ . '/includes/help-system-includes.php');
 */
?>

<!-- FlexPBX Help System -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="/includes/help-widget.js"></script>
<script src="/includes/tooltips.js"></script>
<script src="/includes/keyboard-shortcuts.js"></script>

<!-- Help System Initialization -->
<script>
// Wait for all scripts to load
document.addEventListener('DOMContentLoaded', function() {
    console.log('FlexPBX Help System initialized');

    // Check if help system components are loaded
    if (typeof FlexPBXHelp !== 'undefined') {
        console.log('✓ Help Widget loaded');
    }
    if (typeof FlexPBXTooltips !== 'undefined') {
        console.log('✓ Tooltips loaded');
    }
    if (typeof KeyboardShortcuts !== 'undefined') {
        console.log('✓ Keyboard Shortcuts loaded');
    }
});
</script>
