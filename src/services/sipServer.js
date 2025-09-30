const dgram = require('dgram');
const net = require('net');
const tls = require('tls');
const EventEmitter = require('events');
const crypto = require('crypto');
const logger = require('../utils/logger');

class AccessibleSIPServer extends EventEmitter {
  constructor(options = {}) {
    super();
    this.config = {
      udpPort: options.udpPort || 5060,
      tcpPort: options.tcpPort || 5060,
      tlsPort: options.tlsPort || 5061,
      wsPort: options.wsPort || 8088,
      wssPort: options.wssPort || 8089,
      rtpStartPort: options.rtpStartPort || 10000,
      rtpEndPort: options.rtpEndPort || 20000,
      realm: options.realm || 'accessible.pbx',
      enableAccessibility: options.enableAccessibility !== false,
      accessibilityVoiceSpeed: options.accessibilityVoiceSpeed || 150,
      screenReaderAnnouncements: options.screenReaderAnnouncements !== false
    };

    this.registrations = new Map();
    this.activeCalls = new Map();
    this.conferences = new Map();
    this.queues = new Map();
    this.voicemailBoxes = new Map();
    this.callDetailRecords = [];
    this.accessibilityFeatures = new Map();

    this.initializeServers();
    this.initializeAccessibilityFeatures();
  }

  initializeServers() {
    this.udpServer = dgram.createSocket('udp4');
    this.tcpServer = net.createServer();

    if (this.config.tlsEnabled) {
      this.tlsServer = tls.createServer({
        key: this.config.tlsKey,
        cert: this.config.tlsCert
      });
    }

    this.setupUDPServer();
    this.setupTCPServer();
    this.setupWebSocketServer();
  }

  initializeAccessibilityFeatures() {
    this.accessibilityFeatures.set('voiceAnnouncements', {
      enabled: this.config.screenReaderAnnouncements,
      speed: this.config.accessibilityVoiceSpeed,
      language: 'en-US'
    });

    this.accessibilityFeatures.set('audioFeedback', {
      enabled: true,
      dtmfVolume: 0.8,
      confirmationTones: true
    });

    this.accessibilityFeatures.set('keyboardShortcuts', {
      enabled: true,
      customMappings: new Map()
    });
  }

  setupUDPServer() {
    this.udpServer.on('message', (msg, rinfo) => {
      this.handleSIPMessage(msg.toString(), 'udp', rinfo);
    });

    this.udpServer.on('listening', () => {
      const address = this.udpServer.address();
      logger.info(`SIP UDP Server listening on ${address.address}:${address.port}`);
    });

    this.udpServer.bind(this.config.udpPort);
  }

  setupTCPServer() {
    this.tcpServer.on('connection', (socket) => {
      socket.on('data', (data) => {
        this.handleSIPMessage(data.toString(), 'tcp', socket);
      });
    });

    this.tcpServer.listen(this.config.tcpPort, () => {
      logger.info(`SIP TCP Server listening on port ${this.config.tcpPort}`);
    });
  }

  setupWebSocketServer() {
    const WebSocket = require('ws');
    const http = require('http');
    const https = require('https');

    const wsServer = http.createServer();
    this.ws = new WebSocket.Server({ server: wsServer });

    this.ws.on('connection', (ws, req) => {
      const clientId = this.generateClientId();
      ws.clientId = clientId;

      ws.on('message', (message) => {
        this.handleWebSocketMessage(ws, message.toString());
      });

      ws.on('close', () => {
        this.handleWebSocketDisconnect(ws);
      });

      this.announceToScreenReader(ws, 'WebSocket connection established');
    });

    wsServer.listen(this.config.wsPort, () => {
      logger.info(`SIP WebSocket Server listening on port ${this.config.wsPort}`);
    });
  }

  handleSIPMessage(message, transport, source) {
    const parsedMessage = this.parseSIPMessage(message);

    if (!parsedMessage) {
      logger.error('Failed to parse SIP message');
      return;
    }

    const method = parsedMessage.method;
    const callId = parsedMessage.headers['Call-ID'];

    logger.debug(`Received ${method} via ${transport} for Call-ID: ${callId}`);

    switch (method) {
      case 'REGISTER':
        this.handleRegister(parsedMessage, transport, source);
        break;
      case 'INVITE':
        this.handleInvite(parsedMessage, transport, source);
        break;
      case 'ACK':
        this.handleAck(parsedMessage, transport, source);
        break;
      case 'BYE':
        this.handleBye(parsedMessage, transport, source);
        break;
      case 'CANCEL':
        this.handleCancel(parsedMessage, transport, source);
        break;
      case 'OPTIONS':
        this.handleOptions(parsedMessage, transport, source);
        break;
      case 'INFO':
        this.handleInfo(parsedMessage, transport, source);
        break;
      case 'MESSAGE':
        this.handleMessage(parsedMessage, transport, source);
        break;
      case 'NOTIFY':
        this.handleNotify(parsedMessage, transport, source);
        break;
      case 'SUBSCRIBE':
        this.handleSubscribe(parsedMessage, transport, source);
        break;
      default:
        this.send501NotImplemented(parsedMessage, transport, source);
    }

    this.emit('sipMessage', { method, callId, transport });
  }

