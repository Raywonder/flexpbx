#!/usr/bin/env node

/**
 * Test AMI Connection
 * Verifies that the AMI client can connect to Asterisk
 */

import { AMIClient } from '../src/ami-client.js';
import dotenv from 'dotenv';

dotenv.config();

async function testConnection() {
  console.log('FlexPBX VoIP MCP - Connection Test\n');

  const config = {
    host: process.env.AMI_HOST || '127.0.0.1',
    port: parseInt(process.env.AMI_PORT || '5038'),
    username: process.env.AMI_USERNAME,
    secret: process.env.AMI_SECRET,
    timeout: parseInt(process.env.AMI_TIMEOUT || '10000')
  };

  console.log('Configuration:');
  console.log(`  Host: ${config.host}`);
  console.log(`  Port: ${config.port}`);
  console.log(`  Username: ${config.username}`);
  console.log(`  Timeout: ${config.timeout}ms\n`);

  if (!config.username || !config.secret) {
    console.error('ERROR: AMI_USERNAME and AMI_SECRET must be set in .env file');
    process.exit(1);
  }

  const ami = new AMIClient(config);

  try {
    console.log('Connecting to Asterisk AMI...');
    await ami.connect();
    console.log('✓ Connected successfully\n');

    console.log('Getting Asterisk status...');
    const status = await ami.getStatus();
    console.log('✓ Status retrieved:');
    console.log(JSON.stringify(status, null, 2));
    console.log();

    console.log('Getting active channels...');
    const channels = await ami.getChannels();
    console.log('✓ Channels retrieved');
    console.log();

    console.log('Disconnecting...');
    await ami.disconnect();
    console.log('✓ Disconnected\n');

    console.log('All tests passed! ✓');
  } catch (error) {
    console.error('✗ Test failed:', error.message);
    process.exit(1);
  }
}

testConnection();
