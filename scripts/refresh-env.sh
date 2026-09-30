#!/bin/bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 <stg|prd>" >&2
    exit 1
fi

ENVIRONMENT="$1"

case "$ENVIRONMENT" in
    stg|prd)
        ;;
    *)
        echo "Error: environment must be 'stg' or 'prd'." >&2
        exit 1
        ;;
esac

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$APP_ROOT/.env"
TEMP_FILE="$APP_ROOT/.env.new"

DOPPLER_TOKEN_FILE="$HOME/.config/tir-lavigny.ch/$ENVIRONMENT/doppler-token"

if [[ ! -f "$DOPPLER_TOKEN_FILE" ]]; then
    echo "Error: Doppler token file not found: $DOPPLER_TOKEN_FILE" >&2
    exit 1
fi

DOPPLER_TOKEN="$(< "$DOPPLER_TOKEN_FILE")"

if [[ -z "$DOPPLER_TOKEN" ]]; then
    echo "Error: Doppler token file is empty." >&2
    exit 1
fi

umask 077

# Always remove the temporary environment file on exit.
trap 'rm -f "$TEMP_FILE"' EXIT

echo "Fetching $ENVIRONMENT environment from Doppler..."

curl \
    --fail \
    --silent \
    --show-error \
    --location \
    --header "Authorization: Bearer $DOPPLER_TOKEN" \
    "https://api.doppler.com/v3/configs/config/secrets/download?format=env" \
    > "$TEMP_FILE"

chmod 600 "$TEMP_FILE"

# Only replace the existing .env after a successful download.
mv "$TEMP_FILE" "$ENV_FILE"

echo "Environment refreshed successfully."
