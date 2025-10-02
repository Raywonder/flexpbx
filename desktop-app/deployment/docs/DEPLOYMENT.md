# FlexPBX Deployment Guide

## Pre-Installation

### System Requirements
- Node.js 16+ (18+ recommended)
- Python 3.8+ (for native modules)
- Git (for version control)
- 2-8 GB RAM (depends on deployment type)
- 20-100 GB storage

### Network Requirements
- Ports: 5060-5061 (SIP), 10000-20000 (RTP)
- Stable internet connection
- Static IP recommended for production

## Installation Steps

### 1. Extract Deployment Package
```bash
tar -xzf flexpbx-deployment.tar.gz
cd flexpbx-deployment
```

### 2. Choose Deployment Type
```bash
cd deployment/[enterprise|standalone|cloud|hybrid]
```

### 3. Install Dependencies
```bash
npm install
```

### 4. Configure System
```bash
cp configs/templates/default.json configs/active.json
# Edit configs/active.json with your settings
```

### 5. Initialize Database
```bash
node scripts/init-database.js
```

### 6. Load Modules
```bash
node scripts/load-modules.js
```

### 7. Start Application
```bash
npm start
```

## Post-Installation

### Configure SIP Providers
1. Navigate to Settings > SIP Trunks
2. Add provider credentials
3. Configure inbound/outbound routing

### Setup Extensions
1. Go to Extensions > Add Extension
2. Configure extension number and settings
3. Assign to users

### Configure IVR (Optional)
1. Navigate to IVR > Menu Builder
2. Create menu structure
3. Record or upload prompts

## Verification

### Test Checklist
- [ ] Application starts without errors
- [ ] Can access web interface
- [ ] SIP registration successful
- [ ] Can make test call
- [ ] Extensions working
- [ ] Voicemail functioning

## Upgrading

### Backup Current Installation
```bash
node scripts/backup.js
```

### Apply Update
```bash
node scripts/update.js --version=latest
```

### Verify Update
```bash
node scripts/verify-update.js
```
