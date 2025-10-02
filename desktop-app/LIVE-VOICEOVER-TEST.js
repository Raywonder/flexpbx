#!/usr/bin/env node

/**
 * 🎯 FlexPBX LIVE VoiceOver Test
 * Real-time VoiceOver control and VLC automation test
 */

const { spawn, exec } = require('child_process');
const fs = require('fs');
const path = require('path');

class LiveVoiceOverTest {
    constructor() {
        this.isVoiceOverRunning = false;
        this.testResults = [];
    }

    async runLiveTest() {
        await this.announce("Starting FlexPBX live VoiceOver and VLC test");

        try {
            await this.checkVoiceOverStatus();
            await this.testVoiceOverControl();
            await this.testVLCAutomation();
            await this.showResults();
        } catch (error) {
            await this.announce(`Test failed: ${error.message}`);
            console.error('❌ Test failed:', error);
        }
    }

    async announce(message) {
        console.log(`🗣️ ${message}`);
        return new Promise((resolve) => {
            exec(`say "${message}"`, (error) => {
                if (error) console.warn('Speech synthesis failed:', error.message);
                setTimeout(resolve, 1000); // Brief pause after speech
            });
        });
    }

    async checkVoiceOverStatus() {
        await this.announce("Checking VoiceOver status");

        return new Promise((resolve) => {
            exec('ps aux | grep VoiceOver', (error, stdout) => {
                this.isVoiceOverRunning = stdout.includes('VoiceOver.app');

                if (this.isVoiceOverRunning) {
                    console.log('✅ VoiceOver is running');
                    this.testResults.push({ test: 'VoiceOver Detection', status: 'PASSED' });
                } else {
                    console.log('⚠️ VoiceOver is not running - will try to start it');
                    this.testResults.push({ test: 'VoiceOver Detection', status: 'NOT_RUNNING' });
                }
                resolve();
            });
        });
    }

    async testVoiceOverControl() {
        await this.announce("Testing VoiceOver control. I will toggle VoiceOver if needed and demonstrate navigation.");

        // Test 1: Toggle VoiceOver
        await this.testVoiceOverToggle();

        // Test 2: Navigate to Finder
        await this.testFinderNavigation();

        // Test 3: Test folder operations
        await this.testFolderOperations();
    }

    async testVoiceOverToggle() {
        await this.announce("Testing VoiceOver toggle with Command F5");

        return new Promise((resolve) => {
            const script = `
            tell application "System Events"
                key code 96 using {command down}
            end tell
            `;

            exec(`osascript -e '${script}'`, async (error) => {
                if (error) {
                    console.log('❌ VoiceOver toggle failed:', error.message);
                    this.testResults.push({ test: 'VoiceOver Toggle', status: 'FAILED', error: error.message });
                    await this.announce("VoiceOver toggle failed");
                } else {
                    console.log('✅ VoiceOver toggle successful');
                    this.testResults.push({ test: 'VoiceOver Toggle', status: 'PASSED' });
                    await this.announce("VoiceOver toggle successful");
                }

                // Wait for VoiceOver to respond
                setTimeout(resolve, 3000);
            });
        });
    }

    async testFinderNavigation() {
        await this.announce("Opening Finder and testing navigation");

        return new Promise((resolve) => {
            const script = `
            tell application "Finder"
                activate
                delay 1
            end tell

            tell application "System Events"
                tell process "Finder"
                    delay 1
                    -- Try to navigate to desktop
                    key code 125 using {command down} -- Command+Down to go to desktop
                    delay 1
                end tell
            end tell
            `;

            exec(`osascript -e '${script}'`, async (error) => {
                if (error) {
                    console.log('❌ Finder navigation failed:', error.message);
                    this.testResults.push({ test: 'Finder Navigation', status: 'FAILED', error: error.message });
                    await this.announce("Finder navigation failed");
                } else {
                    console.log('✅ Finder navigation successful');
                    this.testResults.push({ test: 'Finder Navigation', status: 'PASSED' });
                    await this.announce("Finder opened successfully");
                }

                setTimeout(resolve, 2000);
            });
        });
    }

    async testFolderOperations() {
        await this.announce("Testing folder operations - opening and closing folders");

        return new Promise((resolve) => {
            const script = `
            tell application "Finder"
                activate
                delay 1

                try
                    -- Try to navigate to Applications folder
                    set target of Finder window 1 to applications folder
                    delay 2

                    -- Then go back to home
                    set target of Finder window 1 to home folder
                    delay 1

                    return "success"
                on error errMsg
                    return "error: " & errMsg
                end try
            end tell
            `;

            exec(`osascript -e '${script}'`, async (error, stdout) => {
                if (error || stdout.includes('error:')) {
                    console.log('❌ Folder operations failed:', error?.message || stdout);
                    this.testResults.push({ test: 'Folder Operations', status: 'FAILED', error: error?.message || stdout });
                    await this.announce("Folder operations failed");
                } else {
                    console.log('✅ Folder operations successful');
                    this.testResults.push({ test: 'Folder Operations', status: 'PASSED' });
                    await this.announce("Folder operations completed successfully");
                }

                setTimeout(resolve, 2000);
            });
        });
    }

