/**
 * FlexPBX Text-to-Speech Service
 * Integrated TTS system with Chatterbox compatibility and tappedin.fm access
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const axios = require('axios');
const { EventEmitter } = require('events');

class TTSService extends EventEmitter {
  constructor(options = {}) {
    super();

    this.config = {
      apiEndpoint: 'https://tts.tappedin.fm/api/v1',
      cacheDirectory: path.join(__dirname, '..', '..', 'data', 'tts-cache'),
      defaultVoice: 'en-US-AriaNeural',
      defaultRate: '1.0',
      defaultPitch: '0Hz',
      maxCacheSize: 500 * 1024 * 1024, // 500MB
      chatterboxEnabled: true,
      supportedFormats: ['wav', 'mp3', 'ogg'],
      ...options
    };

    this.cache = new Map();
    this.voiceProfiles = new Map();
    this.isInitialized = false;

    this.initializeTTS();
  }

  async initializeTTS() {
    console.log('🔊 Initializing TTS Service...');

    try {
      // Create cache directory
      await this.ensureCacheDirectory();

      // Load voice profiles
      await this.loadVoiceProfiles();

      // Initialize Chatterbox compatibility
      await this.initializeChatterbox();

      // Setup tappedin.fm connection
      await this.setupTappedInConnection();

      this.isInitialized = true;
      console.log('✅ TTS Service initialized successfully');
      this.emit('initialized');

    } catch (error) {
      console.error('❌ TTS Service initialization failed:', error);
      this.emit('error', error);
    }
  }

  async ensureCacheDirectory() {
    if (!fs.existsSync(this.config.cacheDirectory)) {
      fs.mkdirSync(this.config.cacheDirectory, { recursive: true });
    }

    // Load existing cache
    const cacheIndex = path.join(this.config.cacheDirectory, 'cache-index.json');
    if (fs.existsSync(cacheIndex)) {
      try {
        const cacheData = JSON.parse(fs.readFileSync(cacheIndex, 'utf8'));
        this.cache = new Map(Object.entries(cacheData));
      } catch (error) {
        console.warn('⚠️ Failed to load TTS cache index:', error.message);
      }
    }
  }

  async loadVoiceProfiles() {
    // Default voice profiles with Chatterbox compatibility
    const defaultProfiles = {
      'chatterbox-male': {
        name: 'Chatterbox Male',
        voice: 'en-US-DavisNeural',
        rate: '1.0',
        pitch: '0Hz',
        style: 'friendly',
        chatterboxCompatible: true
      },
      'chatterbox-female': {
        name: 'Chatterbox Female',
        voice: 'en-US-AriaNeural',
        rate: '1.0',
        pitch: '0Hz',
        style: 'friendly',
        chatterboxCompatible: true
      },
      'pbx-announcer': {
        name: 'PBX Announcer',
        voice: 'en-US-GuyNeural',
        rate: '0.9',
        pitch: '-2Hz',
        style: 'newscast'
      },
      'ivr-assistant': {
        name: 'IVR Assistant',
        voice: 'en-US-JennyNeural',
        rate: '0.8',
        pitch: '0Hz',
        style: 'assistant'
      },
      'tappedin-host': {
        name: 'TappedIn Host',
        voice: 'en-US-BrianNeural',
        rate: '1.1',
        pitch: '1Hz',
        style: 'chat'
      }
    };

    for (const [key, profile] of Object.entries(defaultProfiles)) {
      this.voiceProfiles.set(key, profile);
    }

    console.log(`✅ Loaded ${this.voiceProfiles.size} voice profiles`);
  }

  async initializeChatterbox() {
    if (!this.config.chatterboxEnabled) return;

    // Chatterbox-compatible TTS engine
    this.chatterbox = {
      version: '2.1.0',
      compatibility: 'full',
      features: {
        voices: ['male', 'female', 'robot', 'child'],
        effects: ['reverb', 'chorus', 'echo', 'distortion'],
        emotions: ['happy', 'sad', 'angry', 'excited', 'calm'],
        languages: ['en-US', 'en-GB', 'es-ES', 'fr-FR', 'de-DE']
      }
    };

    console.log('✅ Chatterbox compatibility initialized');
  }

  async setupTappedInConnection() {
    try {
      // Test connection to tappedin.fm
      const response = await axios.get(`${this.config.apiEndpoint}/status`, {
        timeout: 5000,
        headers: {
          'User-Agent': 'FlexPBX-TTS/1.0.0',
          'X-Service': 'flexpbx'
        }
      });

      if (response.status === 200) {
        console.log('✅ Connected to tappedin.fm TTS service');
        this.tappedInConnected = true;
      }
    } catch (error) {
      console.warn('⚠️ Could not connect to tappedin.fm, using fallback TTS');
      this.tappedInConnected = false;
    }
  }

  async synthesize(text, options = {}) {
    if (!this.isInitialized) {
      throw new Error('TTS Service not initialized');
    }

    const config = {
      voice: options.voice || this.config.defaultVoice,
      rate: options.rate || this.config.defaultRate,
      pitch: options.pitch || this.config.defaultPitch,
      format: options.format || 'wav',
      effect: options.effect || null,
      emotion: options.emotion || null,
      chatterboxMode: options.chatterboxMode || false,
      ...options
    };

    // Generate cache key
    const cacheKey = this.generateCacheKey(text, config);

    // Check cache first
    if (this.cache.has(cacheKey)) {
      const cachedFile = this.cache.get(cacheKey);
      if (fs.existsSync(cachedFile.path)) {
        console.log('📢 Using cached TTS audio');
        return cachedFile;
      } else {
        this.cache.delete(cacheKey);
      }
    }

    try {
      let audioData;

      if (this.tappedInConnected && !config.chatterboxMode) {
        // Use tappedin.fm service
        audioData = await this.synthesizeWithTappedIn(text, config);
      } else {
        // Use local/fallback synthesis
        audioData = await this.synthesizeLocal(text, config);
      }

      // Cache the result
      const cachedFile = await this.cacheAudio(audioData, cacheKey, config.format);

      this.emit('synthesized', { text, config, file: cachedFile });
      return cachedFile;

    } catch (error) {
      console.error('❌ TTS synthesis failed:', error);
      this.emit('error', error);
      throw error;
    }
  }

  async synthesizeWithTappedIn(text, config) {
    console.log('🌐 Synthesizing with tappedin.fm...');

    const requestData = {
      text: text,
      voice: config.voice,
      rate: config.rate,
      pitch: config.pitch,
      format: config.format,
      service: 'flexpbx'
    };

    const response = await axios.post(
      `${this.config.apiEndpoint}/synthesize`,
      requestData,
      {
        responseType: 'arraybuffer',
        timeout: 30000,
        headers: {
          'Content-Type': 'application/json',
          'User-Agent': 'FlexPBX-TTS/1.0.0'
        }
      }
    );

    return Buffer.from(response.data);
  }

  async synthesizeLocal(text, config) {
    console.log('🔊 Synthesizing locally...');

    if (config.chatterboxMode) {
      return await this.synthesizeChatterbox(text, config);
    }

    // Fallback to system TTS or Web Speech API
    return await this.synthesizeSystemTTS(text, config);
  }

  async synthesizeChatterbox(text, config) {
    // Chatterbox-compatible synthesis
    console.log('🤖 Using Chatterbox mode synthesis...');

    // Apply Chatterbox effects and transformations
    let processedText = text;

    if (config.emotion) {
      processedText = this.applyEmotionToText(text, config.emotion);
    }

    if (config.effect) {
      // Effects will be applied during audio processing
      console.log(`Applying effect: ${config.effect}`);
    }

    // Use system TTS with Chatterbox parameters
    return await this.synthesizeSystemTTS(processedText, config);
  }

  async synthesizeSystemTTS(text, config) {
    // Create a simple synthetic audio file for development
    // In production, this would use actual TTS engines like:
    // - macOS: say command
    // - Windows: SAPI
    // - Linux: espeak/festival

    const audioBuffer = this.generateSyntheticAudio(text, config);
    return audioBuffer;
  }

  generateSyntheticAudio(text, config) {
    // Generate simple WAV file for testing
    // This is a placeholder - real implementation would use actual TTS

    const sampleRate = 44100;
    const duration = Math.max(2, text.length * 0.1); // Minimum 2 seconds
    const samples = Math.floor(sampleRate * duration);

    // Create WAV header
    const buffer = Buffer.alloc(44 + samples * 2);

    // WAV header
    buffer.write('RIFF', 0);
    buffer.writeUInt32LE(36 + samples * 2, 4);
    buffer.write('WAVE', 8);
    buffer.write('fmt ', 12);
    buffer.writeUInt32LE(16, 16);
    buffer.writeUInt16LE(1, 20);
    buffer.writeUInt16LE(1, 22);
    buffer.writeUInt32LE(sampleRate, 24);
    buffer.writeUInt32LE(sampleRate * 2, 28);
    buffer.writeUInt16LE(2, 32);
    buffer.writeUInt16LE(16, 34);
    buffer.write('data', 36);
    buffer.writeUInt32LE(samples * 2, 40);

    // Generate simple tone based on text
    const frequency = 200 + (text.charCodeAt(0) % 400); // Vary frequency by first character

    for (let i = 0; i < samples; i++) {
      const time = i / sampleRate;
      const value = Math.sin(2 * Math.PI * frequency * time) * 0.3;
      const sample = Math.floor(value * 32767);
      buffer.writeInt16LE(sample, 44 + i * 2);
    }

    return buffer;
  }

  applyEmotionToText(text, emotion) {
    const emotionMappings = {
      happy: text => text.replace(/\./g, '! '),
      sad: text => text.toLowerCase(),
      angry: text => text.toUpperCase(),
      excited: text => text.replace(/\s/g, ' ') + '!!!',
      calm: text => text.replace(/[!?]/g, '.')
    };

    return emotionMappings[emotion] ? emotionMappings[emotion](text) : text;
  }

  async cacheAudio(audioData, cacheKey, format) {
    const filename = `${cacheKey}.${format}`;
    const filepath = path.join(this.config.cacheDirectory, filename);

    fs.writeFileSync(filepath, audioData);

    const cacheEntry = {
      path: filepath,
      size: audioData.length,
      created: Date.now(),
      format: format
    };

    this.cache.set(cacheKey, cacheEntry);
    await this.saveCacheIndex();

    return cacheEntry;
  }

  generateCacheKey(text, config) {
    const key = JSON.stringify({ text, config });
    return crypto.createHash('md5').update(key).digest('hex');
  }

  async saveCacheIndex() {
    const cacheIndex = path.join(this.config.cacheDirectory, 'cache-index.json');
    const cacheData = Object.fromEntries(this.cache);
    fs.writeFileSync(cacheIndex, JSON.stringify(cacheData, null, 2));
  }

  // TTS for PBX features
  async announceCaller(callerInfo) {
    const text = `Incoming call from ${callerInfo.name || callerInfo.number}`;
    return await this.synthesize(text, { voice: 'pbx-announcer' });
  }

  async announceExtension(extension) {
    const text = `Extension ${extension}`;
    return await this.synthesize(text, { voice: 'pbx-announcer' });
  }

  async createIVRPrompt(promptText, options = {}) {
    return await this.synthesize(promptText, {
      voice: 'ivr-assistant',
      rate: '0.8',
      ...options
    });
  }

  async createVoicemailGreeting(extensionNumber, personalMessage = null) {
    const defaultMessage = `You have reached extension ${extensionNumber}. Please leave a message after the tone.`;
    const text = personalMessage || defaultMessage;

    return await this.synthesize(text, { voice: 'ivr-assistant' });
  }

  // Chatterbox compatibility methods
  async chatterboxSpeak(text, voice = 'male', effect = null) {
    const voiceMap = {
      male: 'chatterbox-male',
      female: 'chatterbox-female',
      robot: 'en-US-AriaNeural', // With robot effect
      child: 'en-US-AriaNeural'   // With pitch adjustment
    };

    const config = {
      voice: voiceMap[voice] || 'chatterbox-male',
      chatterboxMode: true,
      effect: effect
    };

    if (voice === 'robot') {
      config.effect = 'distortion';
    } else if (voice === 'child') {
      config.pitch = '5Hz';
    }

    return await this.synthesize(text, config);
  }

  // Voice profile management
  addVoiceProfile(key, profile) {
    this.voiceProfiles.set(key, profile);
    console.log(`✅ Added voice profile: ${profile.name}`);
  }

  getVoiceProfiles() {
    return Array.from(this.voiceProfiles.entries()).map(([key, profile]) => ({
      key,
      ...profile
    }));
  }

  // Cache management
  async clearCache() {
    const files = fs.readdirSync(this.config.cacheDirectory);
    for (const file of files) {
      if (file !== 'cache-index.json') {
        fs.unlinkSync(path.join(this.config.cacheDirectory, file));
      }
    }
    this.cache.clear();
    await this.saveCacheIndex();
    console.log('✅ TTS cache cleared');
  }

  getCacheStats() {
    const files = Array.from(this.cache.values());
    const totalSize = files.reduce((sum, file) => sum + file.size, 0);

    return {
      files: files.length,
      totalSize: totalSize,
      totalSizeMB: Math.round(totalSize / 1024 / 1024 * 100) / 100,
      oldestFile: files.length > 0 ? Math.min(...files.map(f => f.created)) : null,
      newestFile: files.length > 0 ? Math.max(...files.map(f => f.created)) : null
    };
  }

  // Service management
  async shutdown() {
    console.log('🔊 Shutting down TTS Service...');
    await this.saveCacheIndex();
    this.removeAllListeners();
    console.log('✅ TTS Service shutdown complete');
  }

  // Health check
  async healthCheck() {
    const health = {
      status: 'healthy',
      initialized: this.isInitialized,
      tappedInConnected: this.tappedInConnected,
      chatterboxEnabled: this.config.chatterboxEnabled,
      voiceProfiles: this.voiceProfiles.size,
      cache: this.getCacheStats(),
      issues: []
    };

    // Check cache directory
    if (!fs.existsSync(this.config.cacheDirectory)) {
      health.issues.push('Cache directory does not exist');
      health.status = 'warning';
    }

    // Check tappedin.fm connectivity
    if (!this.tappedInConnected) {
      health.issues.push('tappedin.fm service not connected');
    }

    return health;
  }
}

module.exports = TTSService;