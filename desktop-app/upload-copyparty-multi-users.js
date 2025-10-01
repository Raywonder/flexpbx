#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const https = require('https');
const FormData = require('form-data');

// FlexPBX Desktop CopyParty upload with multiple user accounts
const CONFIGS = [
  {
    name: "tappedin",
    url: "https://files.raywonderis.me",
    username: "tappedin",
    password: "hub-node-api-2024",
    paths: {
      apps: "/tappedin/apps/FlexPBX/",
      public: "/public/downloads/FlexPBX/"
    }
  },
  {
    name: "devinecr",
    url: "https://files.raywonderis.me",
    username: "devinecr",
    password: "hub-node-api-2024",
    paths: {
      apps: "/devinecr/apps/FlexPBX/",
      public: "/public/downloads/FlexPBX/"
    }
  }
];

// Allow self-signed certificates
process.env["NODE_TLS_REJECT_UNAUTHORIZED"] = 0;

async function uploadSingleFile(filePath, uploadPath, config) {
  return new Promise((resolve, reject) => {
    const form = new FormData();
    const fileName = path.basename(filePath);
    const fileSize = fs.statSync(filePath).size;
    const fileSizeMB = (fileSize / (1024 * 1024)).toFixed(1);

    console.log(`📤 Uploading ${fileName} (${fileSizeMB}MB) via ${config.name}...`);

    // Add required fields for CopyParty API
    form.append('act', 'up');
    form.append('file', fs.createReadStream(filePath), fileName);

    const auth = Buffer.from(`${config.username}:${config.password}`).toString('base64');

    const options = {
      method: 'POST',
      headers: {
        'Authorization': `Basic ${auth}`,
        ...form.getHeaders()
      },
      rejectUnauthorized: false
    };

    const fullUrl = `${config.url}${uploadPath}`;
    console.log(`   📍 Target: ${fullUrl}`);

    const req = https.request(fullUrl, options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        console.log(`   📊 Response: ${res.statusCode}`);
        if (res.statusCode >= 200 && res.statusCode < 300) {
          console.log(`   ✅ Success: ${fileName} via ${config.name}`);
          resolve({ fileName, status: 'success', path: uploadPath, config: config.name });
        } else {
          console.log(`   ❌ Failed: ${res.statusCode} - ${data.substring(0, 200)}`);
          resolve({ fileName, status: 'failed', error: `${res.statusCode}: ${data}`, config: config.name });
        }
      });
    });

    req.on('error', (err) => {
      console.log(`   ❌ Error: ${err.message}`);
      resolve({ fileName, status: 'error', error: err.message, config: config.name });
    });

    req.setTimeout(1200000, () => { // 20 minutes timeout for large files
      req.destroy();
      resolve({ fileName, status: 'timeout', error: 'Upload timeout', config: config.name });
    });

    form.pipe(req);
  });
}

async function testSmallFileFirst() {
  console.log('🧪 Testing with small file first...\n');

  // Create test file
  const testContent = `FlexPBX Desktop v1.0.0 Test Upload
Generated: ${new Date().toISOString()}
Testing CopyParty upload with multiple user accounts.
`;

  const testFile = path.join(__dirname, 'flexpbx-test.txt');
  fs.writeFileSync(testFile, testContent);

  const results = [];

  // Test each config
  for (const config of CONFIGS) {
    console.log(`\n📁 Testing ${config.name} account:`);

    // Test apps folder
    console.log(`   → Testing apps folder for ${config.name}:`);
    const result1 = await uploadSingleFile(testFile, config.paths.apps, config);
    results.push(result1);

    if (result1.status === 'success') {
      console.log(`   → Testing public folder for ${config.name}:`);
      const result2 = await uploadSingleFile(testFile, config.paths.public, config);
      results.push(result2);
    }

    // Wait between accounts
    await new Promise(resolve => setTimeout(resolve, 2000));
  }

  // Clean up test file
  fs.unlinkSync(testFile);

  console.log('\n📊 Test Results:');
  results.forEach(result => {
    const status = result.status === 'success' ? '✅' : '❌';
    console.log(`${status} ${result.fileName} via ${result.config} - ${result.status}`);
    if (result.error) {
      console.log(`   Error: ${result.error}`);
    }
  });

  const successCount = results.filter(r => r.status === 'success').length;
  return successCount > 0;
}

