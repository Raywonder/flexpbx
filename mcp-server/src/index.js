#!/usr/bin/env node

/**
 * FlexPBX VoIP MCP Server
 * Enhanced VoIP management server with conference, CDR, and advanced AMI features
 * Inspired by FlexPBX patterns with production-ready security and error handling
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import dotenv from 'dotenv';
import { AMIClient } from './ami-client.js';
import { ConferenceManager } from './conference-manager.js';
import { DialPlanManager } from './dialplan-manager.js';
import { CDRManager } from './cdr-manager.js';
import { ExtensionManager } from './extension-manager.js';

// Load environment variables
dotenv.config();

class FlexPBXVoIPServer {
  constructor() {
    this.server = new Server(
      {
        name: 'flexpbx-voip-mcp',
        version: '2.0.0',
      },
      {
        capabilities: {
          tools: {},
        },
      }
    );

    this.ami = null;
    this.conferenceManager = null;
    this.dialPlanManager = new DialPlanManager();
    this.cdrManager = null;
    this.extensionManager = null;

    this.setupToolHandlers();
    this.setupErrorHandling();
  }

  /**
   * Initialize AMI connection
   */
  async initializeAMI() {
    if (this.ami?.isConnected()) {
      return;
    }

    const config = {
      host: process.env.AMI_HOST || '127.0.0.1',
      port: parseInt(process.env.AMI_PORT || '5038'),
      username: process.env.AMI_USERNAME,
      secret: process.env.AMI_SECRET,
      timeout: parseInt(process.env.AMI_TIMEOUT || '10000')
    };

    if (!config.username || !config.secret) {
      throw new Error('AMI_USERNAME and AMI_SECRET must be set in environment');
    }

    this.ami = new AMIClient(config);
    await this.ami.connect();

    this.conferenceManager = new ConferenceManager(this.ami);
    this.extensionManager = new ExtensionManager(this.ami);
    this.cdrManager = new CDRManager({
      host: process.env.DB_HOST,
      port: process.env.DB_PORT,
      database: process.env.DB_NAME,
      user: process.env.DB_USER,
      password: process.env.DB_PASSWORD
    });
  }

  /**
   * Setup tool handlers
   */
  setupToolHandlers() {
    this.server.setRequestHandler(ListToolsRequestSchema, async () => ({
      tools: [
        // Core AMI Tools
        {
          name: 'asterisk_status',
          description: 'Get Asterisk system status and uptime',
          inputSchema: {
            type: 'object',
            properties: {},
          },
        },
        {
          name: 'list_channels',
          description: 'List all active channels (calls in progress)',
          inputSchema: {
            type: 'object',
            properties: {},
          },
        },
        {
          name: 'originate_call',
          description: 'Originate a new call from one extension to another',
          inputSchema: {
            type: 'object',
            properties: {
              channel: {
                type: 'string',
                description: 'Channel to originate (e.g., PJSIP/2000)',
              },
              extension: {
                type: 'string',
                description: 'Extension to dial',
              },
              context: {
                type: 'string',
                description: 'Dialplan context (default: from-internal)',
              },
              callerId: {
                type: 'string',
                description: 'Optional caller ID',
              },
            },
            required: ['channel', 'extension'],
          },
        },
        {
          name: 'hangup_channel',
          description: 'Hangup an active channel',
          inputSchema: {
            type: 'object',
            properties: {
              channel: {
                type: 'string',
                description: 'Channel to hangup',
              },
              cause: {
                type: 'string',
                description: 'Optional hangup cause code',
              },
            },
            required: ['channel'],
          },
        },

        // Conference Bridge Tools
        {
          name: 'list_conferences',
          description: 'List all active conference bridges',
          inputSchema: {
            type: 'object',
            properties: {},
          },
        },
        {
          name: 'get_conference_participants',
          description: 'Get participants in a specific conference',
          inputSchema: {
            type: 'object',
            properties: {
              conference: {
                type: 'string',
                description: 'Conference room number',
              },
            },
            required: ['conference'],
          },
        },
        {
          name: 'kick_participant',
          description: 'Remove a participant from conference',
          inputSchema: {
            type: 'object',
            properties: {
              conference: {
                type: 'string',
                description: 'Conference room number',
              },
              channel: {
                type: 'string',
                description: 'Participant channel to kick',
              },
            },
            required: ['conference', 'channel'],
          },
        },
        {
          name: 'mute_participant',
          description: 'Mute a conference participant',
          inputSchema: {
            type: 'object',
            properties: {
              conference: {
                type: 'string',
                description: 'Conference room number',
              },
              channel: {
                type: 'string',
                description: 'Participant channel to mute',
              },
            },
            required: ['conference', 'channel'],
          },
        },
        {
          name: 'unmute_participant',
          description: 'Unmute a conference participant',
          inputSchema: {
            type: 'object',
            properties: {
              conference: {
                type: 'string',
                description: 'Conference room number',
              },
              channel: {
                type: 'string',
                description: 'Participant channel to unmute',
              },
            },
            required: ['conference', 'channel'],
          },
        },
        {
          name: 'lock_conference',
          description: 'Lock a conference to prevent new participants',
          inputSchema: {
            type: 'object',
            properties: {
              conference: {
                type: 'string',
                description: 'Conference room number',
              },
            },
            required: ['conference'],
          },
        },
        {
          name: 'unlock_conference',
          description: 'Unlock a conference to allow new participants',
          inputSchema: {
            type: 'object',
            properties: {
              conference: {
                type: 'string',
                description: 'Conference room number',
              },
            },
            required: ['conference'],
          },
        },

        // Extension Management Tools
        {
          name: 'list_extensions',
          description: 'List all PJSIP extensions and their status',
          inputSchema: {
            type: 'object',
            properties: {},
          },
        },
        {
          name: 'get_extension_status',
          description: 'Get detailed status for a specific extension',
          inputSchema: {
            type: 'object',
            properties: {
              extension: {
                type: 'string',
                description: 'Extension number',
              },
            },
            required: ['extension'],
          },
        },
        {
          name: 'get_extension_registration',
          description: 'Get registration details for an extension',
          inputSchema: {
            type: 'object',
            properties: {
              extension: {
                type: 'string',
                description: 'Extension number',
              },
            },
            required: ['extension'],
          },
        },

        // Dial Plan Tools
        {
          name: 'get_dial_rules',
          description: 'Get dial plan rules for SIP client configuration',
          inputSchema: {
            type: 'object',
            properties: {
              format: {
                type: 'string',
                description: 'Output format (json, groundwire, linphone, zoiper)',
                enum: ['json', 'groundwire', 'linphone', 'zoiper'],
              },
            },
          },
        },
        {
          name: 'get_feature_codes',
          description: 'Get available feature codes (*97, *45, etc.)',
          inputSchema: {
            type: 'object',
            properties: {},
          },
        },
        {
          name: 'validate_number',
          description: 'Validate a dialed number against dial plan rules',
          inputSchema: {
            type: 'object',
            properties: {
              number: {
                type: 'string',
                description: 'Number to validate',
              },
            },
            required: ['number'],
          },
        },

        // CDR Tools
        {
          name: 'query_cdr',
          description: 'Query call detail records (requires database setup)',
          inputSchema: {
            type: 'object',
            properties: {
              startDate: {
                type: 'string',
                description: 'Start date (YYYY-MM-DD)',
              },
              endDate: {
                type: 'string',
                description: 'End date (YYYY-MM-DD)',
              },
              src: {
                type: 'string',
                description: 'Source extension',
              },
              dst: {
                type: 'string',
                description: 'Destination number',
              },
              limit: {
                type: 'number',
                description: 'Maximum records to return',
              },
            },
          },
        },
        {
          name: 'get_call_stats',
          description: 'Get call statistics (requires database setup)',
          inputSchema: {
            type: 'object',
            properties: {
              startDate: {
                type: 'string',
                description: 'Start date (YYYY-MM-DD)',
              },
              endDate: {
                type: 'string',
                description: 'End date (YYYY-MM-DD)',
              },
            },
          },
        },
        {
          name: 'get_extension_summary',
          description: 'Get call summary for a specific extension',
          inputSchema: {
            type: 'object',
            properties: {
              extension: {
                type: 'string',
                description: 'Extension number',
              },
              startDate: {
                type: 'string',
                description: 'Start date (YYYY-MM-DD)',
              },
              endDate: {
                type: 'string',
                description: 'End date (YYYY-MM-DD)',
              },
            },
            required: ['extension'],
          },
        },
      ],
    }));

    this.server.setRequestHandler(CallToolRequestSchema, async (request) =>
      this.handleToolCall(request)
    );
  }

  /**
   * Handle tool calls
   */
  async handleToolCall(request) {
    const { name, arguments: args } = request.params;

    try {
      // Initialize AMI for tools that need it
      if (this.requiresAMI(name)) {
        await this.initializeAMI();
      }

      switch (name) {
        // Core AMI Tools
        case 'asterisk_status':
          return await this.handleAsteriskStatus();
        case 'list_channels':
          return await this.handleListChannels();
        case 'originate_call':
          return await this.handleOriginateCall(args);
        case 'hangup_channel':
          return await this.handleHangupChannel(args);

        // Conference Tools
        case 'list_conferences':
          return await this.handleListConferences();
        case 'get_conference_participants':
          return await this.handleGetConferenceParticipants(args);
        case 'kick_participant':
          return await this.handleKickParticipant(args);
        case 'mute_participant':
          return await this.handleMuteParticipant(args);
        case 'unmute_participant':
          return await this.handleUnmuteParticipant(args);
        case 'lock_conference':
          return await this.handleLockConference(args);
        case 'unlock_conference':
          return await this.handleUnlockConference(args);

        // Extension Tools
        case 'list_extensions':
          return await this.handleListExtensions();
        case 'get_extension_status':
          return await this.handleGetExtensionStatus(args);
        case 'get_extension_registration':
          return await this.handleGetExtensionRegistration(args);

        // Dial Plan Tools
        case 'get_dial_rules':
          return await this.handleGetDialRules(args);
        case 'get_feature_codes':
          return await this.handleGetFeatureCodes();
        case 'validate_number':
          return await this.handleValidateNumber(args);

        // CDR Tools
        case 'query_cdr':
          return await this.handleQueryCDR(args);
        case 'get_call_stats':
          return await this.handleGetCallStats(args);
        case 'get_extension_summary':
          return await this.handleGetExtensionSummary(args);

        default:
          throw new Error(`Unknown tool: ${name}`);
      }
    } catch (error) {
      return {
        content: [
          {
            type: 'text',
            text: JSON.stringify({
              success: false,
              error: error.message,
              tool: name
            }, null, 2),
          },
        ],
      };
    }
  }

  /**
   * Check if tool requires AMI connection
   */
  requiresAMI(toolName) {
    const amiTools = [
      'asterisk_status',
      'list_channels',
      'originate_call',
      'hangup_channel',
      'list_conferences',
      'get_conference_participants',
      'kick_participant',
      'mute_participant',
      'unmute_participant',
      'lock_conference',
      'unlock_conference',
      'list_extensions',
      'get_extension_status',
      'get_extension_registration'
    ];

    return amiTools.includes(toolName);
  }

  // Tool Handler Implementations

  async handleAsteriskStatus() {
    const status = await this.ami.getStatus();
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, status }, null, 2),
        },
      ],
    };
  }

  async handleListChannels() {
    const channels = await this.ami.getChannels();
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, channels }, null, 2),
        },
      ],
    };
  }

  async handleOriginateCall(args) {
    const result = await this.ami.originate(args);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, result }, null, 2),
        },
      ],
    };
  }

  async handleHangupChannel(args) {
    const result = await this.ami.hangup(args.channel, args.cause);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, result }, null, 2),
        },
      ],
    };
  }

  async handleListConferences() {
    const conferences = await this.conferenceManager.listConferences();
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, conferences }, null, 2),
        },
      ],
    };
  }

  async handleGetConferenceParticipants(args) {
    const participants = await this.conferenceManager.getParticipants(args.conference);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, conference: args.conference, participants }, null, 2),
        },
      ],
    };
  }

  async handleKickParticipant(args) {
    const result = await this.conferenceManager.kickParticipant(args.conference, args.channel);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: result.success, ...result }, null, 2),
        },
      ],
    };
  }

  async handleMuteParticipant(args) {
    const result = await this.conferenceManager.muteParticipant(args.conference, args.channel);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: result.success, ...result }, null, 2),
        },
      ],
    };
  }

  async handleUnmuteParticipant(args) {
    const result = await this.conferenceManager.unmuteParticipant(args.conference, args.channel);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: result.success, ...result }, null, 2),
        },
      ],
    };
  }

  async handleLockConference(args) {
    const result = await this.conferenceManager.lockConference(args.conference);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: result.success, ...result }, null, 2),
        },
      ],
    };
  }

  async handleUnlockConference(args) {
    const result = await this.conferenceManager.unlockConference(args.conference);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: result.success, ...result }, null, 2),
        },
      ],
    };
  }

  async handleListExtensions() {
    const extensions = await this.extensionManager.getAllExtensionStatuses();
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, extensions }, null, 2),
        },
      ],
    };
  }

  async handleGetExtensionStatus(args) {
    const status = await this.extensionManager.getExtensionStatus(args.extension);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, ...status }, null, 2),
        },
      ],
    };
  }

  async handleGetExtensionRegistration(args) {
    const registration = await this.extensionManager.getRegistrationDetails(args.extension);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, ...registration }, null, 2),
        },
      ],
    };
  }

  async handleGetDialRules(args) {
    const rules = this.dialPlanManager.getDialRules(args.format || 'json');
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, ...rules }, null, 2),
        },
      ],
    };
  }

  async handleGetFeatureCodes() {
    const codes = this.dialPlanManager.getFeatureCodes();
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, ...codes }, null, 2),
        },
      ],
    };
  }

  async handleValidateNumber(args) {
    const validation = this.dialPlanManager.validateNumber(args.number);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ success: true, number: args.number, ...validation }, null, 2),
        },
      ],
    };
  }

  async handleQueryCDR(args) {
    const result = await this.cdrManager.queryCDR(args);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  }

  async handleGetCallStats(args) {
    const stats = await this.cdrManager.getCallStats(args);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify(stats, null, 2),
        },
      ],
    };
  }

  async handleGetExtensionSummary(args) {
    const summary = await this.cdrManager.getExtensionSummary(
      args.extension,
      args.startDate,
      args.endDate
    );
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify(summary, null, 2),
        },
      ],
    };
  }

  /**
   * Setup error handling
   */
  setupErrorHandling() {
    this.server.onerror = (error) => {
      console.error('[MCP Error]', error);
    };

    process.on('SIGINT', async () => {
      if (this.ami) {
        await this.ami.disconnect();
      }
      process.exit(0);
    });
  }

  /**
   * Start the server
   */
  async start() {
    const transport = new StdioServerTransport();
    await this.server.connect(transport);
    console.error('FlexPBX VoIP MCP Server running on stdio');
  }
}

// Start the server
const server = new FlexPBXVoIPServer();
server.start().catch(console.error);
