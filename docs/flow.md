# Kompletny Flow z Promocją i Usługami

---

## 1. Dodanie laptopa do koszyka

**User:** Klika "Dodaj do koszyka" (Laptop Dell XPS, 5999 PLN)

**Cart Service:**
- POST /cart/items {offer_id: 123, type: "product", quantity: 1}
- Response 202 Accepted (20ms)
- Async enrichment
- Update cart:view:{user_id}

**Kafka:**
- Publish: CartItemAdded → cart-events

**Konsumenci:**
- Shipping Service: invalidate cache
- Payment Service: invalidate cache
- Promotion Service: przelicz dostępne promocje
- Services Service: sprawdź dostępne usługi
- Checkout Service: update totals

---

**SimpleCart (widget na stronie):**
```
┌────────────────────────────┐
│ KOSZYK (1)                 │
├────────────────────────────┤
│ Laptop Dell XPS      5999  │
│                            │
│ Razem:              5999   │
└────────────────────────────┘
```

---

## 2. Dodanie gwarancji do koszyka

**User:** Wyświetla gwarancje (zewnętrzny system), wybiera "36 miesięcy" (299 PLN)

**Cart Service:**
- POST /cart/items {offer_id: 456, type: "warranty", parent_item_id: "laptop-uuid", quantity: 1}
- Response 202 Accepted
- Update cart:view

**Kafka:**
- Publish: CartItemAdded (type=warranty)

**Konsumenci:**
- Checkout Service: update totals

---

**SimpleCart:**
```
┌────────────────────────────┐
│ KOSZYK (2)                 │
├────────────────────────────┤
│ Laptop Dell XPS      5999  │
│  └ Gwarancja 36m      299  │
│                            │
│ Razem:              6298   │
└────────────────────────────┘
```

---

## 3. Dodanie akcesoriów

**User:** Wyświetla akcesoria (zewnętrzny system), dodaje torbę (149 PLN)

**Cart Service:**
- POST /cart/items {offer_id: 789, type: "accessory", parent_item_id: "laptop-uuid", quantity: 1}
- Response 202 Accepted
- Update cart:view

**Kafka:**
- Publish: CartItemAdded (type=accessory)

**Konsumenci:**
- Promotion Service: przelicz promocje (może być promocja na akcesoria)
- Checkout Service: update totals

---

**SimpleCart:**
```
┌────────────────────────────┐
│ KOSZYK (3)                 │
├────────────────────────────┤
│ Laptop Dell XPS      5999  │
│  └ Gwarancja 36m      299  │
│  └ Torba na laptop    149  │
│                            │
│ Razem:              6447   │
└────────────────────────────┘
```

---

## 4. Przejście do widoku pełnego koszyka

**User:** Klika ikonę koszyka / "Przejdź do koszyka"

**Cart Service:**
- GET /cart
- Response cart:view + available_promotions + available_services

**Promotion Service:**
- GET /promotions/available?cart_id=xxx
- Zwraca dostępne promocje

**Services Service:**
- GET /services/available?cart_id=xxx
- Zwraca dostępne usługi

**Response (agregowane przez frontend):**
```json
{
  "items": [...],
  "available_promotions": [
    {
      "id": "promo-2x50",
      "name": "2 sztuki laptopa za pół ceny",
      "requires_quantity": 2,
      "applicable_to": ["laptop-uuid"]
    }
  ],
  "available_services": [
    {
      "id": "sms-notification",
      "name": "Powiadomienie SMS",
      "price": 2.00,
      "type": "standalone"
    }
  ]
}
```

---

**Pełny widok koszyka:**
```
┌─────────────────────────────────────────────┐
│ TWÓJ KOSZYK                                 │
├─────────────────────────────────────────────┤
│                                             │
│ Laptop Dell XPS 15           5999.00 PLN    │
│ Ilość: 1                                    │
│  └ Gwarancja 36 miesięcy       299.00 PLN   │
│  └ Torba na laptop             149.00 PLN   │
│                                             │
├─────────────────────────────────────────────┤
│ DOSTĘPNE PROMOCJE                           │
├─────────────────────────────────────────────┤
│ ✓ 2 sztuki laptopa za pół ceny              │
│   Kup 2 laptopy, drugi 50% taniej           │
│   [Zastosuj promocję]                       │
├─────────────────────────────────────────────┤
│ DODATKOWE USŁUGI                            │
├─────────────────────────────────────────────┤
│ □ Powiadomienie SMS o statusie  2.00 PLN    │
│   [Dodaj]                                   │
├─────────────────────────────────────────────┤
│ Razem:                          6447.00 PLN │
│                                             │
│ [Przejdź do dostawy]                        │
└─────────────────────────────────────────────┘
```

---

## 5. Aplikacja promocji "2 za pół ceny"

