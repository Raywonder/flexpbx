#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const https = require('https');
const FormData = require('form-data');

// FlexPBX Desktop CopyParty upload configuration
const CONFIG = {
  url: "https://files.raywonderis.me",
  username: "admin",
  password: "hub-node-api-2024",
  paths: {
    tetoeehoward: "/tetoeehoward/apps/FlexPBX/",
    public: "/public/downloads/FlexPBX/"
  }
};

// Allow self-signed certificates
process.env["NODE_TLS_REJECT_UNAUTHORIZED"] = 0;

async function uploadSingleFile(filePath, uploadPath) {
  return new Promise((resolve, reject) => {
    const form = new FormData();
    const fileName = path.basename(filePath);
    const fileSize = fs.statSync(filePath).size;
    const fileSizeMB = (fileSize / (1024 * 1024)).toFixed(1);

    console.log(`📤 Uploading ${fileName} (${fileSizeMB}MB)...`);

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
        if (res.statusCode >= 200 && res.statusCode < 300) {
          console.log(`   ✅ Success: ${fileName}`);
          resolve({ fileName, status: 'success', path: uploadPath });
        } else {
          console.log(`   ❌ Failed: ${res.statusCode} - ${data.substring(0, 200)}`);
          resolve({ fileName, status: 'failed', error: `${res.statusCode}: ${data}` });
        }
      });
    });

    req.on('error', (err) => {
      console.log(`   ❌ Error: ${err.message}`);
      resolve({ fileName, status: 'error', error: err.message });
    });

    req.setTimeout(1200000, () => { // 20 minutes timeout for large files
      req.destroy();
      resolve({ fileName, status: 'timeout', error: 'Upload timeout' });
    });

    form.pipe(req);
  });
}

async function uploadFlexPBXInstallers() {
  const distDir = path.join(__dirname, 'dist');

  if (!fs.existsSync(distDir)) {
    console.log('❌ No dist directory found. Please build the installers first with "npm run build"');
    return;
  }

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

  console.log(`🚀 FlexPBX Desktop v1.0.0 Installer Upload`);
  console.log(`📦 Found ${filesWithSizes.length} installer files to upload\n`);

  // Show what we're uploading
  filesWithSizes.forEach(fileInfo => {
    const sizeMB = (fileInfo.size / (1024 * 1024)).toFixed(1);
    console.log(`   📄 ${fileInfo.file} (${sizeMB}MB)`);
  });

  console.log('\n🎯 Starting uploads...\n');

  const results = [];

  for (const fileInfo of filesWithSizes) {
    console.log(`\n📁 Uploading ${fileInfo.file} to user apps folder:`);
    const result1 = await uploadSingleFile(fileInfo.path, CONFIG.paths.tetoeehoward);
    results.push(result1);

    if (result1.status === 'success') {
      console.log(`\n📁 Uploading ${fileInfo.file} to public downloads:`);
      const result2 = await uploadSingleFile(fileInfo.path, CONFIG.paths.public);
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
    console.log(`${status} ${result.fileName} - ${result.status}`);
    if (result.error) {
      console.log(`   Error: ${result.error}`);
    }
  });

  const successCount = results.filter(r => r.status === 'success').length;
  console.log(`\n🎯 Success Rate: ${successCount}/${results.length} uploads`);

  if (successCount > 0) {
    console.log('\n🔗 Download URLs:');
    console.log(`📱 User Apps: ${CONFIG.url}${CONFIG.paths.tetoeehoward}`);
    console.log(`🌐 Public Downloads: ${CONFIG.url}${CONFIG.paths.public}`);

    console.log('\n📋 Individual Download Links:');
    const successfulUploads = results.filter(r => r.status === 'success');
    successfulUploads.forEach(result => {
      console.log(`   ${CONFIG.url}${result.path}${result.fileName}`);
    });
  }

  console.log('\n🎉 FlexPBX Desktop v1.0.0 Upload Complete!');
}

if (require.main === module) {
  uploadFlexPBXInstallers().catch(console.error);
}