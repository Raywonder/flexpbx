# Security Guidelines

## Overview

This FlexPBX VoIP MCP server implements security best practices learned from analyzing production VoIP systems.

## Security Improvements Over Source Material

### 1. Credential Management

**Problem in Source**: Hardcoded credentials in PHP files
```php
// DON'T DO THIS (from source)
private $username = 'flexpbx_web';
private $secret = 'FlexPBX_Web_2024!';
```

**Solution**: Environment-based configuration
```javascript
// DO THIS
const config = {
  username: process.env.AMI_USERNAME,
  secret: process.env.AMI_SECRET
};
```

### 2. Input Validation

**Problem**: Direct shell command execution without sanitization
```php
// DON'T DO THIS
function executeAsteriskCommand($command) {
    $output = shell_exec("sudo asterisk -rx \"$command\" 2>&1");
    return $output;
}
```

**Solution**: AMI-based commands with validation
```javascript
// DO THIS
async command(cmd) {
  // Validate command before sending
  if (!this.isValidCommand(cmd)) {
    throw new Error('Invalid command');
  }
  return await this.sendAction({
    Action: 'Command',
    Command: cmd
  });
}
```

### 3. Connection Timeouts

**Problem**: No timeout protection
```php
$this->socket = @fsockopen($this->host, $this->port, $errno, $errstr);
```

**Solution**: Configurable timeouts with cleanup
```javascript
return new Promise((resolve, reject) => {
  const timeoutId = setTimeout(() => {
    reject(new Error(`Connection timeout after ${this.timeout}ms`));
  }, this.timeout);
  // ... cleanup on completion
});
```

### 4. Error Handling

**Problem**: Silent failures with @ operator
```php
$this->socket = @fsockopen($this->host, $this->port, $errno, $errstr);
```

**Solution**: Explicit error handling and logging
```javascript
this.socket.on('error', (error) => {
  this.emit('error', error);
  console.error('[AMI Error]', error);
  reject(error);
});
```

## Security Best Practices

### Environment Variables

Always use environment variables for sensitive data:

```bash
# .env file (never commit this!)
AMI_USERNAME=your_username
AMI_SECRET=your_strong_password_here
DB_PASSWORD=your_db_password_here
API_KEY=random_api_key_for_rate_limiting
```

Add to `.gitignore`:
```
.env
*.log
node_modules/
```

### AMI User Permissions

Create a dedicated AMI user with minimal permissions:

```ini
; /etc/asterisk/manager.conf
[flexpbx_mcp]
secret=StrongRandomPasswordHere123!
deny=0.0.0.0/0.0.0.0
permit=127.0.0.1/255.255.255.255
read=system,call,log,verbose,command,agent,user,config
write=system,call,originate,command
```

**Never** use:
- `write=all`
- `permit=0.0.0.0/0.0.0.0`
- Default passwords

### Database Security

For CDR queries:

1. **Read-only database user**:
```sql
CREATE USER 'cdr_readonly'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT ON asteriskcdrdb.cdr TO 'cdr_readonly'@'localhost';
FLUSH PRIVILEGES;
```

2. **Never use root database credentials**

3. **Use parameterized queries**:
```javascript
// DO THIS
const query = 'SELECT * FROM cdr WHERE src = ?';
const params = [extension];

// DON'T DO THIS
const query = `SELECT * FROM cdr WHERE src = '${extension}'`;
```

### Network Security

1. **Firewall Rules**:
```bash
# Only allow AMI from localhost
iptables -A INPUT -p tcp --dport 5038 -s 127.0.0.1 -j ACCEPT
iptables -A INPUT -p tcp --dport 5038 -j DROP
```

2. **TLS/SSL** for remote connections:
```ini
; /etc/asterisk/manager.conf
[general]
enabled = yes
webenabled = no
port = 5038
bindaddr = 127.0.0.1
tlsenable=yes
tlsbindaddr=0.0.0.0:5039
tlscertfile=/etc/asterisk/keys/asterisk.crt
tlsprivatekey=/etc/asterisk/keys/asterisk.key
```

### Rate Limiting

Implement rate limiting to prevent abuse:

