const dns = require('dns').promises;
const { exec } = require('child_process');
const os = require('os');

class DNSService {
    constructor() {
        this.providers = {
            cloudflare: {
                name: 'Cloudflare',
                apiUrl: 'https://api.cloudflare.com/client/v4',
                requiresAuth: true
            },
            digitalocean: {
                name: 'DigitalOcean',
                apiUrl: 'https://api.digitalocean.com/v2',
                requiresAuth: true
            },
            namecheap: {
                name: 'Namecheap',
                apiUrl: 'https://api.namecheap.com',
                requiresAuth: true
            },
            godaddy: {
                name: 'GoDaddy',
                apiUrl: 'https://api.godaddy.com/v1',
                requiresAuth: true
            }
        };
    }

    async createARecord(config) {
        const { provider, domain, subdomain, ipAddress, ttl = 3600, credentials } = config;

        if (!this.providers[provider]) {
            return {
                success: false,
                error: `Unsupported DNS provider: ${provider}`
            };
        }

        try {
            let result;

            switch (provider) {
                case 'cloudflare':
                    result = await this.createCloudflareRecord(domain, subdomain, ipAddress, ttl, credentials);
                    break;
                case 'digitalocean':
                    result = await this.createDigitalOceanRecord(domain, subdomain, ipAddress, ttl, credentials);
                    break;
                case 'namecheap':
                    result = await this.createNamecheapRecord(domain, subdomain, ipAddress, ttl, credentials);
                    break;
                case 'godaddy':
                    result = await this.createGoDaddyRecord(domain, subdomain, ipAddress, ttl, credentials);
                    break;
                default:
                    return {
                        success: false,
                        error: `Provider ${provider} not implemented yet`
                    };
            }

            if (result.success) {
                // Verify the record was created
                const verification = await this.verifyRecord(subdomain ? `${subdomain}.${domain}` : domain, ipAddress);
                return {
                    ...result,
                    verified: verification.success,
                    verificationNote: verification.success
                        ? 'DNS record created and verified successfully'
                        : 'DNS record created but not yet propagated (may take up to 48 hours)'
                };
            }

            return result;

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async createCloudflareRecord(domain, subdomain, ipAddress, ttl, credentials) {
        const { apiToken } = credentials;

        try {
            // First, get the zone ID for the domain
            const zoneResponse = await fetch(`https://api.cloudflare.com/client/v4/zones?name=${domain}`, {
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json'
                }
            });

            const zoneData = await zoneResponse.json();

            if (!zoneData.success || zoneData.result.length === 0) {
                return {
                    success: false,
                    error: 'Domain not found in Cloudflare account'
                };
            }

            const zoneId = zoneData.result[0].id;

            // Create the A record
            const recordResponse = await fetch(`https://api.cloudflare.com/client/v4/zones/${zoneId}/dns_records`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    type: 'A',
                    name: subdomain ? `${subdomain}.${domain}` : domain,
                    content: ipAddress,
                    ttl: ttl
                })
            });

            const recordData = await recordResponse.json();

