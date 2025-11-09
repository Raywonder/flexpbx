#!/bin/bash
# FlexPBX Asterisk Dialplan Configuration Script
# Applies default dialplan with voicemail and feature codes
# Run during FlexPBX installation

set -e

echo "=== FlexPBX Asterisk Dialplan Configuration ==="
echo

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ] && [ -z "$SUDO_USER" ]; then
    echo -e "${RED}Please run as root or with sudo${NC}"
    exit 1
fi

# Paths
ASTERISK_CONF_DIR="/etc/asterisk"
DIALPLAN_CONF="${ASTERISK_CONF_DIR}/extensions.conf"
TEMPLATE_FILE="$(dirname "$0")/../config/asterisk-dialplan-defaults.conf"
BACKUP_DIR="${ASTERISK_CONF_DIR}/backups"

# Create backup directory
mkdir -p "${BACKUP_DIR}"

echo -e "${YELLOW}Step 1: Backing up existing configuration...${NC}"
if [ -f "${DIALPLAN_CONF}" ]; then
    BACKUP_FILE="${BACKUP_DIR}/extensions.conf.backup.$(date +%Y%m%d_%H%M%S)"
    cp "${DIALPLAN_CONF}" "${BACKUP_FILE}"
    echo -e "${GREEN}✓ Backup created: ${BACKUP_FILE}${NC}"
else
    echo -e "${YELLOW}⚠ No existing configuration found - creating new${NC}"
fi

echo
echo -e "${YELLOW}Step 2: Checking for template file...${NC}"
if [ ! -f "${TEMPLATE_FILE}" ]; then
    echo -e "${RED}✗ Template file not found: ${TEMPLATE_FILE}${NC}"
    echo "Creating template from defaults..."

    cat > "${TEMPLATE_FILE}" << 'TEMPLATE'
; FlexPBX Default Dialplan Configuration
[general]
static=yes
writeprotect=no
clearglobalvars=no

[globals]

[flexpbx-internal]
; Voicemail Feature Codes
exten => *97,1,NoOp(VoiceMail Access - Own Mailbox)
 same => n,Answer()
 same => n,Wait(1)
 same => n,VoiceMailMain(${CALLERID(num)}@flexpbx)
 same => n,Hangup()

exten => *98,1,NoOp(VoiceMail Access - Any Mailbox)
 same => n,Answer()
 same => n,Wait(1)
 same => n,VoiceMailMain(@flexpbx)
 same => n,Hangup()

; Testing Feature Codes
exten => *43,1,NoOp(Echo Test)
 same => n,Answer()
 same => n,Wait(1)
 same => n,Echo()
 same => n,Hangup()
TEMPLATE
fi
echo -e "${GREEN}✓ Template file ready${NC}"

echo
echo -e "${YELLOW}Step 3: Applying dialplan configuration...${NC}"

# If existing dialplan exists and has extensions, merge feature codes
if [ -f "${DIALPLAN_CONF}" ] && grep -q "flexpbx-internal" "${DIALPLAN_CONF}"; then
    echo "Existing dialplan found - adding feature codes if missing..."

    # Check if *97 already exists
    if ! grep -q "\*97" "${DIALPLAN_CONF}"; then
        echo "Adding voicemail feature codes..."

        # Add feature codes to flexpbx-internal context
        sed -i '/\[flexpbx-internal\]/a \
; Feature Codes - Voicemail\
exten => *97,1,NoOp(VoiceMail Access - Own Mailbox)\
 same => n,Answer()\
 same => n,Wait(1)\
 same => n,VoiceMailMain(${CALLERID(num)}@flexpbx)\
 same => n,Hangup()\
\
exten => *98,1,NoOp(VoiceMail Access - Any Mailbox)\
 same => n,Answer()\
 same => n,Wait(1)\
 same => n,VoiceMailMain(@flexpbx)\
 same => n,Hangup()' "${DIALPLAN_CONF}"

        echo -e "${GREEN}✓ Added *97 and *98 voicemail codes${NC}"
    else
        echo -e "${GREEN}✓ Voicemail codes already present${NC}"
    fi

    # Check if *43 already exists
    if ! grep -q "\*43" "${DIALPLAN_CONF}"; then
        echo "Adding echo test feature code..."

        sed -i '/\[flexpbx-internal\]/a \
; Feature Codes - Testing\
exten => *43,1,NoOp(Echo Test)\
 same => n,Answer()\
 same => n,Wait(1)\
 same => n,Echo()\
 same => n,Hangup()' "${DIALPLAN_CONF}"

        echo -e "${GREEN}✓ Added *43 echo test${NC}"
    else
        echo -e "${GREEN}✓ Echo test already present${NC}"
    fi
else
    # No existing dialplan or no flexpbx-internal context - use template
    echo "Creating new dialplan from template..."
    cp "${TEMPLATE_FILE}" "${DIALPLAN_CONF}"
    echo -e "${GREEN}✓ New dialplan created${NC}"
fi

echo
echo -e "${YELLOW}Step 4: Setting file permissions...${NC}"
chown asterisk:asterisk "${DIALPLAN_CONF}"
chmod 640 "${DIALPLAN_CONF}"
echo -e "${GREEN}✓ Permissions set${NC}"

echo
echo -e "${YELLOW}Step 5: Validating dialplan syntax...${NC}"
if asterisk -rx "dialplan reload" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Dialplan reloaded successfully${NC}"
else
    echo -e "${YELLOW}⚠ Could not reload (Asterisk may not be running)${NC}"
    echo "  Dialplan will be loaded when Asterisk starts"
fi

echo
echo -e "${YELLOW}Step 6: Verifying feature codes...${NC}"
if asterisk -rx "dialplan show flexpbx-internal" 2>/dev/null | grep -q "\*97"; then
    echo -e "${GREEN}✓ Feature codes are active:${NC}"
    echo "  • *97 - Access your voicemail"
    echo "  • *98 - Access any voicemail box"
    echo "  • *43 - Echo test"
else
    echo -e "${YELLOW}⚠ Feature codes will be active after Asterisk restart${NC}"
fi

echo
echo -e "${GREEN}=== Dialplan Configuration Complete ===${NC}"
echo
echo "Feature Codes Available:"
echo "  *97  - Access your own voicemail"
echo "  *98  - Access any voicemail box (enter mailbox number)"
echo "  *43  - Echo test (hear yourself back)"
echo
echo "To add extensions, edit: ${DIALPLAN_CONF}"
echo "After changes, reload with: asterisk -rx 'dialplan reload'"
echo
