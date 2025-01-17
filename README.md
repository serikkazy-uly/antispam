# Сервис "антиспам" на PHP

Создать сервис который принимает на вход сообщение и проверяет является ли оно спамом.

Ниже описан список проверок которые нужно реализовать.

## Нормализация сообщения

Входящее сообщение нужно разделить на токены и нормализовать:

1. Разделять предложения на токены по регулярному выражению `[\.\,\!\?\[\]\(\)\<\>\:\;\-\n\'\r\s\"\/\*\|]+`
2. Привести к нижнему регистру
3. Удалить стоп слова (см. приложение)
4. Удалить слова состоящие из чисел
5. Сортировать по алфавиту

## Проверки

По результату проверок нужно решить является ли сообщение спамом

### Проверка #1 (block_list)
Проверять на наличие слов из запрещенного списка (см. приложение). Так же если сообщение содержит эл. почту считать его спамом (см. функцию filter_var($email, FILTER_VALIDATE_EMAIL)).

Если присутствует такое слово, то считать сообщение спамом.

### Проверка #2 (mixed_words)
Проверить нормализованное сообщение на наличие разных раскладок в одном слове (проверять только кириллицу и английский).

Если присутствует такое слово, то считать сообщение спамом.

### Проверка #3 (duplicate)
Если в нормализованном предложении >= 60% токенов такие же как в предыдущем, то считать сообщение спамом. 
Проверка включается только если в нормализованном предложении >= 3 токенов.

### Проверка #4 (check_rate)
Проверять на сколько часто приходят сообщения. Если приходит более 1 сообщения в 2 секунды, считать сообщения спамом.

Эта проверка включается опционально через параметр `check_rate=1`

---

Проверки нужно проводить в приведенном выше порядке. При первой положительной проверке, возвращать результат.

## Формат взаимодействия с сервисом

### Запрос

```
POST /is_spam
Content-Type: application/x-www-form-urlencoded

text=текст сообщения&check_rate=0
```

- text - текст сообщения
- check_rate - включена ли проверка на частоту сообщений. Принимаются значения 1 и 0

### Ответ:

Не спам:

```
HTTP/1.1 200 OK

{
    "status": "ok",
    "spam": false,
    "reason": ""
    "normalized_text": "нормализованные токены через пробел",
}
```

Спам:

```
HTTP/1.1 200 OK

{
    "status": "ok",
    "spam": true,
    "reason": "причина почему сообщение считается спамом (block_list, duplicate итп.)",
    "normalized_text": "нормализованные токены через пробел"
}
```

Ошибка:

```
HTTP/1.1 400 OK

{
    "status": "error",
    "message": "field text required"
}
```

## Советы

Для хранения данных использовать redis. Он развернут как сервис в docker-compose.

## Запуск тестов

Для запуска тестов нужно выполнить команду из корня проекта:

```
docker-compose up tests
```

В результате все тесты будут проходить когда сервис будет полностью реализован.

## Приложение

[Стоп слова](./docs/stopwords.txt)

[Запрещенные слова](./docs/blocklist.txt)
