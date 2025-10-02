#!/usr/bin/env node

/**
 * 🔒 FlexPBX Privacy & Security Permissions Checker
 * Comprehensive check for all needed macOS permissions
 */

const { exec } = require('child_process');

class PermissionChecker {
    constructor() {
        this.results = [];
    }

    async announce(message) {
        console.log(`🗣️ ${message}`);
        return new Promise((resolve) => {
            exec(`say "${message}"`, () => resolve());
        });
    }

    async checkAllPermissions() {
        await this.announce("Checking all FlexPBX permissions. I will tell you exactly what to enable.");

        console.log('🔒 FlexPBX Privacy & Security Permissions Check');
        console.log('=' .repeat(60));

        // Essential permissions for FlexPBX functionality
        await this.checkAccessibility();
        await this.checkMicrophone();
        await this.checkScreenRecording();
        await this.checkFullDiskAccess();
        await this.checkNetworkExtensions();
        await this.checkAutomation();

        this.showResults();
        this.showRecommendations();
    }

    async checkAccessibility() {
        console.log('\n♿ Checking Accessibility...');

        try {
            const result = await this.execAsync(`osascript -e 'tell application "System Events" to key code 49'`);
            this.results.push({
                permission: 'Accessibility',
                status: 'GRANTED',
                required: 'ESSENTIAL',
                reason: 'VoiceOver control, screen reader automation, Finder navigation'
            });
            console.log('✅ Accessibility: GRANTED');
        } catch (error) {
            if (error.message.includes('1002')) {
                this.results.push({
                    permission: 'Accessibility',
                    status: 'DENIED',
                    required: 'ESSENTIAL',
                    reason: 'VoiceOver control, screen reader automation, Finder navigation'
                });
                console.log('❌ Accessibility: DENIED');
            }
        }
    }

    async checkMicrophone() {
        console.log('\n🎤 Checking Microphone...');

        try {
            // Test microphone access through system_profiler
            const result = await this.execAsync('system_profiler SPAudioDataType | grep -i "input"');
            this.results.push({
                permission: 'Microphone',
                status: 'AVAILABLE',
                required: 'ESSENTIAL',
                reason: 'Bidirectional audio streaming, remote assistance, voice communication'
            });
            console.log('✅ Microphone: Available (enable for FlexPBX app when prompted)');
        } catch (error) {
            this.results.push({
                permission: 'Microphone',
                status: 'UNKNOWN',
                required: 'ESSENTIAL',
                reason: 'Bidirectional audio streaming, remote assistance, voice communication'
            });
            console.log('⚠️ Microphone: Status unknown');
        }
    }

    async checkScreenRecording() {
        console.log('\n📺 Checking Screen Recording...');

        this.results.push({
            permission: 'Screen Recording',
            status: 'RECOMMENDED',
            required: 'OPTIONAL',
            reason: 'Screen sharing, remote assistance, accessibility testing screenshots'
        });
        console.log('💡 Screen Recording: RECOMMENDED (for remote assistance features)');
    }

    async checkFullDiskAccess() {
        console.log('\n💾 Checking Full Disk Access...');

        try {
            // Test access to system areas
            const result = await this.execAsync('ls ~/Library/Application\\ Support/ 2>/dev/null | head -1');
            this.results.push({
                permission: 'Full Disk Access',
                status: 'PARTIAL',
                required: 'OPTIONAL',
                reason: 'Complete file sharing, system configuration backup, DNS file management'
            });
            console.log('💡 Full Disk Access: OPTIONAL (for advanced file operations)');
        } catch (error) {
            this.results.push({
                permission: 'Full Disk Access',
                status: 'LIMITED',
                required: 'OPTIONAL',
                reason: 'Complete file sharing, system configuration backup, DNS file management'
            });
            console.log('💡 Full Disk Access: LIMITED');
        }
    }

    async checkNetworkExtensions() {
        console.log('\n🌐 Checking Network Extensions...');

        this.results.push({
            permission: 'Network Extensions',
            status: 'NOT_NEEDED',
            required: 'NOT_REQUIRED',
            reason: 'FlexPBX uses standard networking - no extensions needed'
        });
        console.log('ℹ️ Network Extensions: NOT NEEDED for FlexPBX');
    }

    async checkAutomation() {
        console.log('\n🤖 Checking Automation...');

        try {
            const result = await this.execAsync(`osascript -e 'tell application "Finder" to get name'`);
            this.results.push({
                permission: 'Automation (AppleScript)',
                status: 'WORKING',
                required: 'ESSENTIAL',
                reason: 'VoiceOver scripting, application control, VLC automation'
            });
            console.log('✅ Automation: WORKING');
        } catch (error) {
            this.results.push({
                permission: 'Automation (AppleScript)',
                status: 'LIMITED',
                required: 'ESSENTIAL',
                reason: 'VoiceOver scripting, application control, VLC automation'
            });
            console.log('⚠️ Automation: May need app-specific permissions');
        }
    }

