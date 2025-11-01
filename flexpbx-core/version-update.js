#!/usr/bin/env node

// FlexPBX Version Update Script
// Updates all version references from 1.0.0 to 1.0.0 (official release)

const fs = require('fs').promises;
const path = require('path');

const VERSION_MAPPINGS = {
    '1.0.0': '1.0.0',
    'v1.0.0': 'v1.0.0',
    'Version: 1.0.0': 'Version: 1.0.0',
    '"version": "1.0.0"': '"version": "1.0.0"',
    'FlexPBX-Desktop-Mac/1.0.0': 'FlexPBX-Desktop-Mac/1.0.0',
    'FlexPBX-Desktop-Windows/1.0.0': 'FlexPBX-Desktop-Windows/1.0.0',
    'FlexPBX Desktop v1.0.0': 'FlexPBX Desktop v1.0.0',
    'FlexPhone v1.0.0': 'FlexPhone v1.0.0'
};

const FILE_PATTERNS = [
    '**/*.js',
    '**/*.json',
    '**/*.html',
    '**/*.md',
    '**/*.cs',
    '**/*.php'
];

async function updateVersionInFile(filePath) {
    try {
        let content = await fs.readFile(filePath, 'utf8');
        let updated = false;

        for (const [oldVersion, newVersion] of Object.entries(VERSION_MAPPINGS)) {
            if (content.includes(oldVersion)) {
                content = content.replace(new RegExp(oldVersion.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), newVersion);
                updated = true;
            }
        }

        if (updated) {
            await fs.writeFile(filePath, content, 'utf8');
            console.log(`✅ Updated: ${filePath}`);
            return true;
        }

        return false;
    } catch (error) {
        console.error(`❌ Error updating ${filePath}:`, error.message);
        return false;
    }
}

async function findFiles(dir, patterns) {
    const files = [];

    async function scan(currentDir) {
        const items = await fs.readdir(currentDir);

        for (const item of items) {
            const fullPath = path.join(currentDir, item);
            const stat = await fs.stat(fullPath);

            if (stat.isDirectory()) {
                // Skip node_modules, .git, and other common directories
                if (!['node_modules', '.git', 'dist', 'build', 'logs', 'backup'].includes(item)) {
                    await scan(fullPath);
                }
            } else if (stat.isFile()) {
                const ext = path.extname(item);
                if (['.js', '.json', '.html', '.md', '.cs', '.php'].includes(ext)) {
                    files.push(fullPath);
                }
            }
        }
    }

    await scan(dir);
    return files;
}

async function main() {
    console.log('🔄 FlexPBX Version Update: 1.0.0 → 1.0.0 (Official Release)');
    console.log('========================================');

    const rootDir = process.cwd();
    console.log(`📁 Scanning directory: ${rootDir}`);

    const files = await findFiles(rootDir, FILE_PATTERNS);
    console.log(`📄 Found ${files.length} files to check`);

    let updatedCount = 0;

    for (const file of files) {
        const wasUpdated = await updateVersionInFile(file);
        if (wasUpdated) {
            updatedCount++;
        }
    }

    console.log('========================================');
    console.log(`✅ Version update complete!`);
    console.log(`📊 Updated ${updatedCount} files`);
    console.log(`🎉 FlexPBX v1.0.0 is now the official release version`);
}

if (require.main === module) {
    main().catch(console.error);
}

module.exports = { updateVersionInFile, findFiles };