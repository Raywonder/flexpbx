# FlexPBX Troubleshooting Guide

## Common Issues

### Application Won't Start

#### Check Node.js Version
```bash
node --version  # Should be 16+
```

#### Verify Dependencies
```bash
npm install
npm audit fix
```

#### Check Logs
```bash
tail -f logs/flexpbx.log
```

### SIP Registration Fails

#### Verify Credentials
- Check username/password in configs/active.json
- Ensure account is active with provider

#### Check Network
```bash
# Test SIP connectivity
nc -zv sip.provider.com 5060
```

#### Firewall Issues
- Open ports 5060-5061 for SIP
- Open ports 10000-20000 for RTP

### No Audio in Calls

#### Check NAT Settings
- Configure STUN servers
- Set public IP if behind NAT

#### Verify Codecs
- Ensure compatible codecs with provider
- G.711 is most compatible

### Database Errors

#### SQLite Locked
```bash
# Stop application
# Remove lock file
rm data/flexpbx.db-journal
```

#### PostgreSQL Connection
- Verify connection string
- Check database server status
- Ensure user permissions

## Diagnostic Commands

### Check System Status
```bash
node scripts/health-check.js
```

### Test SIP Registration
```bash
node scripts/test-sip.js
```

### Verify Modules
```bash
node scripts/verify-modules.js
```

### Database Integrity
```bash
node scripts/check-database.js
```

## Log Locations

- Application: `logs/flexpbx.log`
- SIP: `logs/sip.log`
- Errors: `logs/error.log`
- Access: `logs/access.log`

## Getting Help

### Collect Debug Info
```bash
node scripts/collect-debug-info.js
```

### Contact Support
- Email: support@flexpbx.com
- Include debug info file
- Describe issue and steps to reproduce

## Recovery Procedures

### Restore from Backup
```bash
node scripts/restore.js --backup=backup-2024-01-01.tar.gz
```

### Reset to Defaults
```bash
node scripts/reset.js --confirm
```

### Rebuild Database
```bash
node scripts/rebuild-database.js
```
