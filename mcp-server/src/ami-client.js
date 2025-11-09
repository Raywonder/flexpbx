/**
 * Enhanced Asterisk AMI Client
 * Inspired by FlexPBX patterns with improved error handling and connection management
 */

import net from 'net';
import { EventEmitter } from 'events';

export class AMIClient extends EventEmitter {
  constructor(config) {
    super();
    this.host = config.host || '127.0.0.1';
    this.port = config.port || 5038;
    this.username = config.username;
    this.secret = config.secret;
    this.timeout = config.timeout || 10000;
    this.socket = null;
    this.connected = false;
    this.authenticated = false;
    this.buffer = '';
    this.actionId = 0;
    this.pendingActions = new Map();
  }

  /**
   * Connect to Asterisk AMI
   */
  async connect() {
    return new Promise((resolve, reject) => {
      const timeoutId = setTimeout(() => {
        reject(new Error(`Connection timeout after ${this.timeout}ms`));
      }, this.timeout);

      this.socket = net.createConnection({
        host: this.host,
        port: this.port
      });

      this.socket.on('connect', () => {
        clearTimeout(timeoutId);
        this.connected = true;
        this.emit('connect');
      });

      this.socket.on('data', (data) => {
        this.handleData(data);
      });

      this.socket.on('error', (error) => {
        clearTimeout(timeoutId);
        this.connected = false;
        this.authenticated = false;
        this.emit('error', error);
        reject(error);
      });

      this.socket.on('close', () => {
        this.connected = false;
        this.authenticated = false;
        this.emit('disconnect');
      });

      // Wait for connection and greeting
      this.once('greeting', async () => {
        try {
          await this.login();
          resolve();
        } catch (error) {
          reject(error);
        }
      });
    });
  }

  /**
   * Handle incoming data from socket
   */
  handleData(data) {
    this.buffer += data.toString();

    // Process complete messages (separated by double newlines)
    const messages = this.buffer.split('\r\n\r\n');
    this.buffer = messages.pop(); // Keep incomplete message in buffer

    for (const message of messages) {
      if (!message.trim()) continue;

      const parsed = this.parseMessage(message);

      if (parsed.type === 'greeting') {
        this.emit('greeting', parsed);
      } else if (parsed.type === 'response') {
        this.handleResponse(parsed);
      } else if (parsed.type === 'event') {
        this.emit('event', parsed);
      }
    }
  }

  /**
   * Parse AMI message
   */
  parseMessage(message) {
    const lines = message.split('\r\n');
    const parsed = {};

    for (const line of lines) {
      const colonIndex = line.indexOf(':');
      if (colonIndex === -1) continue;

      const key = line.substring(0, colonIndex).trim();
      const value = line.substring(colonIndex + 1).trim();
      parsed[key] = value;
    }

    // Determine message type
    if (parsed.Response) {
      parsed.type = 'response';
    } else if (parsed.Event) {
      parsed.type = 'event';
    } else if (parsed['Asterisk Call Manager']) {
      parsed.type = 'greeting';
    }

    return parsed;
  }

  /**
   * Handle response to action
   */
  handleResponse(response) {
    const actionId = response.ActionID;
    if (!actionId) return;

    const pending = this.pendingActions.get(actionId);
    if (!pending) return;

    clearTimeout(pending.timeout);

    if (response.Response === 'Success') {
      pending.resolve(response);
    } else {
      pending.reject(new Error(response.Message || 'Action failed'));
    }

    this.pendingActions.delete(actionId);
  }

  /**
   * Login to AMI
   */
  async login() {
    const response = await this.sendAction({
      Action: 'Login',
      Username: this.username,
      Secret: this.secret
    });

    if (response.Response === 'Success') {
      this.authenticated = true;
      this.emit('authenticated');
      return response;
    }

    throw new Error('Authentication failed');
  }

  /**
   * Send action to AMI
   */
  async sendAction(action) {
    if (!this.connected) {
      throw new Error('Not connected to AMI');
    }

    return new Promise((resolve, reject) => {
      const actionId = `action-${++this.actionId}-${Date.now()}`;
      action.ActionID = actionId;

      // Build message
      let message = '';
      for (const [key, value] of Object.entries(action)) {
        message += `${key}: ${value}\r\n`;
      }
      message += '\r\n';

      // Set timeout for response
      const timeoutId = setTimeout(() => {
        this.pendingActions.delete(actionId);
        reject(new Error(`Action timeout: ${action.Action}`));
      }, this.timeout);

      // Store pending action
      this.pendingActions.set(actionId, {
        resolve,
        reject,
        timeout: timeoutId
      });

      // Send to socket
      this.socket.write(message);
    });
  }

  /**
   * Disconnect from AMI
   */
  async disconnect() {
    if (this.authenticated) {
      try {
        await this.sendAction({ Action: 'Logoff' });
      } catch (error) {
        // Ignore logout errors
      }
    }

    if (this.socket) {
      this.socket.end();
      this.socket = null;
    }

    this.connected = false;
    this.authenticated = false;
  }

  /**
   * Check connection status
   */
  isConnected() {
    return this.connected && this.authenticated;
  }

  /**
   * Get Asterisk status
   */
  async getStatus() {
    return await this.sendAction({ Action: 'CoreStatus' });
  }

  /**
   * Get active channels
   */
  async getChannels() {
    return await this.sendAction({ Action: 'CoreShowChannels' });
  }

  /**
   * Get SIP peers
   */
  async getSIPPeers() {
    return await this.sendAction({ Action: 'SIPpeers' });
  }

  /**
   * Get PJSIP endpoints
   */
  async getPJSIPEndpoints() {
    return await this.sendAction({ Action: 'PJSIPShowEndpoints' });
  }

  /**
   * Originate a call
   */
  async originate(options) {
    const action = {
      Action: 'Originate',
      Channel: options.channel,
      Exten: options.extension,
      Context: options.context || 'from-internal',
      Priority: options.priority || 1,
      Timeout: options.timeout || 30000
    };

    if (options.callerId) {
      action.CallerID = options.callerId;
    }

    return await this.sendAction(action);
  }

  /**
   * Hangup a channel
   */
  async hangup(channel, cause) {
    const action = {
      Action: 'Hangup',
      Channel: channel
    };

    if (cause) {
      action.Cause = cause;
    }

    return await this.sendAction(action);
  }

  /**
   * Execute CLI command
   */
  async command(cmd) {
    return await this.sendAction({
      Action: 'Command',
      Command: cmd
    });
  }
}
