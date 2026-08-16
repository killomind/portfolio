import csv
from typing import Any, Dict, List

from .base import BaseLoader
from ._text import _strip_cell


class CsvLoader(BaseLoader):
    name = "csv"

    def load(self, path) -> List[Dict[str, Any]]:
        raw = path.read_bytes()
        text = _decode(raw)
        delimiter = _detect_delimiter(text)
        reader = csv.DictReader(text.splitlines(), delimiter=delimiter)
        rows = []
        for row in reader:
            if row is None:
                continue
            cleaned = {k.strip(): _strip_cell(v) for k, v in row.items() if k is not None}
            if any(value for value in cleaned.values()):
                rows.append(cleaned)
        return rows


def _decode(raw: bytes) -> str:
    for encoding in ("utf-8-sig", "utf-8", "cp1251"):
        try:
            return raw.decode(encoding)
        except UnicodeDecodeError:
            continue
    return raw.decode("utf-8", errors="replace")


def _detect_delimiter(text: str) -> str:
    first = text.splitlines()[0] if text.splitlines() else ""
    if ";" in first:
        return ";"
    if "\t" in first:
        return "\t"
    return ","
