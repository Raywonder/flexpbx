# FlexPBX Extension & Trunk Credentials

## 📞 SIP Extensions

**Server:** flexpbx.devinecreations.net:5060
**Transport:** UDP
**Context:** flexpbx-internal

| Extension | Display Name | Password | Email |
|-----------|--------------|----------|-------|
| 2000 | Admin Extension | FlexPBX2000! | admin@flexpbx.devinecreations.net |
| 2001 | Test User | FlexPBX2001! | test@flexpbx.devinecreations.net |
| 2002 | Demo Extension | FlexPBX2002! | demo@flexpbx.devinecreations.net |
| 2003 | Support Extension | FlexPBX2003! | support@flexpbx.devinecreations.net |

## 🌐 SIP Trunks

### CallCentric Trunk
- **Provider:** CallCentric
- **Server:** sip.callcentric.com:5060
- **Username:** 17778171572
- **Password:** 860719938242
- **DID:** (302) 313-9555 (3023139555)
- **Max Channels:** 2
- **Transport:** UDP

## 📱 Google Voice

### Google Voice Number
- **Number:** (281) 301-5784 (2813015784)
- **Service:** Already configured in `/api/services/GoogleVoiceService.js`
- **Test Cell:** (336) 462-6141

## 📱 Softphone Configuration Example

### For Extension 2001:
```
Username: 2001
Password: FlexPBX2001!
Domain: flexpbx.devinecreations.net
Port: 5060
Transport: UDP
```

### Recommended Softphones:
- **Desktop:** Zoiper, Linphone, X-Lite
- **Mobile:** Zoiper, Linphone
- **Web:** JsSIP

## 🔐 Security Notes

- All passwords use strong complexity
- Database realtime configuration enabled
- AMI access restricted to localhost only
- PJSIP transports configured on port 5060

---
**Last Updated:** October 13, 2025
