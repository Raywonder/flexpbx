#!/usr/bin/env node
/**
 * FlexPBX TTS Integration Test
 * Comprehensive testing of TTS features and tappedin.fm connectivity
 */

const TTSService = require('./src/main/services/TTSService');
const ttsConfig = require('./src/main/config/tts-config');
const fs = require('fs');
const path = require('path');

class TTSIntegrationTest {
  constructor() {
    this.testResults = [];
    this.ttsService = null;
  }

  async runTests() {
    console.log('🔊 Starting FlexPBX TTS Integration Tests...');
    console.log('================================================');

    try {
      // Initialize TTS Service
      await this.testTTSInitialization();

      // Test basic synthesis
      await this.testBasicSynthesis();

      // Test Chatterbox compatibility
      await this.testChatterboxCompatibility();

      // Test PBX-specific features
      await this.testPBXFeatures();

      // Test voice profiles
      await this.testVoiceProfiles();

      // Test tappedin.fm connectivity
      await this.testTappedInConnectivity();

      // Test cache functionality
      await this.testCacheSystem();

      // Test error handling
      await this.testErrorHandling();

      // Test performance
      await this.testPerformance();

      // Generate report
      this.generateTestReport();

    } catch (error) {
      console.error('❌ Test suite failed:', error);
      this.testResults.push({
        name: 'Test Suite',
        status: 'failed',
        error: error.message
      });
    } finally {
      if (this.ttsService) {
        await this.ttsService.shutdown();
      }
    }
  }

  async testTTSInitialization() {
    console.log('\n🚀 Testing TTS Service Initialization...');

    try {
      this.ttsService = new TTSService({
        apiEndpoint: 'https://tts.tappedin.fm/api/v1',
        chatterboxEnabled: true,
        cacheDirectory: path.join(__dirname, 'test-cache')
      });

      // Wait for initialization
      await new Promise((resolve, reject) => {
        this.ttsService.on('initialized', resolve);
        this.ttsService.on('error', reject);

        // Timeout after 10 seconds
        setTimeout(() => reject(new Error('Initialization timeout')), 10000);
      });

      console.log('✅ TTS Service initialized successfully');
      this.testResults.push({
        name: 'TTS Service Initialization',
        status: 'passed',
        details: 'Service initialized without errors'
      });

    } catch (error) {
      console.log('❌ TTS Service initialization failed:', error.message);
      this.testResults.push({
        name: 'TTS Service Initialization',
        status: 'failed',
        error: error.message
      });
      throw error;
    }
  }

  async testBasicSynthesis() {
    console.log('\n🔊 Testing Basic Text-to-Speech Synthesis...');

    const testTexts = [
      'Welcome to FlexPBX',
      'Extension one hundred',
      'Please hold while we connect your call',
      'Thank you for calling'
    ];

    let passedTests = 0;

    for (const text of testTexts) {
      try {
        console.log(`  Testing: "${text}"`);
        const result = await this.ttsService.synthesize(text);

        if (result && result.path && fs.existsSync(result.path)) {
          console.log(`    ✅ Generated audio file: ${result.path}`);
          passedTests++;
        } else {
          console.log(`    ❌ Failed to generate audio for: "${text}"`);
        }

      } catch (error) {
        console.log(`    ❌ Error synthesizing: "${text}" - ${error.message}`);
      }
    }

    const status = passedTests === testTexts.length ? 'passed' : 'partial';
    console.log(`\n${status === 'passed' ? '✅' : '⚠️'} Basic synthesis: ${passedTests}/${testTexts.length} tests passed`);

    this.testResults.push({
      name: 'Basic Text Synthesis',
      status: status,
      details: `${passedTests}/${testTexts.length} synthesis tests passed`
    });
  }

  async testChatterboxCompatibility() {
    console.log('\n🤖 Testing Chatterbox Compatibility...');

    const chatterboxTests = [
      { text: 'Hello from Chatterbox male voice', voice: 'male' },
      { text: 'Hello from Chatterbox female voice', voice: 'female' },
      { text: 'I am a robot voice', voice: 'robot' },
      { text: 'This is a child voice speaking', voice: 'child' }
    ];

    let passedTests = 0;

    for (const test of chatterboxTests) {
      try {
        console.log(`  Testing Chatterbox ${test.voice} voice...`);
        const result = await this.ttsService.chatterboxSpeak(test.text, test.voice);

        if (result && result.path && fs.existsSync(result.path)) {
          console.log(`    ✅ Chatterbox ${test.voice} voice working`);
          passedTests++;
        } else {
          console.log(`    ❌ Chatterbox ${test.voice} voice failed`);
        }

      } catch (error) {
        console.log(`    ❌ Error with ${test.voice} voice: ${error.message}`);
      }
    }

    const status = passedTests === chatterboxTests.length ? 'passed' : 'partial';
    console.log(`\n${status === 'passed' ? '✅' : '⚠️'} Chatterbox compatibility: ${passedTests}/${chatterboxTests.length} voices working`);

    this.testResults.push({
      name: 'Chatterbox Compatibility',
      status: status,
      details: `${passedTests}/${chatterboxTests.length} Chatterbox voices working`
    });
  }

