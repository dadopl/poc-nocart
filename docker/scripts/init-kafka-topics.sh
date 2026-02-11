#!/bin/bash

echo "Creating Kafka topics..."

KAFKA_BROKER=${KAFKA_BROKER:-redpanda:9092}

topics=(
    "cart-events"
    "shipping-events"
    "payment-events"
    "promotion-events"
    "services-events"
    "checkout-events"
)

for topic in "${topics[@]}"; do
    echo "Creating topic: $topic"
    rpk topic create "$topic" \
        --brokers "$KAFKA_BROKER" \
        --partitions 3 \
        --replicas 1 \
        2>/dev/null || echo "Topic $topic already exists"
done

echo "Listing topics:"
rpk topic list --brokers "$KAFKA_BROKER"

echo "Kafka topics initialization complete!"