**User:** Klika "Zastosuj promocję"

**Promotion Service:**
- POST /promotions/apply {promotion_id: "promo-2x50", cart_id: "xxx"}
- Walidacja: czy możliwe (ilość = 1, wymaga 2)
- Akcja: zwiększ quantity laptopa do 2

**Call do Cart Service:**
- PATCH /cart/items/laptop-uuid {quantity: 2}
- Update cart:view

**Promotion Service:**
- Oblicz rabat: laptop #2 = 5999 * 0.5 = 2999.50
- Zapisz: promotions:applied:{session_id}

**Kafka:**
- Publish: PromotionApplied
- Publish: CartItemQuantityChanged (Cart Service)

**Konsumenci:**
- Checkout Service: update totals
    - cart_items_total = (5999 + 299 + 149) + (5999 + 299 + 149) = 12894
    - promotion_discount = -2999.50
    - subtotal = 9894.50

---

**Pełny widok koszyka (po promocji):**
```
┌─────────────────────────────────────────────┐
│ TWÓJ KOSZYK                                 │
├─────────────────────────────────────────────┤
│                                             │
│ Laptop Dell XPS 15          11998.00 PLN    │
│ Ilość: 2                                    │
│  └ Gwarancja 36 m (x2)        598.00 PLN    │
│  └ Torba na laptop (x2)       298.00 PLN    │
│                                             │
├─────────────────────────────────────────────┤
│ ZASTOSOWANE PROMOCJE                        │
├─────────────────────────────────────────────┤
│ ✓ 2 sztuki za pół ceny     -2999.50 PLN    │
│   [Usuń promocję]                           │
├─────────────────────────────────────────────┤
│ DODATKOWE USŁUGI                            │
├─────────────────────────────────────────────┤
│ □ Powiadomienie SMS           2.00 PLN      │
│   [Dodaj]                                   │
├─────────────────────────────────────────────┤
│ Produkty:                      12894.00 PLN │
│ Promocje:                      -2999.50 PLN │
│ Razem:                          9894.50 PLN │
│                                             │
│ [Przejdź do dostawy]                        │
└─────────────────────────────────────────────┘
```

---

**SimpleCart:**
```
┌────────────────────────────┐
│ KOSZYK (5)                 │
├────────────────────────────┤
│ Laptop Dell XPS x2  11998  │
│  └ Gwarancja 36m x2   598  │
│  └ Torba x2           298  │
│                            │
│ Promocja:          -2999.5 │
│ Razem:              9894.5 │
└────────────────────────────┘
```

---

## 6. Dodanie usługi powiadomienia SMS

**User:** Klika "Dodaj" przy "Powiadomienie SMS"

**Cart Service:**
- POST /cart/items {type: "service_standalone", service_id: "sms-notif", price: 2.00}
- Response 202 Accepted
- Update cart:view

**Kafka:**
- Publish: CartItemAdded (type=service_standalone)

**Konsumenci:**
- Checkout Service: update totals

---

**SimpleCart:**
```
┌────────────────────────────┐
│ KOSZYK (6)                 │
├────────────────────────────┤
│ Laptop Dell XPS x2  11998  │
│  └ Gwarancja 36m x2   598  │
│  └ Torba x2           298  │
│ Powiadomienie SMS       2  │
│                            │
│ Promocja:          -2999.5 │
│ Razem:              9896.5 │
└────────────────────────────┘
```

---

## 7. Przejście do transportu

**User:** Klika "Przejdź do dostawy"

**Shipping Service:**
- GET /shipping/available?cart_id=xxx
- Oblicz hash, sprawdź cache
- Response: dostępne metody

---

**Widok z SimpleCart:**
```
┌─────────────────────────────────────────────┐
│ WYBIERZ SPOSÓB DOSTAWY                      │
├─────────────────────────────────────────────┤
│ ○ Kurier DPD              15.99 PLN         │
│ ○ InPost Paczkomat        12.99 PLN         │
│ ○ Odbiór w sklepie         0.00 PLN         │
│                                             │
│ [Dalej]                                     │
└─────────────────────────────────────────────┘

        ┌────────────────────────┐
        │ KOSZYK (6)             │
        ├────────────────────────┤
        │ Laptop x2       11998  │
        │  └ Gwarancja x2   598  │
        │  └ Torba x2       298  │
        │ SMS                 2  │
        │                        │
        │ Promocja:      -2999.5 │
        │ Razem:          9896.5 │
        └────────────────────────┘
```

---

## 8. Wybór kuriera + adres

**User:** Wybiera "Kurier DPD", podaje adres (kod: 00-001)

**Shipping Service:**
- POST /shipping/select {method_id: "courier_dpd"}
- POST /shipping/address {postal_code: "00-001", ...}

