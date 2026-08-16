from typing import Any, Dict, List

from ..config import SUPPORTED_EXTENSIONS
from .base import BaseLoader
from .csv_loader import CsvLoader
from .onec_loader import OneCPriceListLoader
from .xlsx_loader import XlsxLoader
from .xml_loader import DemoMusicSupplierAdapter, GenericXmlAdapter, XmlLoader

_LOADERS = {
    ".csv": CsvLoader,
    ".xlsx": XlsxLoader,
}

_ADAPTERS = {
    "generic": GenericXmlAdapter,
    "demo_music": DemoMusicSupplierAdapter,
}

_ONEC_MARKERS = ("Номенклатура", "Остаток", "Цена уценённого товара")


def load_file(path, supplier: str = "") -> List[Dict[str, Any]]:
    suffix = path.suffix.lower()
    if suffix not in SUPPORTED_EXTENSIONS:
        raise ValueError(f"Unsupported file extension: {suffix}")
    if suffix == ".xml":
        adapter = _ADAPTERS.get(supplier, DemoMusicSupplierAdapter())()
        return XmlLoader(adapter).load(path)
    if suffix == ".csv":
        if supplier == "onec" or _looks_like_onec(path):
            return OneCPriceListLoader().load(path)
    return _LOADERS[suffix]().load(path)


def _looks_like_onec(path) -> bool:
    chunk = ""
    try:
        with open(path, "rb") as fh:
            chunk = fh.read(4096)
    except OSError:
        return False
    for encoding in ("utf-8-sig", "utf-8", "cp1251"):
        try:
            text = chunk.decode(encoding)
            break
        except UnicodeDecodeError:
            continue
    else:
        return False
    return all(marker in text for marker in _ONEC_MARKERS)
