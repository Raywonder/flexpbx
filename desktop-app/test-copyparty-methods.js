#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const https = require('https');
const FormData = require('form-data');

// Test different CopyParty upload methods
const CONFIG = {
  url: "https://files.raywonderis.me",
  username: "tappedin",
  password: "hub-node-api-2024",
  testPath: "/tappedin/apps/"
};

process.env["NODE_TLS_REJECT_UNAUTHORIZED"] = 0;

async function testUploadMethod(filePath, uploadPath, method) {
  return new Promise((resolve, reject) => {
    const form = new FormData();
    const fileName = path.basename(filePath);

    console.log(`📤 Testing method: ${method}...`);

    // Try different methods
    switch (method) {
      case 'no-act':
        form.append('file', fs.createReadStream(filePath), fileName);
        break;
      case 'act-up':
        form.append('act', 'up');
        form.append('file', fs.createReadStream(filePath), fileName);
        break;
      case 'act-upload':
        form.append('act', 'upload');
        form.append('file', fs.createReadStream(filePath), fileName);
        break;
      case 'act-put':
        form.append('act', 'put');
        form.append('file', fs.createReadStream(filePath), fileName);
        break;
      case 'action-upload':
        form.append('action', 'upload');
        form.append('file', fs.createReadStream(filePath), fileName);
        break;
      default:
        form.append('file', fs.createReadStream(filePath), fileName);
    }

    const auth = Buffer.from(`${CONFIG.username}:${CONFIG.password}`).toString('base64');

    const options = {
      method: 'POST',
      headers: {
        'Authorization': `Basic ${auth}`,
        ...form.getHeaders()
      },
      rejectUnauthorized: false
    };

    const fullUrl = `${CONFIG.url}${uploadPath}`;

    const req = https.request(fullUrl, options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        console.log(`   📊 Response: ${res.statusCode}`);
        console.log(`   📄 Body: ${data.substring(0, 300)}`);

        if (res.statusCode >= 200 && res.statusCode < 300) {
          console.log(`   ✅ Success with method: ${method}`);
          resolve({ method, status: 'success', response: data });
        } else {
          console.log(`   ❌ Failed with method: ${method}`);
          resolve({ method, status: 'failed', error: `${res.statusCode}: ${data}` });
        }
      });
    });

    req.on('error', (err) => {
      console.log(`   ❌ Error: ${err.message}`);
      resolve({ method, status: 'error', error: err.message });
    });

    req.setTimeout(30000, () => {
      req.destroy();
      resolve({ method, status: 'timeout', error: 'Upload timeout' });
    });

    form.pipe(req);
  });
}

async function testCopyPartyMethods() {
  console.log('🧪 Testing Different CopyParty Upload Methods\n');

  // Create test file
  const testContent = `FlexPBX Desktop Test Upload
Generated: ${new Date().toISOString()}
Testing different CopyParty API methods.
`;

  const testFile = path.join(__dirname, 'method-test.txt');
  fs.writeFileSync(testFile, testContent);

  const methods = [
    'no-act',
    'act-up',
    'act-upload',
    'act-put',
    'action-upload'
  ];

  const results = [];

  for (const method of methods) {
    console.log(`\n🔄 Testing method: ${method}`);
    const result = await testUploadMethod(testFile, CONFIG.testPath, method);
    results.push(result);

    // Wait between tests
    await new Promise(resolve => setTimeout(resolve, 1000));
  }

  // Clean up
  fs.unlinkSync(testFile);

  console.log('\n📊 Method Test Results:');
  console.log('========================');
  results.forEach(result => {
    const status = result.status === 'success' ? '✅' : '❌';
    console.log(`${status} ${result.method} - ${result.status}`);
    if (result.error) {
      console.log(`   Error: ${result.error.substring(0, 100)}`);
    }
  });

  const workingMethods = results.filter(r => r.status === 'success');
  if (workingMethods.length > 0) {
    console.log('\n✅ Working methods found:');
    workingMethods.forEach(method => {
      console.log(`   🎯 ${method.method}`);
    });
  } else {
    console.log('\n❌ No working methods found. CopyParty API may have changed.');
    console.log('\n💡 Suggestions:');
    console.log('   - Check if FlexPBX folder needs to be created manually');
    console.log('   - Try uploading via web interface first');
    console.log('   - Check CopyParty documentation for API changes');
  }
}

if (require.main === module) {
  testCopyPartyMethods().catch(console.error);
}