**Kafka:**
- Publish: ShippingMethodSelected
- Publish: ShippingAddressProvided

**Konsumenci:**
- Checkout Service: update totals (shipping_total = 15.99)

---

**SimpleCart:**
```
┌────────────────────────────┐
│ KOSZYK (6)                 │
├────────────────────────────┤
│ Laptop x2       11998      │
│  └ Gwarancja x2   598      │
│  └ Torba x2       298      │
│ SMS                 2      │
│                            │
│ Promocja:      -2999.5     │
│ Dostawa:          15.99    │
│ Razem:          9912.49    │
└────────────────────────────┘
```

---

## 9. Kalendarz + dostawa jutro

**User:** Widzi kalendarz, wybiera "Dostawa jutro" (express +29.99 PLN)

**Shipping Service:**
- GET /shipping/calendar
- POST /shipping/set-date {date: "jutro", express: true}

**Call do Cart Service:**
- POST /cart/items {type: "service_shipping", service_id: "express", price: 29.99}

**Kafka:**
- Publish: DeliveryDateSelected
- Publish: CartItemAdded (type=service_shipping)

**Konsumenci:**
- Checkout Service: update totals

---

**SimpleCart:**
```
┌────────────────────────────┐
│ KOSZYK (7)                 │
├────────────────────────────┤
│ Laptop x2       11998      │
│  └ Gwarancja x2   598      │
│  └ Torba x2       298      │
│ SMS                 2      │
│ Express delivery   29.99   │
│                            │
│ Promocja:      -2999.5     │
│ Dostawa:          15.99    │
│ Razem:          9942.48    │
└────────────────────────────┘
```

---

## 10. Dodanie usługi wniesienia

**User:** Widzi "Usługi dodatkowe dla Twojego zamówienia"

**Services Service:**
- GET /services/available?cart_id=xxx
- Analiza: 2x laptop (waga: 4kg razem) → wniesienie NIE (za lekkie)
- Analiza: dostępne usługi standalone

**User:** Wraca do koszyka, dodaje lodówkę (AGD, 80kg, 3999 PLN)

**Cart Service:**
- POST /cart/items {offer_id: 999, type: "product", quantity: 1}

**Services Service (po CartItemAdded):**
- Analiza: lodówka 80kg → wniesienie DOSTĘPNE (99 PLN)

**User:** Widzi "Wniesienie i rozpakowanie - 99 PLN", klika "Dodaj"

**Cart Service:**
- POST /cart/items {type: "service_item", service_id: "carrying", parent_item_id: "lodowka-uuid", price: 99}

**Kafka:**
- Publish: CartItemAdded (lodówka)
- Publish: CartItemAdded (wniesienie)

**Konsumenci:**
- Shipping Service: invalidate cache (waga się zmieniła!)
- Promotion Service: przelicz promocje
- Checkout Service: update totals

---

**SimpleCart:**
```
┌────────────────────────────┐
│ KOSZYK (9)                 │
├────────────────────────────┤
│ Laptop x2       11998      │
│  └ Gwarancja x2   598      │
│  └ Torba x2       298      │
│ Lodówka          3999      │
│  └ Wniesienie       99     │
│ SMS                 2      │
│ Express            29.99   │
│                            │
│ Promocja:      -2999.5     │
│ Dostawa:          15.99    │
│ Razem:         13941.48    │
└────────────────────────────┘
```

---

## 11. Wybór płatności

**User:** Klika "Przejdź do płatności"

**Payment Service:**
- GET /payment/available?session_id=xxx
- Pobiera totals z Checkout Service (13941.48)
- Response: dostępne metody

**User:** Wybiera "BLIK"

**Payment Service:**
- POST /payment/select {method_id: "blik"}

**Kafka:**
- Publish: PaymentMethodSelected

---

**Widok z SimpleCart:**
```
┌─────────────────────────────────────────────┐
│ WYBIERZ SPOSÓB PŁATNOŚCI                    │
├─────────────────────────────────────────────┤
│ ○ BLIK                     0.00 PLN         │
│ ○ Karta płatnicza          0.00 PLN         │
│ ○ Przelew                  0.00 PLN         │
│ ○ Raty 12x                 0.00 PLN         │
│                                             │
│ [Dalej]                                     │
└─────────────────────────────────────────────┘

        ┌────────────────────────┐
        │ KOSZYK (9)             │
        ├────────────────────────┤
        │ Laptop x2      11998   │
        │ Lodówka         3999   │
        │ Usługi           131   │
        │                        │
        │ Promocja:     -2999.5  │
        │ Dostawa:        15.99  │
        │ Razem:       13941.48  │
        └────────────────────────┘
```

---

## 12. Dane osobowe + zgody

**User:** Wypełnia formularz, zaznacza zgody