  async testPBXFeatures() {
    console.log('\n📞 Testing PBX-Specific Features...');

    const pbxTests = [
      {
        name: 'Caller Announcement',
        test: () => this.ttsService.announceCaller({ name: 'John Doe', number: '555-1234' })
      },
      {
        name: 'Extension Announcement',
        test: () => this.ttsService.announceExtension('100')
      },
      {
        name: 'IVR Prompt',
        test: () => this.ttsService.createIVRPrompt('Press 1 for sales, press 2 for support')
      },
      {
        name: 'Voicemail Greeting',
        test: () => this.ttsService.createVoicemailGreeting('100', 'Hello, you have reached John Doe')
      }
    ];

    let passedTests = 0;

    for (const pbxTest of pbxTests) {
      try {
        console.log(`  Testing ${pbxTest.name}...`);
        const result = await pbxTest.test();

        if (result && result.path && fs.existsSync(result.path)) {
          console.log(`    ✅ ${pbxTest.name} working`);
          passedTests++;
        } else {
          console.log(`    ❌ ${pbxTest.name} failed`);
        }

      } catch (error) {
        console.log(`    ❌ Error with ${pbxTest.name}: ${error.message}`);
      }
    }

    const status = passedTests === pbxTests.length ? 'passed' : 'partial';
    console.log(`\n${status === 'passed' ? '✅' : '⚠️'} PBX features: ${passedTests}/${pbxTests.length} features working`);

    this.testResults.push({
      name: 'PBX-Specific Features',
      status: status,
      details: `${passedTests}/${pbxTests.length} PBX features working`
    });
  }

  async testVoiceProfiles() {
    console.log('\n👥 Testing Voice Profiles...');

    try {
      const profiles = this.ttsService.getVoiceProfiles();
      console.log(`  Found ${profiles.length} voice profiles`);

      const expectedProfiles = ['chatterbox-male', 'chatterbox-female', 'pbx-announcer', 'ivr-assistant'];
      let foundProfiles = 0;

      for (const expectedProfile of expectedProfiles) {
        const found = profiles.find(p => p.key === expectedProfile);
        if (found) {
          console.log(`    ✅ Profile found: ${found.name}`);
          foundProfiles++;
        } else {
          console.log(`    ❌ Profile missing: ${expectedProfile}`);
        }
      }

      // Test adding custom profile
      this.ttsService.addVoiceProfile('test-voice', {
        name: 'Test Voice',
        voice: 'en-US-AriaNeural',
        rate: '1.0',
        pitch: '0Hz'
      });

      const updatedProfiles = this.ttsService.getVoiceProfiles();
      const customProfile = updatedProfiles.find(p => p.key === 'test-voice');

      if (customProfile) {
        console.log('    ✅ Custom voice profile added successfully');
        foundProfiles++;
      } else {
        console.log('    ❌ Failed to add custom voice profile');
      }

      const status = foundProfiles === expectedProfiles.length + 1 ? 'passed' : 'partial';
      console.log(`\n${status === 'passed' ? '✅' : '⚠️'} Voice profiles: ${foundProfiles}/${expectedProfiles.length + 1} profiles working`);

      this.testResults.push({
        name: 'Voice Profiles',
        status: status,
        details: `${foundProfiles}/${expectedProfiles.length + 1} voice profiles working`
      });

    } catch (error) {
      console.log('❌ Voice profile test failed:', error.message);
      this.testResults.push({
        name: 'Voice Profiles',
        status: 'failed',
        error: error.message
      });
    }
  }

