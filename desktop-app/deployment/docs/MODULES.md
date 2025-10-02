# FlexPBX Module Documentation

## Module System Overview
FlexPBX uses a modular architecture for flexibility and maintainability.

## Core Modules

### PBX Engine
Core PBX functionality and call routing logic.

### SIP Trunk
Manages SIP provider connections and trunk configuration.

### Extensions
Handles extension creation, management, and routing.

### Voicemail
Voicemail recording, storage, and retrieval system.

### IVR
Interactive Voice Response system with menu builder.

### Conference
Multi-party conference room management.

### Recording
Call recording with storage and retrieval.

### Analytics
Real-time and historical call analytics.

### Licensing
License validation and feature management.

## Loading Modules

### Load Single Module
```javascript
const loader = require('./modules/loader');
const pbxEngine = await loader.loadModule('pbx-engine');
```

### Load All Modules
```javascript
const modules = await loader.loadAllModules();
```

### Custom Configuration
```javascript
const module = await loader.loadModule('extensions', {
  config: {
    maxExtensions: 1000,
    startingNumber: 100
  }
});
```

## Creating Custom Modules

### Module Structure
```
custom-module/
├── module.json
├── index.js
├── src/
│   └── service.js
├── config/
│   └── default.json
└── tests/
    └── test.js
```

### Module Manifest
```json
{
  "name": "Custom Module",
  "version": "1.0.0",
  "description": "Custom functionality",
  "dependencies": [],
  "exports": ["CustomService"]
}
```

## Module API

Each module exposes:
- `initialize()`: Setup module
- `getService(name)`: Get module service
- `shutdown()`: Cleanup resources
