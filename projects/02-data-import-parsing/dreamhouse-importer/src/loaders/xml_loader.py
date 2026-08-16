from typing import Any, Dict, List
from xml.etree import ElementTree

from .base import BaseLoader
from ._text import _strip_cell

ARTICLE_KEYS = ("article", "sku", "code", "art", "vendor")
BRAND_KEYS = ("brand", "manufacturer", "producer", "vendor_name", "brend")
TITLE_KEYS = ("title", "name", "product", "item", "model", "название")
PRICE_KEYS = ("price", "cost", "unit_price", "price_rub")
QUANTITY_KEYS = ("quantity", "qty", "stock", "count", "amount", "rest")
CATEGORY_KEYS = ("category", "cat", "group", "type", "section")


class SupplierAdapter:
    name = "generic"

    def parse(self, root) -> List[Dict[str, Any]]:
        raise NotImplementedError


class GenericXmlAdapter(SupplierAdapter):
    name = "generic"

    def parse(self, root) -> List[Dict[str, Any]]:
        records = []
        for element in root.iter():
            values = {}
            for child in element:
                tag = _local(child.tag).strip().lower()
                if not tag:
                    continue
                values.setdefault(tag, _strip_cell(child.text))
            if _has_key(values, ARTICLE_KEYS) and _has_key(values, TITLE_KEYS):
                records.append(values)
        return records


class DemoMusicSupplierAdapter(GenericXmlAdapter):
    name = "demo_music"

    def parse(self, root) -> List[Dict[str, Any]]:
        items = []
        for item in root.iter():
            tag = _local(item.tag).strip().lower()
            if tag in ("item", "product", "offer"):
                values = {}
                for child in item:
                    key = _local(child.tag).strip().lower()
                    if key and not values.get(key):
                        values[key] = _strip_cell(child.text)
                if _has_key(values, ARTICLE_KEYS):
                    items.append(values)
        return items


def _local(tag: str) -> str:
    if tag.startswith("{"):
        return tag.split("}", 1)[1]
    return tag


def _has_key(values: Dict[str, Any], keys) -> bool:
    lowered = {k.lower() for k in values}
    return any(k in lowered for k in keys)


class XmlLoader(BaseLoader):
    name = "xml"

    def __init__(self, adapter: SupplierAdapter = None):
        self.adapter = adapter or DemoMusicSupplierAdapter()

    def load(self, path) -> List[Dict[str, Any]]:
        tree = ElementTree.parse(path)
        return self.adapter.parse(tree.getroot())
