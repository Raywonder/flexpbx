/**
 * FlexPBX TTS Configuration
 * tappedin.fm integration and Chatterbox compatibility settings
 */

const path = require('path');
const os = require('os');

module.exports = {
  // tappedin.fm API Configuration
  tappedin: {
    baseUrl: 'https://tts.tappedin.fm',
    apiVersion: 'v1',
    endpoints: {
      synthesize: '/api/v1/synthesize',
      voices: '/api/v1/voices',
      status: '/api/v1/status',
      stream: '/api/v1/stream'
    },
    authentication: {
      type: 'service',
      serviceId: 'flexpbx',
      apiKey: process.env.TAPPEDIN_API_KEY || null,
      userAgent: 'FlexPBX-TTS/1.0.0'
    },
    defaults: {
      voice: 'en-US-AriaNeural',
      rate: 1.0,
      pitch: 0,
      format: 'audio/wav',
      sampleRate: 44100,
      bitRate: 16
    },
    limits: {
      maxTextLength: 5000,
      maxRequestsPerMinute: 60,
      maxConcurrentRequests: 5
    }
  },

  // Chatterbox Compatibility Settings
  chatterbox: {
    enabled: true,
    version: '2.1.0',
    compatibility: 'full',
    voices: {
      male: {
        name: 'Chatterbox Male',
        voice: 'en-US-DavisNeural',
        settings: {
          rate: 1.0,
          pitch: '0Hz',
          style: 'friendly'
        }
      },
      female: {
        name: 'Chatterbox Female',
        voice: 'en-US-AriaNeural',
        settings: {
          rate: 1.0,
          pitch: '0Hz',
          style: 'friendly'
        }
      },
      robot: {
        name: 'Chatterbox Robot',
        voice: 'en-US-AriaNeural',
        settings: {
          rate: 0.8,
          pitch: '-10Hz',
          style: 'chat'
        },
        effects: ['distortion', 'robot']
      },
      child: {
        name: 'Chatterbox Child',
        voice: 'en-US-AriaNeural',
        settings: {
          rate: 1.2,
          pitch: '5Hz',
          style: 'cheerful'
        }
      }
    },
    effects: {
      reverb: {
        enabled: true,
        parameters: {
          roomSize: 0.5,
          damping: 0.5,
          wetLevel: 0.3,
          dryLevel: 0.7
        }
      },
      chorus: {
        enabled: true,
        parameters: {
          rate: 1.1,
          depth: 0.2,
          feedback: 0.25,
          delay: 0.016
        }
      },
      echo: {
        enabled: true,
        parameters: {
          delay: 0.3,
          decay: 0.4,
          feedback: 0.3
        }
      },
      distortion: {
        enabled: true,
        parameters: {
          amount: 0.8,
          oversample: '4x'
        }
      }
    },
    emotions: {
      happy: {
        textTransform: text => text.replace(/\./g, '! '),
        voiceSettings: {
          style: 'cheerful',
          rate: 1.1
        }
      },
      sad: {
        textTransform: text => text.toLowerCase(),
        voiceSettings: {
          style: 'sad',
          rate: 0.8,
          pitch: '-2Hz'
        }
      },
      angry: {
        textTransform: text => text.toUpperCase(),
        voiceSettings: {
          style: 'angry',
          rate: 1.2,
          pitch: '2Hz'
        }
      },
      excited: {
        textTransform: text => text.replace(/\s/g, ' ') + '!!!',
        voiceSettings: {
          style: 'excited',
          rate: 1.3
        }
      },
      calm: {
        textTransform: text => text.replace(/[!?]/g, '.'),
        voiceSettings: {
          style: 'calm',
          rate: 0.9,
          pitch: '-1Hz'
        }
      }
    }
  },

  // FlexPBX Specific Voice Profiles
  pbx: {
    announcer: {
      name: 'PBX Announcer',
      voice: 'en-US-GuyNeural',
      settings: {
        rate: 0.9,
        pitch: '-2Hz',
        style: 'newscast'
      },
      usage: ['caller-announcements', 'extension-announcements']
    },
    ivr: {
      name: 'IVR Assistant',
      voice: 'en-US-JennyNeural',
      settings: {
        rate: 0.8,
        pitch: '0Hz',
        style: 'assistant'
      },
      usage: ['menu-prompts', 'instructions', 'confirmations']
    },
    voicemail: {
      name: 'Voicemail System',
      voice: 'en-US-AriaNeural',
      settings: {
        rate: 0.9,
        pitch: '0Hz',
        style: 'friendly'
      },
      usage: ['voicemail-greetings', 'voicemail-prompts']
    },
    emergency: {
      name: 'Emergency Alert',
      voice: 'en-US-BrianNeural',
      settings: {
        rate: 1.0,
        pitch: '1Hz',
        style: 'newscast'
      },
      usage: ['emergency-announcements', 'system-alerts']
    },
    multilingual: {
      english: 'en-US-AriaNeural',
      spanish: 'es-ES-ElviraNeural',
      french: 'fr-FR-DeniseNeural',
      german: 'de-DE-KatjaNeural',
      italian: 'it-IT-ElsaNeural',
      portuguese: 'pt-BR-FranciscaNeural',
      chinese: 'zh-CN-XiaoxiaoNeural',
      japanese: 'ja-JP-NanamiNeural'
    }
  },

  // Audio Processing Settings
  audio: {
    formats: {
      wav: {
        sampleRate: 44100,
        bitDepth: 16,
        channels: 1,
        codec: 'pcm'
      },
      mp3: {
        sampleRate: 44100,
        bitRate: 128,
        channels: 1,
        codec: 'mp3'
      },
      ogg: {
        sampleRate: 44100,
        bitRate: 128,
        channels: 1,
        codec: 'vorbis'
      }
    },
    processing: {
      normalize: true,
      compressor: {
        enabled: true,
        threshold: -20,
        ratio: 4,
        attack: 0.003,
        release: 0.1
      },
      eq: {
        enabled: true,
        lowShelf: { frequency: 100, gain: 0 },
        midRange: { frequency: 1000, gain: 1 },
        highShelf: { frequency: 8000, gain: 0 }
      }
    }
  },

  // Cache Management
  cache: {
    directory: path.join(os.homedir(), '.flexpbx', 'tts-cache'),
    maxSize: 500 * 1024 * 1024, // 500MB
    maxAge: 30 * 24 * 60 * 60 * 1000, // 30 days
    compressionEnabled: true,
    indexFile: 'cache-index.json',
    cleanupInterval: 24 * 60 * 60 * 1000 // 24 hours
  },

  // Fallback and Error Handling
  fallback: {
    enabled: true,
    method: 'system-tts', // 'system-tts', 'synthetic-audio', 'silent'
    systemCommands: {
      darwin: 'say',
      linux: 'espeak',
      win32: 'powershell -Command "Add-Type -AssemblyName System.Speech; $synth = New-Object System.Speech.Synthesis.SpeechSynthesizer; $synth.Speak(\'{text}\')"'
    },
    retryAttempts: 3,
    retryDelay: 1000,
    timeoutMs: 30000
  },

  // Performance and Optimization
  performance: {
    preloadCommonPhrases: true,
    concurrentSynthesis: 3,
    streamingEnabled: true,
    compressionLevel: 6,
    backgroundSynthesis: true,
    priorityQueue: true
  },

  // Security Settings
  security: {
    validateInput: true,
    sanitizeText: true,
    maxInputLength: 10000,
    allowedCharacters: /^[a-zA-Z0-9\s\.\,\!\?\-\:\;\(\)\'\"]+$/,
    rateLimiting: {
      enabled: true,
      maxRequestsPerMinute: 100,
      maxRequestsPerHour: 1000
    }
  },

  // Logging and Monitoring
  logging: {
    enabled: true,
    level: 'info', // 'debug', 'info', 'warn', 'error'
    logFile: path.join(os.homedir(), '.flexpbx', 'logs', 'tts.log'),
    logRequests: true,
    logResponses: false,
    logErrors: true,
    maxLogSize: 10 * 1024 * 1024, // 10MB
    maxLogFiles: 5
  },

  // Integration Settings
  integration: {
    pbxCommands: {
      announceCall: 'tts-announce-caller',
      announceExtension: 'tts-announce-extension',
      playIVRPrompt: 'tts-create-ivr-prompt',
      playVoicemailGreeting: 'tts-create-voicemail-greeting'
    },
    asteriskIntegration: {
      enabled: true,
      agiScript: 'flexpbx-tts.agi',
      dialplanContext: 'flexpbx-tts',
      customApplications: {
        'FlexTTS': 'flexpbx-tts-app'
      }
    },
    webInterface: {
      enabled: true,
      port: 8081,
      endpoint: '/tts',
      cors: true,
      authentication: false
    }
  },

  // Development and Testing
  development: {
    mockMode: process.env.NODE_ENV === 'development',
    testPhrases: [
      'Welcome to FlexPBX',
      'Extension one hundred',
      'Please hold while we connect your call',
      'You have reached the voicemail of extension',
      'Press 1 for sales, press 2 for support',
      'Thank you for calling'
    ],
    debugOutput: true,
    saveTestAudio: false
  }
};