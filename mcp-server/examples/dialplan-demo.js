#!/usr/bin/env node

/**
 * Dial Plan Demo
 * Demonstrates dial plan rule generation for different SIP clients
 */

import { DialPlanManager } from '../src/dialplan-manager.js';

console.log('FlexPBX VoIP MCP - Dial Plan Demo\n');

const dialPlan = new DialPlanManager();

console.log('=== Test 1: JSON Format (Default) ===');
const jsonRules = dialPlan.getDialRules('json');
console.log(JSON.stringify(jsonRules, null, 2));
console.log();

console.log('=== Test 2: Groundwire Format ===');
const groundwireRules = dialPlan.getDialRules('groundwire');
console.log(JSON.stringify(groundwireRules, null, 2));
console.log();

console.log('=== Test 3: Linphone Format ===');
const linphoneRules = dialPlan.getDialRules('linphone');
console.log(JSON.stringify(linphoneRules, null, 2));
console.log();

console.log('=== Test 4: Zoiper Format ===');
const zoiperRules = dialPlan.getDialRules('zoiper');
console.log(JSON.stringify(zoiperRules, null, 2));
console.log();

console.log('=== Test 5: Feature Codes ===');
const featureCodes = dialPlan.getFeatureCodes();
console.log(JSON.stringify(featureCodes, null, 2));
console.log();

console.log('=== Test 6: Number Validation ===');
const testNumbers = [
  '2001',           // Valid extension
  '*97',            // Valid feature code
  '18005551234',    // Valid US number
  '011441234567890', // Valid international
  '999',            // Invalid
  'abc'             // Invalid
];

for (const number of testNumbers) {
  const validation = dialPlan.validateNumber(number);
  console.log(`${number}: ${validation.valid ? '✓ Valid' : '✗ Invalid'}`);
  if (validation.valid) {
    console.log(`  Type: ${validation.type}, Rule: ${validation.rule}`);
  } else {
    console.log(`  Error: ${validation.error}`);
  }
}

console.log('\nDial plan demo completed! ✓');