            if (recordData.success) {
                return {
                    success: true,
                    recordId: recordData.result.id,
                    message: 'A record created successfully in Cloudflare'
                };
            } else {
                return {
                    success: false,
                    error: recordData.errors.map(e => e.message).join(', ')
                };
            }

        } catch (error) {
            return {
                success: false,
                error: `Cloudflare API error: ${error.message}`
            };
        }
    }

    async createDigitalOceanRecord(domain, subdomain, ipAddress, ttl, credentials) {
        const { apiToken } = credentials;

        try {
            const recordResponse = await fetch(`https://api.digitalocean.com/v2/domains/${domain}/records`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    type: 'A',
                    name: subdomain || '@',
                    data: ipAddress,
                    ttl: ttl
                })
            });

            if (!recordResponse.ok) {
                const errorData = await recordResponse.json();
                return {
                    success: false,
                    error: errorData.message || 'Failed to create record'
                };
            }

            const recordData = await recordResponse.json();

            return {
                success: true,
                recordId: recordData.domain_record.id,
                message: 'A record created successfully in DigitalOcean'
            };

        } catch (error) {
            return {
                success: false,
                error: `DigitalOcean API error: ${error.message}`
            };
        }
    }

    async createNamecheapRecord(domain, subdomain, ipAddress, ttl, credentials) {
        const { apiUser, apiKey, clientIp } = credentials;

        try {
            // Namecheap uses XML API with different structure
            const params = new URLSearchParams({
                ApiUser: apiUser,
                ApiKey: apiKey,
                UserName: apiUser,
                Command: 'namecheap.domains.dns.setHosts',
                ClientIp: clientIp,
                SLD: domain.split('.')[0],
                TLD: domain.split('.')[1],
                HostName1: subdomain || '@',
                RecordType1: 'A',
                Address1: ipAddress,
                TTL1: ttl
            });

            const response = await fetch(`https://api.namecheap.com/xml.response?${params}`);
            const xmlText = await response.text();

            // Parse XML response (simplified)
            if (xmlText.includes('Status="OK"')) {
                return {
                    success: true,
                    message: 'A record created successfully in Namecheap'
                };
            } else {
                return {
                    success: false,
                    error: 'Failed to create record in Namecheap'
                };
            }

        } catch (error) {
            return {
                success: false,
                error: `Namecheap API error: ${error.message}`
            };
        }
    }

    async createGoDaddyRecord(domain, subdomain, ipAddress, ttl, credentials) {
        const { apiKey, apiSecret } = credentials;

        try {
            const recordResponse = await fetch(`https://api.godaddy.com/v1/domains/${domain}/records/A/${subdomain || '@'}`, {
                method: 'PUT',
                headers: {
                    'Authorization': `sso-key ${apiKey}:${apiSecret}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify([{
                    data: ipAddress,
                    ttl: ttl
                }])
            });

            if (recordResponse.ok) {
                return {
                    success: true,
                    message: 'A record created successfully in GoDaddy'
                };
            } else {
                const errorData = await recordResponse.json();
                return {
                    success: false,
                    error: errorData.message || 'Failed to create record'
                };
            }

        } catch (error) {
            return {
                success: false,
                error: `GoDaddy API error: ${error.message}`
            };
        }
    }

    async verifyRecord(hostname, expectedIp) {
        try {
            const addresses = await dns.resolve4(hostname);
            const recordExists = addresses.includes(expectedIp);

            return {
                success: recordExists,
                resolvedIps: addresses,
                expectedIp,
                message: recordExists
                    ? 'DNS record verified successfully'
                    : 'DNS record not yet propagated'
            };

        } catch (error) {
            return {
                success: false,
                error: `DNS verification failed: ${error.message}`,
                resolvedIps: [],
                expectedIp
            };
        }
    }

    async getCurrentPublicIP() {
        try {
            // Try multiple services for redundancy
            const services = [
                'https://api.ipify.org?format=json',
                'https://httpbin.org/ip',
                'https://api.myip.com'
            ];

            for (const service of services) {
                try {
                    const response = await fetch(service);
                    const data = await response.json();

                    // Different services return IP in different formats
                    if (data.ip) return data.ip;
                    if (data.origin) return data.origin;
                    if (data.ipAddress) return data.ipAddress;
                } catch (error) {
                    continue; // Try next service
                }
            }

            throw new Error('Could not determine public IP address');

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async getLocalNetworkInfo() {
        try {
            const interfaces = os.networkInterfaces();
            const networkInfo = [];

            for (const [name, addresses] of Object.entries(interfaces)) {
                for (const addr of addresses) {
                    if (addr.family === 'IPv4' && !addr.internal) {
                        networkInfo.push({
                            interface: name,
                            address: addr.address,
                            netmask: addr.netmask,
                            mac: addr.mac
                        });
                    }
                }
            }

            return {
                success: true,
                interfaces: networkInfo
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async testDNSResolution(hostname) {
        try {
            const results = {};

            // Test A record
            try {
                results.a = await dns.resolve4(hostname);
            } catch (error) {
                results.a = { error: error.message };
            }

            // Test AAAA record (IPv6)
            try {
                results.aaaa = await dns.resolve6(hostname);
            } catch (error) {
                results.aaaa = { error: error.message };
            }

            // Test CNAME record
            try {
                results.cname = await dns.resolveCname(hostname);
            } catch (error) {
                results.cname = { error: error.message };
            }

            // Test MX record
            try {
                results.mx = await dns.resolveMx(hostname);
            } catch (error) {
                results.mx = { error: error.message };
            }

            return {
                success: true,
                hostname,
                records: results
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async detectCloudflareProxy(hostname) {
        try {
            // Cloudflare proxy detection by checking if IP is in Cloudflare ranges
            const addresses = await dns.resolve4(hostname);

            // These are some common Cloudflare IP ranges (not exhaustive)
            const cloudflareRanges = [
                '103.21.244.0/22',
                '103.22.200.0/22',
                '103.31.4.0/22',
                '104.16.0.0/12',
                '108.162.192.0/18',
                '131.0.72.0/22',
                '141.101.64.0/18',
                '162.158.0.0/15',
                '172.64.0.0/13',
                '173.245.48.0/20',
                '188.114.96.0/20',
                '190.93.240.0/20',
                '197.234.240.0/22',
                '198.41.128.0/17'
            ];

            // Simple check - in production would use proper CIDR matching
            const isCloudflareProxy = addresses.some(ip => {
                return ip.startsWith('104.16.') || ip.startsWith('172.64.') || ip.startsWith('162.158.');
            });

            return {
                success: true,
                hostname,
                addresses,
                isCloudflareProxy,
                message: isCloudflareProxy ? 'Domain appears to be proxied through Cloudflare' : 'Domain does not appear to be proxied'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    getSupportedProviders() {
        return Object.entries(this.providers).map(([key, provider]) => ({
            id: key,
            name: provider.name,
            requiresAuth: provider.requiresAuth
        }));
    }

    getProviderCredentialsSchema(provider) {
        const schemas = {
            cloudflare: {
                fields: [
                    { name: 'apiToken', label: 'API Token', type: 'password', required: true }
                ],
                instructions: 'Get your API token from Cloudflare Dashboard > My Profile > API Tokens'
            },
            digitalocean: {
                fields: [
                    { name: 'apiToken', label: 'API Token', type: 'password', required: true }
                ],
                instructions: 'Generate a personal access token in DigitalOcean Control Panel > API'
            },
            namecheap: {
                fields: [
                    { name: 'apiUser', label: 'API User', type: 'text', required: true },
                    { name: 'apiKey', label: 'API Key', type: 'password', required: true },
                    { name: 'clientIp', label: 'Client IP', type: 'text', required: true }
                ],
                instructions: 'Enable API access in Namecheap Account > Profile > Tools > Namecheap API Access'
            },
            godaddy: {
                fields: [
                    { name: 'apiKey', label: 'API Key', type: 'password', required: true },
                    { name: 'apiSecret', label: 'API Secret', type: 'password', required: true }
                ],
                instructions: 'Create API keys in GoDaddy Developer Portal'
            }
        };

        return schemas[provider] || null;
    }
}

module.exports = DNSService;