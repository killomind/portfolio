import tempfile
import unittest
from pathlib import Path

from src.loaders.onec_loader import OneCPriceListLoader
from src.normalize import derive_identity, normalize_records

SAMPLE = "\ufeff;Уценка;;;;;;\n;;;;;;;\n;В валюте: руб. (курс 1).;;;;;;\n;;;;;;;\n;Номенклатура;Остаток;;Цена уценённого товара;;;\n;;;Описание;Цена;Фото;Ед.;\n;ABK EHR-200 Трансформатор для аттенюатора;1,000;;2\xa0582,75 руб.;;шт;\n;\"ABK PA-2612U Микшер; 120 Вт\";1,000;;22\xa0909,19 руб.;;шт;\n;ZECK F42S Акустическая система;2,000;;9\xa0347,00 руб.;;шт;\n"

CSV = "\ufeffarticle;brand;title;price;quantity;category\nSM58;Shure;Microphone;120;10;Microphones\n"


class TestOneCPriceListLoader(unittest.TestCase):
    def test_load(self):
        tmp = tempfile.NamedTemporaryFile(mode="w", suffix=".csv", encoding="utf-8", delete=False)
        tmp.write(SAMPLE)
        tmp.close()
        rows = OneCPriceListLoader().load(Path(tmp.name))
        self.assertEqual(len(rows), 3)
        self.assertEqual(rows[0]["Номенклатура"], "ABK EHR-200 Трансформатор для аттенюатора")
        self.assertEqual(rows[0]["Остаток"], "1,000")
        self.assertEqual(rows[0]["Цена уценённого товара"], "2\u00a0582,75 руб.")
        self.assertEqual(rows[0]["Категория"], "Уценка")
        self.assertEqual(rows[1]["Номенклатура"], "ABK PA-2612U Микшер; 120 Вт")

    def test_normalize_onec(self):
        tmp = tempfile.NamedTemporaryFile(mode="w", suffix=".csv", encoding="utf-8", delete=False)
        tmp.write(SAMPLE)
        tmp.close()
        rows = OneCPriceListLoader().load(Path(tmp.name))
        products = normalize_records(rows)
        self.assertEqual(len(products), 3)
        self.assertEqual(products[0].article, "EHR-200")
        self.assertEqual(products[0].brand, "Abk")
        self.assertEqual(products[0].price, 2582.75)
        self.assertEqual(products[0].quantity, 1)
        self.assertEqual(products[1].article, "PA-2612U")
        self.assertEqual(products[2].article, "F42S")


class TestDeriveIdentity(unittest.TestCase):
    def test_abk(self):
        self.assertEqual(derive_identity("ABK EHR-200 Трансформатор для аттенюатора"), ("ABK", "EHR-200"))

    def test_multiword_brand(self):
        self.assertEqual(derive_identity("МУЗЫКАЛЬНЫЙ АРСЕНАЛ 03403 Чехол для гитары"), ("МУЗЫКАЛЬНЫЙ АРСЕНАЛ", "03403"))

    def test_no_brand(self):
        self.assertEqual(derive_identity("Эконом-панели 1400х2400"), ("", ""))

    def test_no_model(self):
        self.assertEqual(derive_identity("ПРОТОН Фильтр стеклянный желтый"), ("ПРОТОН", ""))


class TestPlainCsvStillWorks(unittest.TestCase):
    def test_normalize(self):
        from src.loaders.csv_loader import CsvLoader

        tmp = tempfile.NamedTemporaryFile(mode="wb", suffix=".csv", delete=False)
        tmp.write(CSV.encode("utf-8"))
        tmp.close()
        rows = CsvLoader().load(Path(tmp.name))
        products = normalize_records(rows)
        self.assertEqual(products[0].article, "SM58")
        self.assertEqual(products[0].brand, "Shure")


if __name__ == "__main__":
    unittest.main()