  async testTappedInConnectivity() {
    console.log('\n🌐 Testing tappedin.fm Connectivity...');

    try {
      const health = await this.ttsService.healthCheck();

      console.log(`  TTS Service Status: ${health.status}`);
      console.log(`  tappedin.fm Connected: ${health.tappedInConnected ? 'Yes' : 'No'}`);
      console.log(`  Chatterbox Enabled: ${health.chatterboxEnabled ? 'Yes' : 'No'}`);

      if (health.tappedInConnected) {
        console.log('  ✅ tappedin.fm connection established');

        // Test actual synthesis with tappedin.fm
        try {
          const result = await this.ttsService.synthesize('Testing tappedin.fm connectivity', {
            chatterboxMode: false
          });

          if (result && result.path) {
            console.log('  ✅ tappedin.fm synthesis working');
            this.testResults.push({
              name: 'tappedin.fm Connectivity',
              status: 'passed',
              details: 'Successfully connected and synthesized audio'
            });
          } else {
            console.log('  ⚠️ tappedin.fm connected but synthesis failed');
            this.testResults.push({
              name: 'tappedin.fm Connectivity',
              status: 'partial',
              details: 'Connected but synthesis failed'
            });
          }
        } catch (synthError) {
          console.log('  ⚠️ tappedin.fm connected but synthesis error:', synthError.message);
          this.testResults.push({
            name: 'tappedin.fm Connectivity',
            status: 'partial',
            details: `Connected but synthesis failed: ${synthError.message}`
          });
        }
      } else {
        console.log('  ⚠️ tappedin.fm not connected - using fallback synthesis');
        this.testResults.push({
          name: 'tappedin.fm Connectivity',
          status: 'warning',
          details: 'Service not connected, using fallback methods'
        });
      }

    } catch (error) {
      console.log('❌ tappedin.fm connectivity test failed:', error.message);
      this.testResults.push({
        name: 'tappedin.fm Connectivity',
        status: 'failed',
        error: error.message
      });
    }
  }

  async testCacheSystem() {
    console.log('\n💾 Testing Cache System...');

    try {
      // Clear cache first
      await this.ttsService.clearCache();
      console.log('  Cache cleared');

      // Generate some cached content
      const testText = 'This is a cache test message';

      // First synthesis (should create cache)
      const startTime1 = Date.now();
      const result1 = await this.ttsService.synthesize(testText);
      const time1 = Date.now() - startTime1;

      // Second synthesis (should use cache)
      const startTime2 = Date.now();
      const result2 = await this.ttsService.synthesize(testText);
      const time2 = Date.now() - startTime2;

      console.log(`  First synthesis: ${time1}ms`);
      console.log(`  Second synthesis: ${time2}ms`);

      if (time2 < time1 && result1.path === result2.path) {
        console.log('  ✅ Cache system working (faster second request)');
      } else {
        console.log('  ⚠️ Cache system may not be working optimally');
      }

      // Test cache stats
      const stats = this.ttsService.getCacheStats();
      console.log(`  Cache stats: ${stats.files} files, ${stats.totalSizeMB}MB`);

      if (stats.files > 0) {
        console.log('  ✅ Cache stats available');
        this.testResults.push({
          name: 'Cache System',
          status: 'passed',
          details: `Cache working with ${stats.files} files (${stats.totalSizeMB}MB)`
        });
      } else {
        console.log('  ❌ No cache files found');
        this.testResults.push({
          name: 'Cache System',
          status: 'failed',
          details: 'No cache files were created'
        });
      }

    } catch (error) {
      console.log('❌ Cache system test failed:', error.message);
      this.testResults.push({
        name: 'Cache System',
        status: 'failed',
        error: error.message
      });
    }
  }

  async testErrorHandling() {
    console.log('\n⚠️ Testing Error Handling...');

    const errorTests = [
      {
        name: 'Empty Text',
        test: () => this.ttsService.synthesize('')
      },
      {
        name: 'Very Long Text',
        test: () => this.ttsService.synthesize('a'.repeat(10000))
      },
      {
        name: 'Invalid Voice',
        test: () => this.ttsService.synthesize('Test', { voice: 'invalid-voice' })
      },
      {
        name: 'Invalid Chatterbox Voice',
        test: () => this.ttsService.chatterboxSpeak('Test', 'invalid')
      }
    ];

    let handledErrors = 0;

    for (const errorTest of errorTests) {
      try {
        console.log(`  Testing ${errorTest.name}...`);
        await errorTest.test();
        console.log(`    ⚠️ ${errorTest.name}: Expected error but got success`);
      } catch (error) {
        console.log(`    ✅ ${errorTest.name}: Properly handled error - ${error.message}`);
        handledErrors++;
      }
    }

    const status = handledErrors >= 2 ? 'passed' : 'partial';
    console.log(`\n${status === 'passed' ? '✅' : '⚠️'} Error handling: ${handledErrors}/${errorTests.length} errors handled properly`);

    this.testResults.push({
      name: 'Error Handling',
      status: status,
      details: `${handledErrors}/${errorTests.length} error cases handled properly`
    });
  }

