import unittest

from src.normalize import clean_text, deduplicate, normalize_records, parse_price, parse_quantity
from src.models import Product


class TestNormalize(unittest.TestCase):
    def test_clean_text(self):
        self.assertEqual(clean_text("  Hello   World\t "), "Hello World")

    def test_parse_price_russian(self):
        self.assertEqual(parse_price("1 234,56"), 1234.56)
        self.assertEqual(parse_price("12 500 руб."), 12500.0)
        self.assertEqual(parse_price("₽ 999,90"), 999.9)

    def test_parse_price_us(self):
        self.assertEqual(parse_price("$1,234.56"), 1234.56)

    def test_parse_quantity(self):
        self.assertEqual(parse_quantity("10 шт"), 10)
        self.assertEqual(parse_quantity("5-7"), 5)
        self.assertEqual(parse_quantity(""), 0)

    def test_normalize_records_aliases(self):
        records = [
            {
                "Артикул": " sm58 ",
                "Бренд": "shure",
                "Название": "  Микрофон  SM58  ",
                "Цена": "5 500,50",
                "Количество": "7",
                "Категория": "Микрофоны",
            }
        ]
        products = normalize_records(records)
        self.assertEqual(len(products), 1)
        p = products[0]
        self.assertEqual(p.article, "SM58")
        self.assertEqual(p.brand, "Shure")
        self.assertEqual(p.title, "Микрофон SM58")
        self.assertEqual(p.price, 5500.5)
        self.assertEqual(p.quantity, 7)
        self.assertEqual(p.category, "Микрофоны")

    def test_skips_record_without_article(self):
        products = normalize_records([{"title": "No article here"}])
        self.assertEqual(products, [])

    def test_deduplicate_by_article(self):
        products = [
            Product("SM58", "Shure", "A", 1.0, 1, "C"),
            Product("sm58", "Shure", "B", 2.0, 2, "C"),
            Product("SM57", "Shure", "C", 3.0, 3, "C"),
        ]
        unique = deduplicate(products)
        self.assertEqual([p.article for p in unique], ["SM58", "SM57"])


if __name__ == "__main__":
    unittest.main()
