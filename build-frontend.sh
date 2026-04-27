#!/bin/bash
set -e

echo "==> Building React frontend..."

cd frontend
npm install
npm run build

echo "==> Copying React build to Symfony public..."

cd ..
cp -r frontend/dist/* public/

echo "==> Done."
echo "React build deployed into public/"