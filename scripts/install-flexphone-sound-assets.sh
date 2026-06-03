#!/usr/bin/env bash
set -euo pipefail

# Installs bundled FlexPhone sound assets into Asterisk-friendly locations.
# Run from the flexpbx repository root on the PBX host.

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ASTERISK_SOUNDS_DIR="${ASTERISK_SOUNDS_DIR:-/var/lib/asterisk/sounds/flexpbx}"
ASTERISK_MOH_DIR="${ASTERISK_MOH_DIR:-/var/lib/asterisk/moh/flexphone-hold}"
MOH_CONF="${MOH_CONF:-/etc/asterisk/musiconhold.conf}"
ASTERISK_USER="${ASTERISK_USER:-asterisk}"
ASTERISK_GROUP="${ASTERISK_GROUP:-asterisk}"

source_ringtones="$REPO_ROOT/uploads/media/sounds/ringtones"
source_ivr="$REPO_ROOT/uploads/media/sounds/ivr"
source_moh="$REPO_ROOT/uploads/moh/flexphone-hold"

require_dir() {
  local path="$1"
  if [[ ! -d "$path" ]]; then
    echo "Missing required directory: $path" >&2
    exit 1
  fi
}

require_dir "$source_ringtones"
require_dir "$source_ivr"
require_dir "$source_moh"

if ! command -v ffmpeg >/dev/null 2>&1; then
  echo "ffmpeg is required to normalize FlexPhone audio assets for Asterisk." >&2
  exit 1
fi

install -d "$ASTERISK_SOUNDS_DIR/ringtones" "$ASTERISK_SOUNDS_DIR/ivr" "$ASTERISK_MOH_DIR"

convert_prompt() {
  local input="$1"
  local output="$2"
  ffmpeg -hide_banner -loglevel error -y -i "$input" -ac 1 -ar 8000 -sample_fmt s16 "$output"
}

convert_moh() {
  local input="$1"
  local output="$2"
  ffmpeg -hide_banner -loglevel error -y -i "$input" -ac 1 -ar 8000 -sample_fmt s16 "$output"
}

for input in "$source_ringtones"/*.wav; do
  base="$(basename "$input")"
  convert_prompt "$input" "$ASTERISK_SOUNDS_DIR/ringtones/$base"
done

for input in "$source_ivr"/*.wav; do
  base="$(basename "$input")"
  convert_prompt "$input" "$ASTERISK_SOUNDS_DIR/ivr/$base"
done

index=1
for input in "$source_moh"/*.wav; do
  printf -v base "flexphone-hold-lofi-%02d.wav" "$index"
  convert_moh "$input" "$ASTERISK_MOH_DIR/$base"
  index=$((index + 1))
done

if [[ -f "$MOH_CONF" ]] && ! grep -q '^\[flexphone-hold\]' "$MOH_CONF"; then
  cp "$MOH_CONF" "$MOH_CONF.bak.$(date +%Y%m%d%H%M%S)"
  {
    echo
    echo "; FlexPhone bundled local hold music"
    echo "[flexphone-hold]"
    echo "mode=files"
    echo "directory=$ASTERISK_MOH_DIR"
    echo "random=yes"
    echo "sort=random"
  } >> "$MOH_CONF"
fi

if id "$ASTERISK_USER" >/dev/null 2>&1; then
  chown -R "$ASTERISK_USER:$ASTERISK_GROUP" "$ASTERISK_SOUNDS_DIR/ringtones" "$ASTERISK_SOUNDS_DIR/ivr" "$ASTERISK_MOH_DIR"
fi

chmod -R u=rwX,g=rX,o=rX "$ASTERISK_SOUNDS_DIR/ringtones" "$ASTERISK_SOUNDS_DIR/ivr" "$ASTERISK_MOH_DIR"

if command -v asterisk >/dev/null 2>&1; then
  asterisk -rx "moh reload" || true
  asterisk -rx "dialplan reload" || true
fi

echo "FlexPhone sound assets installed."
echo "Ringtones: $ASTERISK_SOUNDS_DIR/ringtones"
echo "IVR sounds: $ASTERISK_SOUNDS_DIR/ivr"
echo "Hold music: $ASTERISK_MOH_DIR"
