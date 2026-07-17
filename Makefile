.PHONY: doctor setup start reset test docker-up docker-down docker-logs

doctor:
	./scripts/doctor.sh

setup:
	./scripts/setup-macos.sh

start:
	./scripts/start-dev.sh

reset:
	./scripts/reset-sqlite.sh

test:
	./scripts/check.sh

docker-up:
	@test -f .env || cp .env.docker.example .env
	docker compose up -d --build

docker-down:
	docker compose down

docker-logs:
	docker compose logs -f api worker web
