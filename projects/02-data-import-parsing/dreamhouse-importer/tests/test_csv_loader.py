import tempfile
import unittest
from pathlib import Path

from src.loaders.csv_loader import CsvLoader


class TestCsvLoader(unittest.TestCase):
    def _write(self, content):
        tmp = tempfile.NamedTemporaryFile(mode="wb", suffix=".csv", delete=False)
        tmp.write(content)
        tmp.close()
        return Path(tmp.name)

    def test_utf8_bom_semicolon(self):
        content = b"\xef\xbb\xbfarticle;brand;title;price;quantity;category\nSM58;Shure;Microphone;120;10;Microphones\n"
        path = self._write(content)
        rows = CsvLoader().load(path)
        self.assertEqual(len(rows), 1)
        self.assertEqual(rows[0]["article"], "SM58")
        self.assertEqual(rows[0]["brand"], "Shure")
        self.assertEqual(rows[0]["quantity"], "10")

    def test_comma_delimiter_fallback(self):
        content = b"article,brand,title\nA1,Acme,Widget\n"
        path = self._write(content)
        rows = CsvLoader().load(path)
        self.assertEqual(rows[0]["title"], "Widget")

    def test_skips_empty_rows(self):
        content = b"article;title\nA1;Thing\n\n; \n"
        path = self._write(content)
        rows = CsvLoader().load(path)
        self.assertEqual(len(rows), 1)


if __name__ == "__main__":
    unittest.main()
