#!/bin/sh
# =============================================================================
# init-ssl.sh - Premier lancement : obtient le certificat Let's Encrypt
#
# À exécuter UNE SEULE FOIS sur le serveur avant de démarrer la stack prod.
#
# Pré-requis :
#   - Le DNS du domaine pointe déjà sur ce serveur (A record)
#   - Les ports 80 et 443 sont ouverts dans le pare-feu
#   - .env.prod est créé (APP_KEY générée)
#   - docker et docker compose sont installés
#
# Usage :
#   chmod +x docker/ssl/init-ssl.sh
#   ./docker/ssl/init-ssl.sh your@email.com
# =============================================================================

set -e

EMAIL="${1:-}"
DOMAIN="tir-lavigny.ch"
COMPOSE_FILE="docker-compose.prod.yml"

if [ -z "$EMAIL" ]; then
  echo "Usage: $0 <email>"
  exit 1
fi

echo "==> 1/6 Téléchargement des paramètres SSL recommandés dans le volume certbot..."
docker compose -f $COMPOSE_FILE run --rm --entrypoint "sh -c '
  curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf \
       -o /etc/letsencrypt/options-ssl-nginx.conf &&
  curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot/certbot/ssl-dhparams.pem \
       -o /etc/letsencrypt/ssl-dhparams.pem
'" certbot

echo "==> 2/6 Création d'un certificat auto-signé temporaire pour démarrer Nginx..."
docker compose -f $COMPOSE_FILE run --rm --entrypoint "sh -c '
  mkdir -p /etc/letsencrypt/live/$DOMAIN &&
  openssl req -x509 -nodes -newkey rsa:4096 -days 1 \
    -keyout /etc/letsencrypt/live/$DOMAIN/privkey.pem \
    -out    /etc/letsencrypt/live/$DOMAIN/fullchain.pem \
    -subj /CN=localhost 2>/dev/null
'" certbot

echo "==> 3/6 Démarrage de Nginx (avec le cert temporaire)..."
docker compose -f $COMPOSE_FILE up -d nginx

echo "==> 4/6 Suppression du certificat temporaire..."
docker compose -f $COMPOSE_FILE run --rm --entrypoint "sh -c '
  rm -rf /etc/letsencrypt/live/$DOMAIN
'" certbot

echo "==> 5/6 Obtention du certificat Let's Encrypt réel..."
docker compose -f $COMPOSE_FILE run --rm certbot certonly \
  --webroot \
  --webroot-path=/var/www/certbot \
  --email "$EMAIL" \
  --agree-tos \
  --no-eff-email \
  -d "$DOMAIN" \
  -d "www.$DOMAIN"

echo "==> 6/6 Rechargement de Nginx avec le vrai certificat..."
docker compose -f $COMPOSE_FILE exec nginx nginx -s reload

echo ""
echo "Certificat obtenu avec succès !"
echo "Lance maintenant la stack complète :"
echo ""
echo "  docker compose -f docker-compose.prod.yml up -d"

