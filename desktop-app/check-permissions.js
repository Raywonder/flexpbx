#!/usr/bin/env node

/**
 * Check if Terminal has accessibility permissions
 */

const { exec } = require('child_process');

async function checkPermissions() {
    console.log('🔍 Checking accessibility permissions...');

    try {
        // Try a simple keystroke to test permissions
        const result = await new Promise((resolve, reject) => {
            exec(`osascript -e 'tell application "System Events" to key code 49'`, (error, stdout, stderr) => {
                if (error) {
                    if (error.message.includes('1002')) {
                        resolve({ hasPermission: false, error: 'Accessibility permission required' });
                    } else {
                        reject(error);
                    }
                } else {
                    resolve({ hasPermission: true });
                }
            });
        });

        if (result.hasPermission) {
            console.log('✅ Terminal has accessibility permissions!');
            console.log('🎯 Ready to run VoiceOver tests!');
            exec('say "Accessibility permissions confirmed. Ready to test VoiceOver control!"');
            return true;
        } else {
            console.log('❌ Terminal needs accessibility permissions');
            console.log('📝 Instructions:');
            console.log('   1. Open System Preferences');
            console.log('   2. Go to Security & Privacy');
            console.log('   3. Click on Accessibility (left side)');
            console.log('   4. Click the lock to make changes');
            console.log('   5. Check the box next to Terminal');
            console.log('   6. Run this script again to verify');
            exec('say "Please enable Terminal in accessibility settings, then run this check again"');
            return false;
        }
    } catch (error) {
        console.log('❌ Error checking permissions:', error.message);
        return false;
    }
}

// Run the check
checkPermissions().then(hasPermission => {
    if (hasPermission) {
        console.log('\n🚀 Ready to run: node LIVE-VOICEOVER-TEST.js');
        process.exit(0);
    } else {
        console.log('\n⏳ Run this script again after setting permissions');
        process.exit(1);
    }
});