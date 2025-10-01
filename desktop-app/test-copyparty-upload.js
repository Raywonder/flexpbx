#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const https = require('https');
const FormData = require('form-data');

// Test small file upload to CopyParty
const CONFIG = {
  url: "https://files.raywonderis.me",
  username: "admin",
  password: "hub-node-api-2024",
  testPath: "/tetoeehoward/apps/"
};

// Allow self-signed certificates
process.env["NODE_TLS_REJECT_UNAUTHORIZED"] = 0;

async function createTestFile() {
  const testContent = `FlexPBX Desktop v1.0.0 Test Upload
Generated: ${new Date().toISOString()}

This is a test file to verify CopyParty upload functionality.
`;

  const testFile = path.join(__dirname, 'test-upload.txt');
  fs.writeFileSync(testFile, testContent);
  return testFile;
}

async function uploadTestFile(filePath, uploadPath) {
  return new Promise((resolve, reject) => {
    const form = new FormData();
    const fileName = path.basename(filePath);

    console.log(`📤 Testing upload of ${fileName}...`);

    // Add required fields for CopyParty API
    form.append('act', 'up');
    form.append('file', fs.createReadStream(filePath), fileName);

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
    console.log(`   📍 Target: ${fullUrl}`);

    const req = https.request(fullUrl, options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        console.log(`   📊 Response: ${res.statusCode}`);
        console.log(`   📄 Response body: ${data.substring(0, 500)}`);
        if (res.statusCode >= 200 && res.statusCode < 300) {
          console.log(`   ✅ Success: ${fileName}`);
          resolve({ fileName, status: 'success', path: uploadPath });
        } else {
          console.log(`   ❌ Failed: ${res.statusCode}`);
          resolve({ fileName, status: 'failed', error: `${res.statusCode}: ${data}` });
        }
      });
    });

    req.on('error', (err) => {
      console.log(`   ❌ Error: ${err.message}`);
      resolve({ fileName, status: 'error', error: err.message });
    });

    req.setTimeout(30000, () => {
      req.destroy();
      resolve({ fileName, status: 'timeout', error: 'Upload timeout' });
    });

    form.pipe(req);
  });
}

async function testCopyPartyUpload() {
  console.log('🧪 CopyParty Upload Test for FlexPBX\n');

  // Create test file
  const testFile = await createTestFile();
  console.log(`📝 Created test file: ${testFile}`);

  // Test upload to apps folder
  console.log('\n📁 Testing upload to apps folder...');
  const result = await uploadTestFile(testFile, CONFIG.testPath);

  console.log('\n📊 Test Results:');
  console.log(`Status: ${result.status}`);
  if (result.error) {
    console.log(`Error: ${result.error}`);
  }

  // Clean up
  fs.unlinkSync(testFile);
  console.log('\n🧹 Cleaned up test file');

  if (result.status === 'success') {
    console.log('\n✅ CopyParty upload is working! Ready to upload FlexPBX installers.');
    console.log(`🔗 Test file URL: ${CONFIG.url}${CONFIG.testPath}test-upload.txt`);
  } else {
    console.log('\n❌ CopyParty upload failed. Need to investigate the API.');
  }
}

if (require.main === module) {
  testCopyPartyUpload().catch(console.error);
}