    async testVLCAutomation() {
        await this.announce("Testing VLC automation - will find a video file and control playback");

        // First, find a video file in the TV folder
        const videoFile = await this.findVideoFile();

        if (!videoFile) {
            await this.announce("No video file found in TV folder");
            this.testResults.push({ test: 'VLC Automation', status: 'SKIPPED', reason: 'No video file found' });
            return;
        }

        await this.announce(`Found video file: ${path.basename(videoFile)}. Starting VLC test.`);

        // Test VLC automation
        await this.testVLCPlayback(videoFile);
    }

    async findVideoFile() {
        const possiblePaths = [
            '/Volumes/*/TV/',
            '/Volumes/*/tv/',
            '/Volumes/*/Videos/',
            '/Volumes/*/movies/',
            '/Volumes/*/Movies/'
        ];

        for (const basePath of possiblePaths) {
            try {
                const result = await this.execAsync(`ls ${basePath}*.{mp4,mkv,avi,mov,m4v} 2>/dev/null | head -1`);
                if (result.stdout.trim()) {
                    return result.stdout.trim();
                }
            } catch (e) {
                // Continue searching
            }
        }

        // Fallback - check for any video file in mounted volumes
        try {
            const result = await this.execAsync(`find /Volumes -name "*.mp4" -o -name "*.mkv" -o -name "*.avi" | head -1 2>/dev/null`);
            if (result.stdout.trim()) {
                return result.stdout.trim();
            }
        } catch (e) {
            // No video files found
        }

        return null;
    }

    async testVLCPlayback(videoFile) {
        await this.announce("Opening VLC and starting playback");

        return new Promise((resolve) => {
            const script = `
            tell application "VLC"
                activate
                delay 2

                try
                    open POSIX file "${videoFile}"
                    delay 3

                    -- Test rewind
                    tell application "System Events"
                        tell process "VLC"
                            key code 123 using {shift down} -- Shift+Left for rewind
                            delay 2
                        end tell
                    end tell

                    -- Test fast forward
                    tell application "System Events"
                        tell process "VLC"
                            key code 124 using {shift down} -- Shift+Right for fast forward
                            delay 2
                        end tell
                    end tell

                    -- Pause
                    tell application "System Events"
                        tell process "VLC"
                            key code 49 -- Space to pause
                            delay 1
                        end tell
                    end tell

                    -- Close VLC
                    tell application "VLC"
                        quit
                    end tell

                    return "success"
                on error errMsg
                    return "error: " & errMsg
                end try
            end tell
            `;

            exec(`osascript -e '${script}'`, async (error, stdout) => {
                if (error || stdout.includes('error:')) {
                    console.log('❌ VLC automation failed:', error?.message || stdout);
                    this.testResults.push({ test: 'VLC Automation', status: 'FAILED', error: error?.message || stdout });
                    await this.announce("VLC automation failed");
                } else {
                    console.log('✅ VLC automation successful');
                    this.testResults.push({ test: 'VLC Automation', status: 'PASSED' });
                    await this.announce("VLC automation completed successfully - played video, rewound, fast forwarded, and closed VLC");
                }

                setTimeout(resolve, 2000);
            });
        });
    }

    async showResults() {
        await this.announce("Test completed. Displaying results.");

        console.log('\n🎯 FlexPBX Live VoiceOver Test Results');
        console.log('=' .repeat(50));

        let passed = 0;
        let failed = 0;
        let skipped = 0;

        for (const result of this.testResults) {
            const status = result.status === 'PASSED' ? '✅' :
                          result.status === 'FAILED' ? '❌' :
                          result.status === 'SKIPPED' ? '⏭️' : '⚠️';

            console.log(`${status} ${result.test}: ${result.status}`);

            if (result.error) {
                console.log(`   Error: ${result.error}`);
            }

            if (result.reason) {
                console.log(`   Reason: ${result.reason}`);
            }

            if (result.status === 'PASSED') passed++;
            else if (result.status === 'FAILED') failed++;
            else skipped++;
        }

        console.log('\n📊 Summary:');
        console.log(`   Passed: ${passed}`);
        console.log(`   Failed: ${failed}`);
        console.log(`   Skipped: ${skipped}`);
        console.log(`   Total: ${this.testResults.length}`);

        const successRate = Math.round((passed / (passed + failed)) * 100);
        await this.announce(`Test completed. ${passed} tests passed, ${failed} failed. Success rate: ${successRate} percent.`);

        // Save results
        const resultsFile = './live-test-results.json';
        fs.writeFileSync(resultsFile, JSON.stringify({
            timestamp: new Date().toISOString(),
            results: this.testResults,
            summary: { passed, failed, skipped, successRate }
        }, null, 2));

        console.log(`\n📄 Results saved to: ${resultsFile}`);
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

// Run the live test
if (require.main === module) {
    const test = new LiveVoiceOverTest();
    test.runLiveTest().then(() => {
        console.log('🎉 Live test completed!');
    }).catch((error) => {
        console.error('💥 Live test failed:', error);
    });
}

module.exports = LiveVoiceOverTest;