  handleRegister(message, transport, source) {
    const contact = this.parseContact(message.headers['Contact']);
    const from = this.parseURI(message.headers['From']);
    const expires = parseInt(message.headers['Expires'] || '3600');

    if (expires > 0) {
      this.registrations.set(from.user, {
        contact: contact.uri,
        transport,
        source,
        expires: Date.now() + (expires * 1000),
        accessibility: {
          screenReader: message.headers['X-Accessibility-ScreenReader'] === 'true',
          voiceSpeed: parseInt(message.headers['X-Accessibility-Voice-Speed'] || '150'),
          highContrast: message.headers['X-Accessibility-High-Contrast'] === 'true'
        }
      });

      logger.info(`Registered user ${from.user} from ${contact.uri}`);
      this.announceRegistration(from.user, true);
    } else {
      this.registrations.delete(from.user);
      logger.info(`Unregistered user ${from.user}`);
      this.announceRegistration(from.user, false);
    }

    const response = this.buildSIPResponse(message, 200, 'OK');
    this.sendSIPMessage(response, transport, source);
  }

  handleInvite(message, transport, source) {
    const from = this.parseURI(message.headers['From']);
    const to = this.parseURI(message.headers['To']);
    const callId = message.headers['Call-ID'];
    const sdp = this.parseSDP(message.body);

    const call = {
      id: callId,
      from: from.user,
      to: to.user,
      startTime: Date.now(),
      state: 'ringing',
      transport,
      source,
      sdp,
      accessibility: {
        callerScreenReader: message.headers['X-Accessibility-Caller-SR'] === 'true',
        calleeScreenReader: this.isScreenReaderUser(to.user)
      }
    };

    this.activeCalls.set(callId, call);

    this.sendSIPMessage(this.buildSIPResponse(message, 100, 'Trying'), transport, source);
    this.sendSIPMessage(this.buildSIPResponse(message, 180, 'Ringing'), transport, source);

    this.announceIncomingCall(call);
    this.routeCall(call);

    this.emit('callStarted', call);
  }

  handleBye(message, transport, source) {
    const callId = message.headers['Call-ID'];
    const call = this.activeCalls.get(callId);

    if (call) {
      call.endTime = Date.now();
      call.duration = Math.floor((call.endTime - call.startTime) / 1000);

      this.callDetailRecords.push({
        ...call,
        recordedAt: new Date().toISOString()
      });

      this.activeCalls.delete(callId);
      this.announceCallEnded(call);

      const response = this.buildSIPResponse(message, 200, 'OK');
      this.sendSIPMessage(response, transport, source);

      this.emit('callEnded', call);
    }
  }

  handleOptions(message, transport, source) {
    const response = this.buildSIPResponse(message, 200, 'OK');
    response.headers['Allow'] = 'INVITE, ACK, BYE, CANCEL, OPTIONS, MESSAGE, SUBSCRIBE, NOTIFY, INFO';
    response.headers['Accept'] = 'application/sdp, application/dtmf-relay';
    response.headers['X-Accessibility-Support'] = 'true';
    this.sendSIPMessage(response, transport, source);
  }

  routeCall(call) {
    const destination = this.registrations.get(call.to);

    if (destination) {
      this.forwardCall(call, destination);
    } else if (this.isConferenceNumber(call.to)) {
      this.routeToConference(call);
    } else if (this.isQueueNumber(call.to)) {
      this.routeToQueue(call);
    } else if (this.isVoicemailNumber(call.to)) {
      this.routeToVoicemail(call);
    } else {
      this.sendCallNotFound(call);
    }
  }

  announceToScreenReader(client, message) {
    if (this.config.screenReaderAnnouncements) {
      const announcement = {
        type: 'screen_reader_announcement',
        message,
        timestamp: Date.now(),
        priority: 'normal'
      };

      if (client && client.send) {
        client.send(JSON.stringify(announcement));
      }

      this.emit('accessibilityAnnouncement', announcement);
    }
  }

  announceRegistration(user, registered) {
    const message = registered ?
      `User ${user} has registered` :
      `User ${user} has unregistered`;

    this.emit('announcement', {
      type: 'registration',
      user,
      registered,
      message,
      voiceSpeed: this.config.accessibilityVoiceSpeed
    });
  }

  announceIncomingCall(call) {
    const message = `Incoming call from ${call.from} to ${call.to}`;

    if (call.accessibility.calleeScreenReader) {
      this.emit('announcement', {
        type: 'incoming_call',
        call,
        message,
        priority: 'high',
        voiceSpeed: this.config.accessibilityVoiceSpeed
      });
    }
  }

  announceCallEnded(call) {
    const message = `Call ended. Duration: ${call.duration} seconds`;

    this.emit('announcement', {
      type: 'call_ended',
      call,
      message,
      voiceSpeed: this.config.accessibilityVoiceSpeed
    });
  }

