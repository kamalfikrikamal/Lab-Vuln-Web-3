#!/bin/bash
set -euo pipefail

cd /opt/klaimku

echo "=== /opt/klaimku tree before build ==="
find /opt/klaimku -maxdepth 3
echo "======================================="

# Newer docker-compose-plugin defaults to building via `docker buildx bake`,
# which has a path-resolution bug against this compose file's relative
# `dockerfile: docker/web/Dockerfile` (fails with "resolve : lstat
# .../docker/web: no such file or directory"). Force the classic build path.
export COMPOSE_BAKE=false

docker compose build
docker compose up -d

# Wait for MySQL's init scripts (schema.sql/seed.sql) to finish running on
# this first-ever start, so the db-data volume is fully seeded before we
# stop the stack again.
for i in $(seq 1 60); do
  if docker compose exec -T db mysqladmin ping -uroot -pklaimku_root_pw --silent; then
    break
  fi
  sleep 2
done

# No -v here: keep the seeded db-data volume for the shipped appliance.
docker compose down
