/**
 * Extension Manager
 * Manages SIP/PJSIP extensions and registration status
 */

export class ExtensionManager {
  constructor(amiClient) {
    this.ami = amiClient;
  }

  /**
   * List all SIP peers
   */
  async listSIPPeers() {
    const response = await this.ami.getSIPPeers();
    return this.parseSIPPeers(response);
  }

  /**
   * Parse SIP peers response
   */
  parseSIPPeers(response) {
    // AMI returns events for each peer
    // This is a simplified version
    return {
      success: true,
      message: 'SIP peers retrieved',
      peers: [],
      note: 'Listen for PeerEntry events for detailed peer information'
    };
  }

  /**
   * List all PJSIP endpoints
   */
  async listPJSIPEndpoints() {
    const response = await this.ami.getPJSIPEndpoints();
    return this.parsePJSIPEndpoints(response);
  }

  /**
   * Parse PJSIP endpoints response
   */
  parsePJSIPEndpoints(response) {
    return {
      success: true,
      message: 'PJSIP endpoints retrieved',
      endpoints: [],
      note: 'Listen for EndpointList events for detailed endpoint information'
    };
  }

  /**
   * Get extension status
   */
  async getExtensionStatus(extension) {
    const response = await this.ami.command(`pjsip show endpoint ${extension}`);

    return {
      extension: extension,
      status: this.parseExtensionStatus(response.Output || ''),
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Parse extension status output
   */
  parseExtensionStatus(output) {
    const lines = output.split('\n');
    const status = {
      registered: false,
      deviceState: 'UNKNOWN',
      contacts: []
    };

    for (const line of lines) {
      if (line.includes('DeviceState')) {
        const match = line.match(/DeviceState\s*:\s*(\S+)/);
        if (match) {
          status.deviceState = match[1];
          status.registered = match[1] !== 'UNAVAILABLE';
        }
      }

      if (line.includes('Contact:')) {
        const match = line.match(/Contact:\s*(.+)/);
        if (match) {
          status.contacts.push(match[1].trim());
        }
      }
    }

    return status;
  }

  /**
   * Get all extension statuses
   */
  async getAllExtensionStatuses() {
    const response = await this.ami.command('pjsip show endpoints');
    return this.parseAllEndpoints(response.Output || '');
  }

  /**
   * Parse all endpoints output
   */
  parseAllEndpoints(output) {
    const lines = output.split('\n');
    const endpoints = [];

    for (const line of lines) {
      // Parse endpoint list format
      const match = line.match(/^\s*(\S+)\s+(\S+)\s+(\d+)\s+of\s+(\d+)/);
      if (match) {
        endpoints.push({
          endpoint: match[1],
          deviceState: match[2],
          activeChannels: parseInt(match[3]),
          maxContacts: parseInt(match[4])
        });
      }
    }

    return endpoints;
  }

  /**
   * Check if extension is registered
   */
  async isExtensionRegistered(extension) {
    const status = await this.getExtensionStatus(extension);
    return status.status.registered;
  }

  /**
   * Get extension registration details
   */
  async getRegistrationDetails(extension) {
    const response = await this.ami.command(`pjsip show aor ${extension}`);

    return {
      extension: extension,
      aorDetails: this.parseAORDetails(response.Output || ''),
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Parse AOR (Address of Record) details
   */
  parseAORDetails(output) {
    const lines = output.split('\n');
    const aor = {
      contacts: [],
      maxContacts: 0,
      authenticateQualify: false
    };

    for (const line of lines) {
      if (line.includes('max_contacts')) {
        const match = line.match(/max_contacts\s*:\s*(\d+)/);
        if (match) {
          aor.maxContacts = parseInt(match[1]);
        }
      }

      if (line.includes('Contact:')) {
        const parts = line.split(/\s+/).filter(Boolean);
        if (parts.length >= 2) {
          aor.contacts.push({
            uri: parts[1],
            status: parts[2] || 'Unknown'
          });
        }
      }
    }

    return aor;
  }
}
