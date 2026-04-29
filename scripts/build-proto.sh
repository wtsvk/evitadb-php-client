#!/bin/bash
# Generate PHP gRPC stubs from .proto files into src/Protocol/.
#
# Requires `protoc` and `grpc_php_plugin` in PATH.
# The .proto files must contain `option php_namespace = "Wtsvk\\EvitaDbClient\\Protocol";`
# (sync-protos.sh applies this patch automatically).

set -e

echo "Building protobuf files for EvitaDB..."

PROTO_PATH="proto"
OUTPUT_DIR="src"

mkdir -p "$OUTPUT_DIR"
rm -rf "$OUTPUT_DIR/Protocol"

echo "Generating PHP files from protobuf definitions..."

# EvitaDB protos import google/protobuf/* well-known types (timestamp, wrappers, etc.)
# which sync-protos.sh deliberately skips. Locate them on the system.
WELL_KNOWN_INCLUDE=""
for candidate in /opt/include /usr/include /usr/local/include /opt/homebrew/include; do
    if [ -f "$candidate/google/protobuf/timestamp.proto" ]; then
        WELL_KNOWN_INCLUDE="$candidate"
        break
    fi
done

if [ -z "$WELL_KNOWN_INCLUDE" ]; then
    echo "ERROR: Could not find google/protobuf/timestamp.proto." >&2
    echo "Install protoc with well-known types (e.g. apt install protobuf-compiler, brew install protobuf)." >&2
    exit 1
fi

echo "Using well-known types from: $WELL_KNOWN_INCLUDE"

protoc \
    --proto_path="$PROTO_PATH" \
    --proto_path="$WELL_KNOWN_INCLUDE" \
    --php_out="$OUTPUT_DIR" \
    --grpc_out="$OUTPUT_DIR" \
    --plugin=protoc-gen-grpc="$(which grpc_php_plugin)" \
    "$PROTO_PATH"/*.proto

# Rename gRPC method `Close` → `CloseSession` in EvitaSessionServiceClient
# to avoid PHP case-insensitive collision with Grpc\BaseStub::close().
SESSION_CLIENT="$OUTPUT_DIR/Wtsvk/EvitaDbClient/Protocol/EvitaSessionServiceClient.php"
if [ -f "$SESSION_CLIENT" ]; then
    sed -i.bak 's/public function Close(/public function CloseSession(/g' "$SESSION_CLIENT"
    rm -f "$SESSION_CLIENT.bak"
fi

# protoc places generated PHP under src/Wtsvk/EvitaDbClient/Protocol/ matching the
# `option php_namespace`; flatten to src/Protocol/ for our PSR-4 mapping.
if [ -d "$OUTPUT_DIR/Wtsvk/EvitaDbClient/Protocol" ]; then
    rm -rf "$OUTPUT_DIR/Protocol"
    mv "$OUTPUT_DIR/Wtsvk/EvitaDbClient/Protocol" "$OUTPUT_DIR/Protocol"
    rm -rf "$OUTPUT_DIR/Wtsvk"
fi

echo "Protobuf compilation completed! Generated files:"
find "$OUTPUT_DIR/Protocol" -name "*.php" | wc -l | tr -d ' '
