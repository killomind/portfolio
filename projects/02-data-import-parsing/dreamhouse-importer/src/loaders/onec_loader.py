import csv
import io
from typing import Any, Dict, List

from .base import BaseLoader
from ._text import _strip_cell

_HEADER_MARKER = "Номенклатура"


class OneCPriceListLoader(BaseLoader):
    name = "onec"
    category = "Уценка"

    def load(self, path) -> List[Dict[str, Any]]:
        raw = path.read_bytes()
        text = _decode(raw)
        rows = list(csv.reader(io.StringIO(text), delimiter=";"))
        header_idx = None
        for index, row in enumerate(rows):
            if any(_HEADER_MARKER in str(cell) for cell in row):
                header_idx = index
                break
        if header_idx is None:
            return []
        top = rows[header_idx]
        bottom = rows[header_idx + 1] if header_idx + 1 < len(rows) else []
        width = max(len(top), len(bottom))
        names = []
        for idx in range(width):
            a = _strip_cell(top[idx]) if idx < len(top) else ""
            b = _strip_cell(bottom[idx]) if idx < len(bottom) else ""
            merged = a or b
            names.append(merged if merged else f"_col{idx}")
        records = []
        for row in rows[header_idx + 2:]:
            if not any(str(cell).strip() for cell in row):
                continue
            record = {}
            for idx, cell in enumerate(row):
                if idx >= len(names):
                    break
                name = names[idx]
                if name.startswith("_col"):
                    continue
                record[name] = _strip_cell(cell)
            if record.get("Номенклатура"):
                record["Категория"] = self.category
                records.append(record)
        return records


def _decode(raw: bytes) -> str:
    for encoding in ("utf-8-sig", "utf-8", "cp1251"):
        try:
            return raw.decode(encoding)
        except UnicodeDecodeError:
            continue
    return raw.decode("utf-8", errors="replace")
