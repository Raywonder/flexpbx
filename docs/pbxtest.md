Run these commands on server...
run self check.
but before doing so check if all files are already there, of so state so and check permissions with script and start server.
cd /home/flexpbxuser/public_html

# Create required directories
mkdir -p api admin config docs scripts monitoring modules

# Move files to correct locations (if they exist)
[ -f admin-self-check.php ] && mv admin-self-check.php api/
[ -f callcentric-trunk-config.json ] && mv callcentric-trunk-config.json config/
[ -f extensions-config.json ] && mv extensions-config.json config/
[ -f google-voice-config.json ] && mv google-voice-config.json config/
[ -f EXTENSION-TESTING-GUIDE.md ] && mv EXTENSION-TESTING-GUIDE.md docs/
[ -f flexpbx-test-suite.sh ] && mv flexpbx-test-suite.sh scripts/
[ -f flexpbx-server-setup.sh ] && mv flexpbx-server-setup.sh scripts/

# Fix permissions
chmod 755 scripts/*.sh 2>/dev/null
chmod 644 config/*.json 2>/dev/null
chmod 644 docs/*.md 2>/dev/null
chmod 644 api/*.php 2>/dev/null

# Check final structure
find . -maxdepth 2 -type f | sort

# Run self-check to verify
php api/admin-self-check.php
```

## 🚀 **One-Line Command (copy/paste this):**
```bash
cd /home/flexpbxuser/public_html && mkdir -p api admin config docs scripts monitoring modules && [ -f admin-self-check.php ] && mv admin-self-check.php api/ && [ -f callcentric-trunk-config.json ] && mv callcentric-trunk-config.json config/ && [ -f extensions-config.json ] && mv extensions-config.json config/ && [ -f google-voice-config.json ] && mv google-voice-config.json config/ && [ -f EXTENSION-TESTING-GUIDE.md ] && mv EXTENSION-TESTING-GUIDE.md docs/ && [ -f flexpbx-test-suite.sh ] && mv flexpbx-test-suite.sh scripts/ && chmod 755 scripts/*.sh 2>/dev/null && chmod 644 config/*.json 2>/dev/null && chmod 644 api/*.php 2>/dev/null && echo "Files organized!" && php api/admin-self-check.php