    showResults() {
        console.log('\n📊 PERMISSION SUMMARY');
        console.log('=' .repeat(60));

        const essential = this.results.filter(r => r.required === 'ESSENTIAL');
        const optional = this.results.filter(r => r.required === 'OPTIONAL');
        const notNeeded = this.results.filter(r => r.required === 'NOT_REQUIRED');

        console.log('\n🔴 ESSENTIAL PERMISSIONS (Required for core functionality):');
        essential.forEach(result => {
            const status = result.status === 'GRANTED' || result.status === 'WORKING' ? '✅' :
                          result.status === 'DENIED' ? '❌' : '⚠️';
            console.log(`   ${status} ${result.permission}: ${result.status}`);
            console.log(`      → ${result.reason}`);
        });

        console.log('\n🟡 OPTIONAL PERMISSIONS (Enhance functionality):');
        optional.forEach(result => {
            console.log(`   💡 ${result.permission}: ${result.status}`);
            console.log(`      → ${result.reason}`);
        });

        console.log('\n🟢 NOT REQUIRED:');
        notNeeded.forEach(result => {
            console.log(`   ℹ️ ${result.permission}: ${result.status}`);
        });
    }

    showRecommendations() {
        console.log('\n🎯 RECOMMENDED PRIVACY & SECURITY SETTINGS');
        console.log('=' .repeat(60));

        console.log('\n📝 IN SYSTEM PREFERENCES → SECURITY & PRIVACY:');

        console.log('\n🔴 ESSENTIAL - Enable these for FlexPBX:');
        console.log('   1. ACCESSIBILITY:');
        console.log('      ✅ Terminal (for VoiceOver scripting)');
        console.log('      ✅ FlexPBX Enhanced (when it appears)');
        console.log('      ✅ osascript (if listed)');

        console.log('\n   2. MICROPHONE:');
        console.log('      ✅ Terminal (for testing)');
        console.log('      ✅ FlexPBX Enhanced (when prompted)');
        console.log('      ✅ Allow microphone access for audio streaming');

        console.log('\n🟡 RECOMMENDED - Enable these for enhanced features:');
        console.log('   3. SCREEN RECORDING:');
        console.log('      💡 Terminal (for screen sharing tests)');
        console.log('      💡 FlexPBX Enhanced (for remote assistance)');

        console.log('\n   4. AUTOMATION:');
        console.log('      💡 Terminal → Finder (for file operations)');
        console.log('      💡 Terminal → VLC (for media control)');
        console.log('      💡 Terminal → System Events (for accessibility)');

        console.log('\n🟢 OPTIONAL - Enable if you want maximum functionality:');
        console.log('   5. FULL DISK ACCESS:');
        console.log('      🔧 Terminal (for system file management)');
        console.log('      🔧 FlexPBX Enhanced (for complete file sharing)');

        console.log('\n❌ DO NOT ENABLE (FlexPBX doesn\'t need these):');
        console.log('   ❌ Camera (FlexPBX is audio-focused)');
        console.log('   ❌ Location Services (Not relevant)');
        console.log('   ❌ Contacts (Privacy-focused design)');
        console.log('   ❌ Calendar (Not applicable)');
        console.log('   ❌ Reminders (Not applicable)');

        console.log('\n🔒 SECURITY RECOMMENDATIONS:');
        console.log('   ✅ Keep FileVault encryption ON');
        console.log('   ✅ Keep Firewall ON');
        console.log('   ✅ Allow signed software');
        console.log('   ✅ Enable "Require password immediately after sleep"');
        console.log('   ✅ Keep "Allow apps downloaded from App Store and identified developers"');

        console.log('\n⚡ PERFORMANCE SETTINGS:');
        console.log('   🚀 Add FlexPBX to "Energy Saver" exceptions if needed');
        console.log('   🚀 Consider adding to "Login Items" for auto-start');

        console.log('\n🎵 VOICEOVER SPECIFIC SETTINGS:');
        console.log('   ♿ VoiceOver Utility → General → "Allow VoiceOver to be controlled with AppleScript" ✅');
        console.log('   ♿ This is ESSENTIAL for remote VoiceOver control!');
    }

    async execAsync(command) {
        return new Promise((resolve, reject) => {
            exec(command, (error, stdout, stderr) => {
                if (error) {
                    reject(error);
                } else {
                    resolve({ stdout, stderr });
                }
            });
        });
    }
}

// Run the comprehensive check
if (require.main === module) {
    const checker = new PermissionChecker();
    checker.checkAllPermissions().then(() => {
        console.log('\n🎉 Permission check complete!');
        console.log('📋 Enable the ESSENTIAL permissions first, then test with:');
        console.log('   node check-permissions.js');
        console.log('   node LIVE-VOICEOVER-TEST.js');
    });
}

module.exports = PermissionChecker;