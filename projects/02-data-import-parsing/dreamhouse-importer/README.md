# DreamHouse Importer

Python-импортёр прайс-листов магазина музыкального оборудования: читает прайсы
поставщиков в разных форматах, нормализует товары к единой структуре и
автоматически подбирает фотографии товаров в интернете.

## Стек

- Python ≥ 3.9 (стандартная библиотека + `requests`, `openpyxl`, `Pillow`)
- Парсинг: CSV (UTF-8 BOM / cp1251), XLSX, XML с адаптером поставщика, выгрузки из 1С (уценка)
- Поиск изображений: DuckDuckGo (без API-ключей), скачивание с учётом `robots.txt`
- Тесты: `unittest` (stdlib), 27 тестов

## Что реализовано

- Чтение прайс-листов: CSV (`;`, `,`, таб), XLSX (первый лист, автодетект шапки),
  XML (адаптер поставщика, `generic` / `demo_music`), 1С-выгрузки с двухстрочной шапкой
  и маркерами `Номенклатура / Остаток / Цена уценённого товара`.
- Нормализация товаров к единой структуре `article / brand / title / price / quantity / category`.
- Вывод бренда и артикула из названия (бренд = ведущие слова только из заглавных букв,
  артикул = первый токен с цифрой или дефисом).
- Дедупликация по артикулу (регистронезависимо, первый из дублей).
- Автоматический подбор фото: запрос `"{brand} {article} product"`, fallback `"{brand} {title}"`,
  скоринг кандидатов, фильтр по качеству и размеру (≥500×500), учёт `robots.txt`,
  rate limiting с ретраями.
- Отчёты: `products.csv` (с колонкой `image`) и `image_search_report.csv` со статусами
  (`DOWNLOADED`, `REVIEW`, `SKIPPED`, `ERROR`, `NOT_FOUND`) и сохранёнными URL для ручной проверки.
- CLI: `--input`, `--limit`, `--dry-run`, `--supplier`, `--delay`; все параметры централизованы в `src/config.py`.

## Запуск

```bash
python3 -m pip install --user requests openpyxl Pillow

# тесты
python3 -m unittest discover -s tests

# dry-run: загрузка + нормализация + дедуп, без сети и записи файлов
python3 -m src.main --input input/test_music_price.csv --limit 3 --dry-run

# реальный прогон
python3 -m src.main --input input/test_music_price.csv --limit 3
```

## Ссылки

Исходный репозиторий (приватный): https://github.com/killomind/dreamhouse-importer