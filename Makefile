# =========================
# DEV
# =========================
dev:
	./scripts/build_and_export.sh dev

dev-logs:
	docker compose -f docker-compose.dev.yml logs -f

dev-down:
	docker compose -f docker-compose.dev.yml down

# =========================
# PROD
# =========================
prod:
	./scripts/build_and_export.sh