  parseSIPMessage(message) {
    const lines = message.split('\\r\\n');
    const requestLine = lines[0];
    const headers = {};
    let body = '';
    let bodyStart = false;

    for (let i = 1; i < lines.length; i++) {
      if (lines[i] === '') {
        bodyStart = true;
        body = lines.slice(i + 1).join('\\r\\n');
        break;
      }

      const colonIndex = lines[i].indexOf(':');
      if (colonIndex > 0) {
        const key = lines[i].substring(0, colonIndex).trim();
        const value = lines[i].substring(colonIndex + 1).trim();
        headers[key] = value;
      }
    }

    const requestParts = requestLine.split(' ');

    return {
      method: requestParts[0],
      uri: requestParts[1],
      version: requestParts[2],
      headers,
      body
    };
  }

  buildSIPResponse(request, statusCode, statusText) {
    const response = {
      statusLine: `SIP/2.0 ${statusCode} ${statusText}`,
      headers: {
        'Via': request.headers['Via'],
        'From': request.headers['From'],
        'To': request.headers['To'],
        'Call-ID': request.headers['Call-ID'],
        'CSeq': request.headers['CSeq'],
        'Content-Length': '0',
        'Server': 'AccessiblePBX/1.0',
        'X-Accessibility-Enabled': 'true'
      },
      body: ''
    };

    return this.formatSIPMessage(response);
  }

  formatSIPMessage(message) {
    let formatted = message.statusLine || message.requestLine;
    formatted += '\\r\\n';

    for (const [key, value] of Object.entries(message.headers)) {
      formatted += `${key}: ${value}\\r\\n`;
    }

    formatted += '\\r\\n';

    if (message.body) {
      formatted += message.body;
    }

    return formatted;
  }

  sendSIPMessage(message, transport, destination) {
    const messageBuffer = Buffer.from(message);

    switch (transport) {
      case 'udp':
        this.udpServer.send(messageBuffer, destination.port, destination.address);
        break;
      case 'tcp':
        destination.write(messageBuffer);
        break;
      case 'ws':
        destination.send(message);
        break;
    }
  }

  parseURI(uriHeader) {
    const match = uriHeader.match(/<sip:([^@]+)@([^>]+)>/);
    if (match) {
      return {
        user: match[1],
        domain: match[2]
      };
    }
    return null;
  }

  parseContact(contactHeader) {
    const match = contactHeader.match(/<([^>]+)>/);
    return {
      uri: match ? match[1] : contactHeader
    };
  }

  parseSDP(sdpBody) {
    if (!sdpBody) return null;

    const lines = sdpBody.split('\\n');
    const sdp = {
      version: '',
      origin: '',
      sessionName: '',
      media: []
    };

    lines.forEach(line => {
      if (line.startsWith('v=')) {
        sdp.version = line.substring(2);
      } else if (line.startsWith('o=')) {
        sdp.origin = line.substring(2);
      } else if (line.startsWith('s=')) {
        sdp.sessionName = line.substring(2);
      } else if (line.startsWith('m=')) {
        sdp.media.push(line.substring(2));
      }
    });

    return sdp;
  }

  generateClientId() {
    return crypto.randomBytes(16).toString('hex');
  }

  isScreenReaderUser(extension) {
    const registration = this.registrations.get(extension);
    return registration && registration.accessibility && registration.accessibility.screenReader;
  }

  isConferenceNumber(number) {
    return number.startsWith('9') && number.length === 4;
  }

  isQueueNumber(number) {
    return number.startsWith('8') && number.length === 4;
  }

  isVoicemailNumber(number) {
    return number === '*97' || number === '*98';
  }

  handleWebSocketMessage(ws, message) {
    try {
      const data = JSON.parse(message);

      switch (data.type) {
        case 'sip':
          this.handleSIPMessage(data.payload, 'ws', ws);
          break;
        case 'accessibility':
          this.handleAccessibilityRequest(ws, data);
          break;
        case 'dtmf':
          this.handleDTMF(ws, data);
          break;
      }
    } catch (error) {
      logger.error('WebSocket message handling error:', error);
    }
  }

  handleAccessibilityRequest(ws, data) {
    const response = {
      type: 'accessibility_response',
      feature: data.feature,
      settings: this.accessibilityFeatures.get(data.feature)
    };

    ws.send(JSON.stringify(response));
  }

  handleDTMF(ws, data) {
    const { digit, callId } = data;
    const call = this.activeCalls.get(callId);

    if (call) {
      this.emit('dtmf', { callId, digit });

      if (this.config.enableAccessibility) {
        const announcement = `Pressed ${digit}`;
        this.announceToScreenReader(ws, announcement);
      }
    }
  }

  handleWebSocketDisconnect(ws) {
    logger.info(`WebSocket client ${ws.clientId} disconnected`);
  }

  send501NotImplemented(message, transport, source) {
    const response = this.buildSIPResponse(message, 501, 'Not Implemented');
    this.sendSIPMessage(response, transport, source);
  }

  shutdown() {
    this.udpServer.close();
    this.tcpServer.close();
    if (this.tlsServer) {
      this.tlsServer.close();
    }
    if (this.ws) {
      this.ws.close();
    }

    logger.info('SIP Server shutdown complete');
  }
}

module.exports = AccessibleSIPServer;