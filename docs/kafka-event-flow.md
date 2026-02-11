# Kafka Event Flow Documentation

## Topics

| Topic | Producer | Description |
|-------|----------|-------------|
| `cart-events` | Cart Service | Events related to cart operations |
| `shipping-events` | Shipping Service | Events related to shipping/delivery |
| `payment-events` | Payment Service | Events related to payments |
| `promotion-events` | Promotion Service | Events related to promotions and codes |
| `services-events` | Services Service | Events related to additional services |
| `checkout-events` | Checkout Service | Events related to checkout orchestration |

## Consumer Groups

| Consumer Group | Service | Subscribed Topics |
|----------------|---------|-------------------|
| `checkout-consumer` | Checkout Service | cart-events, shipping-events, payment-events, promotion-events, services-events |
| `shipping-consumer` | Shipping Service | cart-events |
| `promotion-consumer` | Promotion Service | cart-events |
| `services-consumer` | Services Service | cart-events |

## Event Types

### Cart Events (`cart-events`)

| Event Name | Trigger | Data |
|------------|---------|------|
| `cart.item_added` | Item added to cart | session_id, user_id, item_id, offer_id, quantity, price, cart_total |
| `cart.item_removed` | Item removed from cart | session_id, user_id, item_id, cart_total |
| `cart.item_quantity_changed` | Item quantity updated | session_id, user_id, item_id, old_quantity, new_quantity, cart_total |
| `cart.cleared` | Cart cleared | session_id, user_id |

### Shipping Events (`shipping-events`)

| Event Name | Trigger | Data |
|------------|---------|------|
| `shipping.method_selected` | Shipping method chosen | session_id, method_id, method_name, price_amount, price_currency |
| `shipping.address_provided` | Shipping address set | session_id, street, city, postal_code, country |
| `shipping.delivery_date_selected` | Delivery date chosen | session_id, delivery_date, is_express, express_fee_amount |

### Payment Events (`payment-events`)

| Event Name | Trigger | Data |
|------------|---------|------|
| `payment.method_selected` | Payment method chosen | session_id, method_id, method_name |
| `payment.initialized` | Payment started | session_id, transaction_id, method_id, amount_total, currency |
| `payment.succeeded` | Payment successful | session_id, transaction_id, order_id |
| `payment.failed` | Payment failed | session_id, transaction_id, reason |

### Promotion Events (`promotion-events`)

| Event Name | Trigger | Data |
|------------|---------|------|
| `promotion.applied` | Promotion applied | session_id, promotion_id, promotion_name, discount_amount, discount_currency |
| `promotion.removed` | Promotion removed | session_id, promotion_id |
| `promotion.code_applied` | Promo code applied | session_id, code, discount_amount, discount_currency |

### Services Events (`services-events`)

| Event Name | Trigger | Data |
|------------|---------|------|
| `services.availability_calculated` | Services recalculated | session_id, cart_hash, available_services[], total_services_price |

### Checkout Events (`checkout-events`)

| Event Name | Trigger | Data |
|------------|---------|------|
| `checkout.totals_updated` | Any total changed | session_id, cart_total, promotion_discount, shipping_total, services_total, grand_total, currency |
| `checkout.order_created` | Order finalized | session_id, order_id, grand_total, currency |
| `checkout.completed` | Checkout completed | session_id, order_id |

## Event Flow Diagram

```
┌─────────────┐     cart-events      ┌─────────────────┐
│   Cart      │─────────────────────▶│    Checkout     │
│   Service   │                      │    Service      │
└─────────────┘                      │  (Orchestrator) │
       │                             │                 │
       │ cart-events                 │                 │
       ▼                             │                 │
┌─────────────┐     promotion-events │                 │
│  Promotion  │─────────────────────▶│                 │
│   Service   │                      │                 │
└─────────────┘                      │                 │
       ▲                             │                 │
       │ cart-events                 │                 │
       │                             │                 │
┌─────────────┐     services-events  │                 │
│  Services   │─────────────────────▶│                 │
│   Service   │                      │                 │
└─────────────┘                      │                 │
       ▲                             │                 │
       │ cart-events                 │                 │
       │                             │                 │
┌─────────────┐     shipping-events  │                 │
│  Shipping   │─────────────────────▶│                 │
│   Service   │                      │                 │
└─────────────┘                      │                 │
                                     │                 │
┌─────────────┐     payment-events   │                 │
│  Payment    │─────────────────────▶│                 │
│   Service   │                      │                 │
└─────────────┘                      └─────────────────┘
                                            │
                                            │ checkout-events
                                            ▼
                                     ┌─────────────────┐
                                     │  External       │
                                     │  Consumers      │
                                     └─────────────────┘
```

## Complete Checkout Flow

1. User adds item to cart
   - Cart Service: Publishes `cart.item_added`
   - Checkout Service: Receives event, updates cart total
   - Promotion Service: Receives event, recalculates promotions
   - Services Service: Receives event, recalculates available services

2. User selects shipping method
   - Shipping Service: Publishes `shipping.method_selected`
   - Checkout Service: Receives event, updates shipping total

3. User selects payment method
   - Payment Service: Publishes `payment.method_selected`
   - Checkout Service: Receives event, updates payment method

4. User applies promotion
   - Promotion Service: Publishes `promotion.applied`
   - Checkout Service: Receives event, updates promotion discount

5. User finalizes checkout
   - Checkout Service: Validates all data, publishes `checkout.order_created`
   - Payment Service: Initializes payment, publishes `payment.initialized`

6. User confirms payment (BLIK)
   - Payment Service: Publishes `payment.succeeded`
   - Checkout Service: Receives event, publishes `checkout.completed`, clears cart

## Correlation ID

All events include an optional `correlation_id` field for distributed tracing:

```json
{
    "event_name": "cart.item_added",
    "session_id": "sess_abc123",
    "correlation_id": "corr_xyz789",
    "occurred_at": "2026-02-11T12:00:00Z",
    ...
}
```

## Message Format

All events follow a consistent JSON structure:

```json
{
    "event_name": "string",
    "aggregate_id": "string",
    "correlation_id": "string|null",
    "occurred_at": "ISO8601 timestamp",
    "payload": {}
}
```

