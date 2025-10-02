# FlexPBX Configuration Guide

## Configuration Files

### Main Configuration
`configs/active.json` - Active configuration file

### Templates
- `default.json` - Default settings
- `enterprise.json` - Enterprise settings
- `cloud.json` - Cloud deployment settings

## Configuration Sections

### PBX Settings
```json
{
  "pbx": {
    "maxExtensions": 100,
    "maxConcurrentCalls": 50,
    "recordingEnabled": true,
    "voicemailEnabled": true
  }
}
```

### SIP Configuration
```json
{
  "sip": {
    "providers": ["flexpbx", "callcentric"],
    "codecs": ["G.711", "Opus"],
    "transport": ["UDP", "TLS"]
  }
}
```

### Security Settings
```json
{
  "security": {
    "encryption": "required",
    "firewall": true,
    "rateLimiting": true,
    "maxFailedAttempts": 5
  }
}
```

## Environment Variables

### Required
- `FLEXPBX_LICENSE_KEY`: License key
- `FLEXPBX_DB_URL`: Database connection string

### Optional
- `FLEXPBX_PORT`: Web interface port (default: 3000)
- `FLEXPBX_LOG_LEVEL`: Logging level (debug|info|warn|error)
- `FLEXPBX_CLUSTER_MODE`: Enable clustering (true|false)

## Provider Configuration

### FlexPBX
```json
{
  "provider": "flexpbx",
  "username": "your-username",
  "password": "your-password",
  "server": "pbx.flexpbx.com"
}
```

### CallCentric
```json
{
  "provider": "callcentric",
  "sipUsername": "1777XXXXXXX",
  "sipPassword": "password",
  "server": "callcentric.com"
}
```

## Advanced Configuration

### High Availability
```json
{
  "ha": {
    "enabled": true,
    "mode": "active-passive",
    "heartbeatInterval": 5000,
    "failoverThreshold": 3
  }
}
```

### Clustering
```json
{
  "cluster": {
    "enabled": true,
    "nodes": ["node1.example.com", "node2.example.com"],
    "loadBalancing": "round-robin"
  }
}
```
