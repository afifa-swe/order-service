# Order Service

Небольшой сервис оформления заказа. Тестовое задание, написан на Laravel 13, БД — PostgreSQL.

Задача простая: принять заказ от фронта, разложить его по таблицам (заказ, товары, доставка, оплата), всё это в одной транзакции, с нормальной валидацией и без лишних абстракций.

## Стек

- PHP 8.3+
- Laravel 13
- PostgreSQL 14+

## Как запустить

```bash
composer install
cp .env.example .env
php artisan key:generate
```

В `.env` прописать креды от постгреса:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=order_service
DB_USERNAME=order_service
DB_PASSWORD=order_service_pass
```

Накатить миграции и поднять сервер:

```bash
php artisan migrate
php artisan serve
```

Заказу нужен существующий пользователь (FK), самый быстрый способ завести тестового:

```bash
php artisan tinker --execute='\App\Models\User::factory()->create(["id"=>1,"email"=>"test@example.com"]);'
```

## Эндпоинты

| Метод  | URL                  | Что делает                                    |
|--------|----------------------|-----------------------------------------------|
| POST   | `/api/orders`        | Оформить заказ                                |
| GET    | `/api/orders`        | Список заказов (пагинация, фильтр `user_id`)  |
| GET    | `/api/orders/{id}`   | Один заказ со всеми связями                   |

Во все запросы имеет смысл слать `Accept: application/json`, иначе на 422 Laravel попытается отдать HTML-редирект.

## Структура БД

Четыре таблицы:

- `orders` — шапка заказа (user_id, phone, email, total, status)
- `order_items` — товары, привязаны к заказу
- `deliveries` — доставка, один к одному к заказу
- `payments` — оплата, один к одному к заказу

Доставка и оплата — по одной таблице на каждый тип, лишние поля `nullable`. Вариант с полиморфизмом или STI выглядел избыточным: типов всего два, уникальных инвариантов между ними нет. Если типов станет больше — там уже можно думать про разделение.

**Что лежит в `deliveries`:**
- общее: `type`, `cost`
- самовывоз: `pickup_point_id`
- адрес: `city`, `street`, `house`, `apartment`

**Что лежит в `payments`:**
- общее: `type`, `status`, `amount`
- карта: `card_last4`
- кредит: `credit_provider`, `credit_months`, `monthly_payment`

**Связи:**

```
User     1 ── *  Order
Order    1 ── *  OrderItem
Order    1 ── 1  Delivery
Order    1 ── 1  Payment
```

Дочерние удаляются каскадом при удалении заказа.

## Логика оформления заказа

### Что приходит

Один JSON на `POST /api/orders`:

```json
{
  "user_id": 1,
  "phone": "+79991234567",
  "email": "user@example.com",
  "comment": "опционально",
  "items": [
    {"product_id": 10, "name": "Клавиатура", "quantity": 1, "price": 4500}
  ],
  "delivery": {
    "type": "pickup | address",
    "pickup_point_id": 42,
    "city": "...", "street": "...", "house": "...", "apartment": "..."
  },
  "payment": {
    "type": "card | credit",
    "card_last4": "1234",
    "credit_provider": "Tinkoff",
    "credit_months": 12
  }
}
```

### Что проверяется

Всё через `StoreOrderRequest`. Правила разбиты на три блока — заказ, доставка, оплата — чтобы потом не искать их по одной куче.

- По заказу: `user_id` существует в `users`, телефон и email валидные, в `items` хотя бы один товар с положительным количеством и ценой.
- По доставке: тип должен быть из enum. Если `pickup` — обязателен `pickup_point_id`. Если `address` — обязательны `city`, `street`, `house` (квартира опционально).
- По оплате: тип из enum. Для карты нужны последние 4 цифры. Для кредита — название провайдера и срок в месяцах (от 3 до 36).

Условные поля заведены через `Rule::requiredIf()` — смотрит на `delivery.type` / `payment.type` и включает нужные правила.

### Что сохраняется и в каком порядке

Всё в `OrderService::createOrder()`, одной транзакцией (`DB::transaction`). Если что-то упадёт — откатится всё.

1. Считаем `total`: сумма `price * quantity` по всем товарам плюс стоимость доставки.
2. Создаём запись в `orders` (со статусом `new`).
3. Сохраняем товары через `createMany` — пачкой.
4. Сохраняем доставку: общие поля + специфичные в зависимости от типа (`pickup_point_id` либо `city/street/house/apartment`).
5. Сохраняем оплату: для карты пишем `card_last4`, для кредита — провайдера, срок и считаем `monthly_payment = total / credit_months`.
6. Отдаём созданный заказ с подгруженными связями (`items`, `delivery`, `payment`), ответ 201.

`monthly_payment` считается на бэке, от клиента приходить не должен — так меньше шансов на рассинхрон.

## Декомпозиция

- **Controller** — только HTTP: принять запрос, дёрнуть сервис, вернуть JSON. Ничего больше.
- **FormRequest** — вся валидация. Если меняются правила — только здесь.
- **Service** — вся бизнес-логика. Транзакция, подсчёт, сохранение. Контроллер не должен знать, в каком порядке что создаётся.
- **DTO** — простой readonly-объект `OrderData::fromArray()`, чтобы по сервису не таскать сырой массив из реквеста.
- **Enum** — `DeliveryType` и `PaymentType`. Единая точка правды, используются и в валидации, и в модели (cast), и в сервисе.
- **Model** — связи и casts, без логики.

## Как расширять

Добавить новый тип доставки или оплаты:

1. Новый case в соответствующий enum.
2. Новые поля (nullable) в миграцию `deliveries` / `payments`.
3. Ветка в `OrderService::saveDelivery()` / `savePayment()`.
4. Условные правила в `StoreOrderRequest`.

Enum играет роль контракта — если забыть обновить какой-то слой, PHP подскажет падением.

## Быстрая проверка

Самовывоз + карта:

```bash
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "user_id": 1,
    "phone": "+79991234567",
    "email": "u@example.com",
    "items": [{"product_id": 10, "name": "Клава", "quantity": 1, "price": 4500}],
    "delivery": {"type": "pickup", "pickup_point_id": 42},
    "payment": {"type": "card", "card_last4": "1234"}
  }'
```

Адрес + кредит:

```bash
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "user_id": 1,
    "phone": "+79991234567",
    "email": "u@example.com",
    "items": [{"product_id": 55, "name": "Ноут", "quantity": 1, "price": 60000}],
    "delivery": {"type": "address", "city": "Москва", "street": "Ленина", "house": "10", "apartment": "5", "cost": 500},
    "payment": {"type": "credit", "credit_provider": "Tinkoff", "credit_months": 12}
  }'
```

В ответ прилетит 201 и полный объект заказа с товарами, доставкой и оплатой. Посмотреть в БД можно так:

```bash
sudo -u postgres psql -d order_service -c "SELECT * FROM orders;"
```
