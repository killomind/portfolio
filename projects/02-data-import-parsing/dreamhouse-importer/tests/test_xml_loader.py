import tempfile
import unittest
from pathlib import Path

from src.loaders.xml_loader import DemoMusicSupplierAdapter, GenericXmlAdapter, XmlLoader

SAMPLE_XML = """<?xml version="1.0" encoding="utf-8"?>
<price_list>
  <supplier>Demo Music</supplier>
  <items>
    <item>
      <article>SM58</article>
      <brand>Shure</brand>
      <title>Vocal Microphone</title>
      <price>120.50</price>
      <quantity>10</quantity>
      <category>Microphones</category>
    </item>
    <item>
      <article>TS10</article>
      <brand>Acme</brand>
      <title>Guitar Stand</title>
      <price>2000</price>
      <quantity>3</quantity>
      <category>Stands</category>
    </item>
  </items>
</price_list>
"""


class TestXmlLoader(unittest.TestCase):
    def _write(self, content):
        tmp = tempfile.NamedTemporaryFile(mode="w", suffix=".xml", delete=False, encoding="utf-8")
        tmp.write(content)
        tmp.close()
        return Path(tmp.name)

    def test_demo_music_adapter(self):
        path = self._write(SAMPLE_XML)
        rows = XmlLoader(DemoMusicSupplierAdapter()).load(path)
        self.assertEqual(len(rows), 2)
        self.assertEqual(rows[0]["article"], "SM58")
        self.assertEqual(rows[1]["title"], "Guitar Stand")

    def test_generic_adapter(self):
        path = self._write(SAMPLE_XML)
        rows = XmlLoader(GenericXmlAdapter()).load(path)
        self.assertGreaterEqual(len(rows), 1)

    def test_namespace_stripping(self):
        xml = SAMPLE_XML.replace("<price_list>", '<price_list xmlns="http://example.com/x">')
        path = self._write(xml)
        rows = XmlLoader(DemoMusicSupplierAdapter()).load(path)
        self.assertEqual(len(rows), 2)
        self.assertEqual(rows[0]["article"], "SM58")


if __name__ == "__main__":
    unittest.main()
