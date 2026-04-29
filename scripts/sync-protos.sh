#!/bin/bash
# Sync EvitaDB proto definitions from the pinned Docker image.
#
# Run this manually after bumping the EvitaDB version in composer.json
# (extra.evitadb-version). Updates proto/*.proto in-place; review the
# diff and commit.
#
# Requirements: docker CLI (host), unzip, awk, jq.

set -e

EVITA_VERSION=$(jq -r '.extra["evitadb-version"]' composer.json)
PROTO_DIR=proto
JAR_PATH=/evita/bin/evita-server.jar

echo "Syncing protos from evitadb/evitadb:$EVITA_VERSION ..."

container=$(docker create "evitadb/evitadb:$EVITA_VERSION")
docker cp "$container:$JAR_PATH" /tmp/evita-server.jar
docker rm "$container" >/dev/null

rm -f "$PROTO_DIR"/*.proto
# Only EvitaDB's own protos (prefix `Grpc`); skip transitive deps like google/* and grpc/*
unzip -j -o /tmp/evita-server.jar 'Grpc*.proto' -d "$PROTO_DIR" >/dev/null
rm /tmp/evita-server.jar

# Apply PHP namespace patches (idempotent — skip files that already have them)
for f in "$PROTO_DIR"/*.proto; do
    grep -q "php_namespace" "$f" && continue
    awk '
        /^option csharp_namespace/ && !patched {
            print "option php_namespace = \"Wtsvk\\\\EvitaDbClient\\\\Protocol\";"
            print "option php_metadata_namespace = \"Wtsvk\\\\EvitaDbClient\\\\Protocol\\\\GPBMetadata\";"
            patched = 1
        }
        { print }
    ' "$f" > "$f.tmp" && mv "$f.tmp" "$f"
done

echo "Done. $(ls $PROTO_DIR/*.proto | wc -l | tr -d ' ') proto files synced for $EVITA_VERSION."
echo "Review: git diff $PROTO_DIR"
