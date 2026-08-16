import tempfile
import unittest
from pathlib import Path

from openpyxl import Workbook

from src.loaders.xlsx_loader import XlsxLoader


class TestXlsxLoader(unittest.TestCase):
    def test_load(self):
        wb = Workbook()
        ws = wb.active
        ws.append(["article", "brand", "title", "price", "quantity", "category"])
        ws.append(["K1", "Korg", "Keyboard", 34000, 4, "Keys"])
        ws.append(["K1", "Korg", "Duplicate", 35000, 1, "Keys"])
        tmp = tempfile.NamedTemporaryFile(suffix=".xlsx", delete=False)
        wb.save(tmp.name)
        tmp.close()
        rows = XlsxLoader().load(Path(tmp.name))
        self.assertEqual(len(rows), 2)
        self.assertEqual(rows[0]["article"], "K1")
        self.assertEqual(rows[0]["price"], "34000")


if __name__ == "__main__":
    unittest.main()
