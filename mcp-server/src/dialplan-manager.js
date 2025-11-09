/**
 * Dial Plan Manager
 * Provides dial plan rules and patterns for SIP client auto-configuration
 */

export class DialPlanManager {
  constructor() {
    this.rules = this.getDefaultRules();
  }

  /**
   * Get default dial plan rules
   */
  getDefaultRules() {
    return {
      extensions: {
        pattern: '2xxx',
        description: '4-digit extensions (2000-2999)',
        regex: '^2[0-9]{3}$',
        minLength: 4,
        maxLength: 4,
        type: 'extension'
      },
      featureCodes: {
        pattern: '*xx',
        description: 'Feature codes (*97 voicemail, *45 queue login, etc.)',
        regex: '^\\*[0-9]{2}$',
        minLength: 3,
        maxLength: 3,
        type: 'feature',
        codes: {
          '*97': 'Voicemail Access',
          '*45': 'Queue Agent Login',
          '*46': 'Queue Agent Logout',
          '*65': 'Call Recording On',
          '*66': 'Call Recording Off',
          '*78': 'Do Not Disturb On',
          '*79': 'Do Not Disturb Off',
          '*72': 'Call Forward Enable',
          '*73': 'Call Forward Disable'
        }
      },
      usCanada: {
        pattern: '1[2-9]xxxxxxxxx',
        description: 'US/Canada 11-digit numbers (1-NPA-NXX-XXXX)',
        regex: '^1[2-9][0-9]{2}[2-9][0-9]{6}$',
        minLength: 11,
        maxLength: 11,
        type: 'outbound'
      },
      international: {
        pattern: '011xxxxxxxxxxx',
        description: 'International calls (011 + country code + number)',
        regex: '^011[0-9]{7,15}$',
        minLength: 10,
        maxLength: 18,
        type: 'outbound'
      }
    };
  }

  /**
   * Get dial rules for specific client format
   */
  getDialRules(format = 'json') {
    switch (format.toLowerCase()) {
      case 'groundwire':
        return this.getGroundwireFormat();
      case 'linphone':
        return this.getLinphoneFormat();
      case 'zoiper':
        return this.getZoiperFormat();
      default:
        return this.getJsonFormat();
    }
  }

  /**
   * Groundwire dial plan format
   */
  getGroundwireFormat() {
    return {
      dialPlan: '(2xxx|*xx|1[2-9]xxxxxxxxx|011xxxxxxxxxxx)',
      rules: this.rules,
      format: 'groundwire'
    };
  }

  /**
   * Linphone dial plan format
   */
  getLinphoneFormat() {
    const linphoneRules = Object.entries(this.rules).map(([key, rule]) => ({
      pattern: rule.regex,
      description: rule.description
    }));

    return {
      dialRules: linphoneRules,
      format: 'linphone'
    };
  }

  /**
   * Zoiper dial plan format
   */
  getZoiperFormat() {
    return {
      dialPlan: {
        timeout: 3,
        rules: Object.entries(this.rules).map(([key, rule]) => ({
          pattern: rule.pattern,
          min: rule.minLength,
          max: rule.maxLength
        }))
      },
      format: 'zoiper'
    };
  }

  /**
   * JSON format (default)
   */
  getJsonFormat() {
    return {
      dialRules: this.rules,
      combinedPattern: '(2xxx|*xx|1[2-9]xxxxxxxxx|011xxxxxxxxxxx)',
      interDigitTimeout: 3,
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Get feature codes
   */
  getFeatureCodes() {
    return {
      featureCodes: this.rules.featureCodes.codes,
      pattern: this.rules.featureCodes.pattern,
      description: this.rules.featureCodes.description
    };
  }

  /**
   * Get emergency numbers configuration
   */
  getEmergencyNumbers() {
    return {
      emergencyNumbers: {
        '911': {
          description: 'Emergency Services (US/Canada)',
          type: 'emergency',
          enabled: false,
          warning: 'Currently blocked for safety - configure E911 first'
        },
        '112': {
          description: 'International Emergency',
          type: 'emergency',
          enabled: false
        }
      },
      warning: 'Emergency calling requires proper E911 configuration and address verification'
    };
  }

  /**
   * Validate dialed number against rules
   */
  validateNumber(number) {
    for (const [name, rule] of Object.entries(this.rules)) {
      const regex = new RegExp(rule.regex);
      if (regex.test(number)) {
        return {
          valid: true,
          rule: name,
          type: rule.type,
          description: rule.description
        };
      }
    }

    return {
      valid: false,
      error: 'Number does not match any dial plan rule'
    };
  }
}