**Checkout Service:**
- POST /checkout/customer-data {...}
- POST /checkout/consents {terms: true, privacy: true}

---

**SimpleCart (bez zmian):**
```
┌────────────────────────────┐
│ KOSZYK (9)                 │
├────────────────────────────┤
│ Laptop x2      11998       │
│ Lodówka         3999       │
│ Usługi           131       │
│                            │
│ Promocja:     -2999.5      │
│ Dostawa:        15.99      │
│ Razem:       13941.48      │
└────────────────────────────┘
```

---

## 13. Podsumowanie

**User:** Widzi pełne podsumowanie

```
┌─────────────────────────────────────────────┐
│ PODSUMOWANIE ZAMÓWIENIA                     │
├─────────────────────────────────────────────┤
│ PRODUKTY:                                   │
│ • Laptop Dell XPS 15 (x2)      11998.00 PLN │
│   └ Gwarancja 36m (x2)           598.00 PLN │
│   └ Torba (x2)                   298.00 PLN │
│ • Lodówka Samsung               3999.00 PLN │
│   └ Wniesienie                    99.00 PLN │
│                                             │
│ USŁUGI:                                     │
│ • Powiadomienie SMS                2.00 PLN │
│ • Dostawa express                 29.99 PLN │
│                                             │
│ PROMOCJE:                                   │
│ • 2 za pół ceny              -2999.50 PLN   │
│                                             │
│ DOSTAWA:                                    │
│ • Kurier DPD (jutro)              15.99 PLN │
│ • Adres: ul. Testowa 1, 00-001 Warszawa     │
│                                             │
│ PŁATNOŚĆ:                                   │
│ • BLIK                             0.00 PLN │
│                                             │
├─────────────────────────────────────────────┤
│ RAZEM DO ZAPŁATY:           13941.48 PLN    │
├─────────────────────────────────────────────┤
│                                             │
│ ☑ Akceptuję regulamin                       │
│ ☑ Akceptuję politykę prywatności            │
│                                             │
│ [ZAMÓW I ZAPŁAĆ]                            │
└─────────────────────────────────────────────┘

        ┌────────────────────────┐
        │ KOSZYK (9)             │
        ├────────────────────────┤
        │ Produkty:      16992   │
        │ Promocja:     -2999.5  │
        │ Usługi:          131   │
        │ Dostawa:        15.99  │
        │                        │
        │ RAZEM:       13941.48  │
        └────────────────────────┘
```

---

## 14. Finalizacja

**User:** Klika "ZAMÓW I ZAPŁAĆ"

**Checkout Service:**
- POST /checkout/finalize
- Walidacja completeness
- Real-time validation (Cart, Shipping, Payment Services)
- Utworzenie zamówienia
- Response: {order_id: 12345}

**Kafka:**
- Publish: OrderCreated

**Payment Service (konsumuje OrderCreated):**
- POST /payment/initialize
- Call do Przelewy24
- Response: {transaction_id, blik_required: true}

**Kafka:**
- Publish: PaymentInitialized

**Frontend:** Wyświetla pole "Podaj kod BLIK"

---

**User:** Wpisuje kod "123456"

**Payment Service:**
- POST /payment/blik-code {code: "123456"}
- Gateway procesuje (3s)
- Success

**Kafka:**
- Publish: PaymentSucceeded

**Konsumenci:**
- Checkout Service: update order status = PAID
- Wyślij email potwierdzenie
- Trigger faktury
- Wyczyść koszyk (Cart Service)

---

**Potwierdzenie:**
```
┌─────────────────────────────────────────────┐
│ ✓ ZAMÓWIENIE ZŁOŻONE                        │
├─────────────────────────────────────────────┤
│ Numer zamówienia: #12345                    │
│ Status płatności: OPŁACONE                  │
│                                             │
│ Szacowana dostawa: jutro, 12.02.2026        │
│                                             │
│ Szczegóły wysłaliśmy na: jan@example.com    │
│                                             │
│ [Powrót do sklepu]                          │
└─────────────────────────────────────────────┘
```

**SimpleCart (wyczyszczony):**
```
┌────────────────────────────┐
│ KOSZYK (0)                 │
├────────────────────────────┤
│ Koszyk jest pusty          │
│                            │
│ Razem:                  0  │
└────────────────────────────┘
```

---

## Podsumowanie SimpleCart na każdym kroku:

1. **Po laptopie:** 5999
2. **+ gwarancja:** 6298
3. **+ torba:** 6447
4. **+ promocja (2x laptop):** 9894.5
5. **+ SMS:** 9896.5
6. **+ dostawa:** 9912.49
7. **+ express:** 9942.48
8. **+ lodówka:** 13941.48
9. **+ wniesienie:** 14040.48
10. **Po płatności:** 0 (wyczyszczony)