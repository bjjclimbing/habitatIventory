#!/bin/bash

set -e

ENV=${1:-prod}

IMAGE_NAME="inventory_app"
VERSION=$(date +"%Y%m%d_%H%M")

echo "🌍 Entorno: $ENV"

# =========================
# VALIDACIÓN
# =========================
if [[ "$ENV" != "dev" && "$ENV" != "prod" ]]; then
  echo "❌ Entorno inválido: $ENV (usa 'dev' o 'prod')"
  exit 1
fi

# =========================
# DEV
# =========================
if [ "$ENV" = "dev" ]; then
  echo "🚀 Levantando entorno DEV..."

  docker compose -f docker-compose.dev.yml up --build -d

  echo "✅ DEV listo en http://localhost:8480"
  exit 0
fi

# =========================
# PROD
# =========================
echo "🧹 Limpiando build anterior..."
rm -rf public/build

# =========================
# FRONTEND BUILD
# =========================
echo "📦 Build frontend..."

cd frontend
npm install
npm run build
cd ..

echo "📁 Moviendo build a Symfony..."
mkdir -p public/build
cp -r frontend/dist/* public/build/

# =========================
# DOCKER BUILD
# =========================
echo "🐳 Build Docker..."

docker build -f docker/php/Dockerfile \
  --build-arg APACHE_ENV=prod \
  --build-arg APP_ENV=prod \
  -t ${IMAGE_NAME}:${VERSION} \
  -t ${IMAGE_NAME}:latest \
  .

# =========================
# EXPORT
# =========================
echo "📤 Exportando imagen..."

OUTPUT_FILE="${IMAGE_NAME}_${VERSION}.tar.gz"

docker save ${IMAGE_NAME}:${VERSION} | gzip > $OUTPUT_FILE

echo "✅ DONE → $OUTPUT_FILE"