```javascript
class RateLimiter {
  constructor(maxRequests = 60, windowMs = 60000) {
    this.maxRequests = maxRequests;
    this.windowMs = windowMs;
    this.requests = new Map();
  }

  check(identifier) {
    const now = Date.now();
    const userRequests = this.requests.get(identifier) || [];

    // Remove old requests
    const validRequests = userRequests.filter(
      time => now - time < this.windowMs
    );

    if (validRequests.length >= this.maxRequests) {
      throw new Error('Rate limit exceeded');
    }

    validRequests.push(now);
    this.requests.set(identifier, validRequests);
  }
}
```

### Logging and Auditing

1. **Log all actions**:
```javascript
const logger = {
  info: (msg, data) => console.log(`[INFO] ${msg}`, data),
  warn: (msg, data) => console.warn(`[WARN] ${msg}`, data),
  error: (msg, data) => console.error(`[ERROR] ${msg}`, data),
  audit: (action, user, data) => {
    console.log(`[AUDIT] ${action} by ${user}`, data);
  }
};
```

2. **Rotate logs**:
```bash
# /etc/logrotate.d/flexpbx-voip-mcp
/var/log/flexpbx-voip-mcp.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 flexpbx flexpbx
    sharedscripts
}
```

### Secure Deployment

1. **Run as non-root user**:
```bash
# Create dedicated user
sudo useradd -r -s /bin/false flexpbx-mcp

# Set permissions
sudo chown -R flexpbx-mcp:flexpbx-mcp /home/devinecr/apps/mcp-servers/flexpbx-voip-mcp

# Run as user
sudo -u flexpbx-mcp node src/index.js
```

2. **Use systemd service**:
```ini
[Unit]
Description=FlexPBX VoIP MCP Server
After=network.target asterisk.service

[Service]
Type=simple
User=flexpbx-mcp
Group=flexpbx-mcp
WorkingDirectory=/home/devinecr/apps/mcp-servers/flexpbx-voip-mcp
EnvironmentFile=/home/devinecr/apps/mcp-servers/flexpbx-voip-mcp/.env
ExecStart=/usr/bin/node src/index.js
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

## Vulnerability Prevention

### SQL Injection

**Bad**:
```javascript
const query = `SELECT * FROM cdr WHERE src = '${src}'`;
```

**Good**:
```javascript
const query = 'SELECT * FROM cdr WHERE src = ?';
const params = [src];
```

### Command Injection

**Bad**:
```javascript
exec(`asterisk -rx "${command}"`);
```

**Good**:
```javascript
// Use AMI instead of shell commands
await ami.command(sanitizedCommand);
```

### Path Traversal

**Bad**:
```javascript
const file = fs.readFileSync(userInput);
```

**Good**:
```javascript
const safeFile = path.basename(userInput);
if (!/^[a-zA-Z0-9_-]+$/.test(safeFile)) {
  throw new Error('Invalid filename');
}
```

## Security Checklist

- [ ] No hardcoded credentials
- [ ] Environment variables for all secrets
- [ ] Input validation on all user inputs
- [ ] Parameterized database queries
- [ ] Rate limiting enabled
- [ ] Timeout protection on network operations
- [ ] Error handling with safe messages
- [ ] Logging and auditing enabled
- [ ] Running as non-root user
- [ ] Firewall rules configured
- [ ] TLS enabled for remote connections
- [ ] Regular security updates
- [ ] Log rotation configured
- [ ] File permissions restricted

## Incident Response

If you detect a security issue:

1. **Isolate**: Stop the service immediately
2. **Investigate**: Check logs for suspicious activity
3. **Patch**: Update credentials, fix vulnerabilities
4. **Monitor**: Enable enhanced logging
5. **Report**: Document the incident

## Regular Maintenance

- **Weekly**: Review access logs
- **Monthly**: Update dependencies (`npm audit`)
- **Quarterly**: Rotate credentials
- **Annually**: Security audit

## Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Node.js Security Best Practices](https://nodejs.org/en/docs/guides/security/)
- [Asterisk Security Framework](https://wiki.asterisk.org/wiki/display/AST/Asterisk+Security+Framework)

## Contact

For security concerns, contact: security@devinecreations.com