async function uploadFlexPBXInstallers() {
  const distDir = path.join(__dirname, 'dist');

  if (!fs.existsSync(distDir)) {
    console.log('❌ No dist directory found. Please build the installers first with "npm run build"');
    return;
  }

  // Test with small file first
  const testPassed = await testSmallFileFirst();

  if (!testPassed) {
    console.log('\n❌ Small file test failed. Stopping upload process.');
    return;
  }

  console.log('\n✅ Small file test passed! Proceeding with installers...\n');

  // Find all installer files
  const allFiles = fs.readdirSync(distDir).filter(file => {
    return file.endsWith('.dmg') ||
           file.endsWith('.exe') ||
           file.endsWith('.AppImage') ||
           file.endsWith('.zip');
  });

  if (allFiles.length === 0) {
    console.log('❌ No installer files found in dist directory');
    return;
  }

  // Sort by file size (smallest first for better success rate)
  const filesWithSizes = allFiles.map(file => {
    const filePath = path.join(distDir, file);
    const size = fs.statSync(filePath).size;
    return { file, path: filePath, size };
  }).sort((a, b) => a.size - b.size);

  console.log(`🚀 FlexPBX Desktop v1.0.0 Installer Upload (Multi-User)`);
  console.log(`📦 Found ${filesWithSizes.length} installer files to upload\n`);

  // Show what we're uploading
  filesWithSizes.forEach(fileInfo => {
    const sizeMB = (fileInfo.size / (1024 * 1024)).toFixed(1);
    console.log(`   📄 ${fileInfo.file} (${sizeMB}MB)`);
  });

  console.log('\n🎯 Starting installer uploads...\n');

  const results = [];

  // Try uploading with the first working config
  let workingConfig = null;

  // Test which config works first
  for (const config of CONFIGS) {
    console.log(`\n🧪 Testing ${config.name} with smallest installer...`);
    const testFile = filesWithSizes[0];
    const testResult = await uploadSingleFile(testFile.path, config.paths.apps, config);

    if (testResult.status === 'success') {
      console.log(`✅ ${config.name} is working! Using this account for uploads.`);
      workingConfig = config;
      results.push(testResult);
      break;
    } else {
      console.log(`❌ ${config.name} failed: ${testResult.error}`);
    }
  }

  if (!workingConfig) {
    console.log('\n❌ No working config found. All accounts failed.');
    return;
  }

  // Upload remaining files with working config
  for (let i = 1; i < filesWithSizes.length; i++) {
    const fileInfo = filesWithSizes[i];

    console.log(`\n📁 Uploading ${fileInfo.file} to ${workingConfig.name} apps folder:`);
    const result1 = await uploadSingleFile(fileInfo.path, workingConfig.paths.apps, workingConfig);
    results.push(result1);

    if (result1.status === 'success') {
      console.log(`\n📁 Uploading ${fileInfo.file} to public downloads:`);
      const result2 = await uploadSingleFile(fileInfo.path, workingConfig.paths.public, workingConfig);
      results.push(result2);
    }

    // Wait between uploads to avoid overwhelming server
    console.log(`   ⏳ Waiting 5 seconds before next upload...`);
    await new Promise(resolve => setTimeout(resolve, 5000));
  }

  console.log('\n📊 Upload Results Summary:');
  console.log('================================');
  results.forEach(result => {
    const status = result.status === 'success' ? '✅' : '❌';
    console.log(`${status} ${result.fileName} via ${result.config} - ${result.status}`);
    if (result.error) {
      console.log(`   Error: ${result.error}`);
    }
  });

  const successCount = results.filter(r => r.status === 'success').length;
  console.log(`\n🎯 Success Rate: ${successCount}/${results.length} uploads`);

  if (successCount > 0 && workingConfig) {
    console.log('\n🔗 Download URLs:');
    console.log(`📱 User Apps: ${workingConfig.url}${workingConfig.paths.apps}`);
    console.log(`🌐 Public Downloads: ${workingConfig.url}${workingConfig.paths.public}`);

    console.log('\n📋 Individual Download Links:');
    const successfulUploads = results.filter(r => r.status === 'success');
    successfulUploads.forEach(result => {
      const config = CONFIGS.find(c => c.name === result.config);
      console.log(`   ${config.url}${result.path}${result.fileName}`);
    });
  }

  console.log('\n🎉 FlexPBX Desktop v1.0.0 Upload Complete!');
}

if (require.main === module) {
  uploadFlexPBXInstallers().catch(console.error);
}