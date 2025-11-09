#!/usr/bin/env node

/**
 * Test Conference Management
 * Demonstrates conference management capabilities
 */

import { AMIClient } from '../src/ami-client.js';
import { ConferenceManager } from '../src/conference-manager.js';
import dotenv from 'dotenv';

dotenv.config();

async function testConference() {
  console.log('FlexPBX VoIP MCP - Conference Management Test\n');

  const config = {
    host: process.env.AMI_HOST || '127.0.0.1',
    port: parseInt(process.env.AMI_PORT || '5038'),
    username: process.env.AMI_USERNAME,
    secret: process.env.AMI_SECRET,
    timeout: parseInt(process.env.AMI_TIMEOUT || '10000')
  };

  if (!config.username || !config.secret) {
    console.error('ERROR: AMI_USERNAME and AMI_SECRET must be set in .env file');
    process.exit(1);
  }

  const ami = new AMIClient(config);
  const conferenceManager = new ConferenceManager(ami);

  try {
    console.log('Connecting to Asterisk AMI...');
    await ami.connect();
    console.log('✓ Connected\n');

    console.log('Test 1: List all conferences');
    const conferences = await conferenceManager.listConferences();
    console.log('✓ Conferences:', conferences);
    console.log();

    if (conferences.length > 0) {
      const conferenceNum = conferences[0].conference;

      console.log(`Test 2: Get participants in conference ${conferenceNum}`);
      const participants = await conferenceManager.getParticipants(conferenceNum);
      console.log('✓ Participants:', participants);
      console.log();

      console.log(`Test 3: Get conference statistics for ${conferenceNum}`);
      const stats = await conferenceManager.getConferenceStats(conferenceNum);
      console.log('✓ Statistics:', stats);
      console.log();

      // Don't actually lock/mute/kick in test
      console.log('Note: Mute, kick, and lock operations not tested to avoid disruption');
    } else {
      console.log('No active conferences found');
      console.log('To test conference features:');
      console.log('  1. Dial into a conference room from two extensions');
      console.log('  2. Run this test again');
    }

    console.log('\nDisconnecting...');
    await ami.disconnect();
    console.log('✓ Disconnected\n');

    console.log('Conference tests completed! ✓');
  } catch (error) {
    console.error('✗ Test failed:', error.message);
    process.exit(1);
  }
}

testConference();
