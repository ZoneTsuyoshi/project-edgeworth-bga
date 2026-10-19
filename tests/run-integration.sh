#!/usr/bin/env sh
# MariaDB と PHP 8.4 の Docker コンテナを立てて、結合テスト（PHPUnit）を実行する。
# 実行: npm run test:integration（または bash tests/run-integration.sh）。Docker だけが必要。
# 追加の引数は PHPUnit に渡す（例: bash tests/run-integration.sh --filter LinkRepositoryTest）。
set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SUFFIX="$$"
NETWORK="pe-test-net-$SUFFIX"
DB="pe-test-db-$SUFFIX"
IMAGE="pe-test-php"

cleanup() {
  docker rm -f "$DB" >/dev/null 2>&1
  docker network rm "$NETWORK" >/dev/null 2>&1
}
trap cleanup EXIT INT TERM

docker build -q -t "$IMAGE" "$ROOT/tests" >/dev/null || exit 1
docker network create "$NETWORK" >/dev/null || exit 1
docker run -d --name "$DB" --network "$NETWORK" \
  -e MARIADB_ROOT_PASSWORD=test -e MARIADB_DATABASE=edgeworth_test \
  mariadb:11 >/dev/null || exit 1

echo "MariaDB の起動を待っています..."
i=0
until docker exec "$DB" mariadb -uroot -ptest -e 'SELECT 1' edgeworth_test >/dev/null 2>&1; do
  i=$((i + 1))
  if [ "$i" -gt 60 ]; then
    echo "MariaDB が起動しませんでした" >&2
    exit 1
  fi
  sleep 1
done

# --rm でコンテナは終了時に消える。vendor/ はリポジトリ直下に作られる（.gitignore 済み）
docker run --rm --network "$NETWORK" -v "$ROOT":/app -w /app \
  -e DB_HOST="$DB" -e DB_NAME=edgeworth_test -e DB_USER=root -e DB_PASSWORD=test \
  "$IMAGE" sh -c 'composer install --no-interaction --no-progress --quiet && composer test:integration -- "$@"' sh "$@"