  async testPerformance() {
    console.log('\n⚡ Testing Performance...');

    try {
      const testTexts = [
        'Performance test one',
        'Performance test two',
        'Performance test three'
      ];

      const startTime = Date.now();
      const promises = testTexts.map(text => this.ttsService.synthesize(text));
      const results = await Promise.all(promises);
      const totalTime = Date.now() - startTime;

      const successfulSyntheses = results.filter(r => r && r.path).length;
      const avgTime = totalTime / testTexts.length;

      console.log(`  Synthesized ${successfulSyntheses}/${testTexts.length} texts in ${totalTime}ms`);
      console.log(`  Average time per synthesis: ${avgTime.toFixed(1)}ms`);

      if (successfulSyntheses === testTexts.length && avgTime < 5000) {
        console.log('  ✅ Performance test passed');
        this.testResults.push({
          name: 'Performance',
          status: 'passed',
          details: `${successfulSyntheses} syntheses in ${totalTime}ms (avg: ${avgTime.toFixed(1)}ms)`
        });
      } else {
        console.log('  ⚠️ Performance test had issues');
        this.testResults.push({
          name: 'Performance',
          status: 'partial',
          details: `${successfulSyntheses}/${testTexts.length} syntheses, avg time: ${avgTime.toFixed(1)}ms`
        });
      }

    } catch (error) {
      console.log('❌ Performance test failed:', error.message);
      this.testResults.push({
        name: 'Performance',
        status: 'failed',
        error: error.message
      });
    }
  }

  generateTestReport() {
    console.log('\n📊 TTS Integration Test Report');
    console.log('===============================================');

    const passed = this.testResults.filter(r => r.status === 'passed').length;
    const partial = this.testResults.filter(r => r.status === 'partial').length;
    const warning = this.testResults.filter(r => r.status === 'warning').length;
    const failed = this.testResults.filter(r => r.status === 'failed').length;
    const total = this.testResults.length;

    console.log(`\nTest Results Summary:`);
    console.log(`  ✅ Passed:  ${passed}/${total}`);
    console.log(`  ⚠️  Partial: ${partial}/${total}`);
    console.log(`  🟡 Warning: ${warning}/${total}`);
    console.log(`  ❌ Failed:  ${failed}/${total}`);

    console.log(`\nDetailed Results:`);
    this.testResults.forEach(result => {
      const icon = {
        passed: '✅',
        partial: '⚠️',
        warning: '🟡',
        failed: '❌'
      }[result.status];

      console.log(`  ${icon} ${result.name}: ${result.details || result.error || 'No details'}`);
    });

    const overallStatus = failed === 0 ? (partial === 0 ? 'PASSED' : 'PARTIAL') : 'FAILED';
    const successRate = Math.round(((passed + partial * 0.5) / total) * 100);

    console.log(`\nOverall Status: ${overallStatus} (${successRate}% success rate)`);

    if (overallStatus === 'PASSED') {
      console.log('\n🎉 FlexPBX TTS integration is working perfectly!');
      console.log('   All features tested successfully including:');
      console.log('   • Text-to-speech synthesis');
      console.log('   • Chatterbox compatibility');
      console.log('   • PBX-specific features');
      console.log('   • Voice profile management');
      console.log('   • Cache system');
      console.log('   • Error handling');
    } else if (overallStatus === 'PARTIAL') {
      console.log('\n✨ FlexPBX TTS integration is mostly working!');
      console.log('   Some features may need attention, but core functionality is operational.');
    } else {
      console.log('\n⚠️ FlexPBX TTS integration needs attention.');
      console.log('   Please review the failed tests and resolve issues.');
    }

    // Save report to file
    const reportData = {
      timestamp: new Date().toISOString(),
      overallStatus,
      successRate,
      summary: { passed, partial, warning, failed, total },
      results: this.testResults
    };

    const reportPath = path.join(__dirname, 'tts-test-report.json');
    fs.writeFileSync(reportPath, JSON.stringify(reportData, null, 2));
    console.log(`\n📄 Detailed report saved to: ${reportPath}`);
  }
}

// Run tests if called directly
if (require.main === module) {
  const tester = new TTSIntegrationTest();
  tester.runTests().catch(console.error);
}

module.exports = TTSIntegrationTest;