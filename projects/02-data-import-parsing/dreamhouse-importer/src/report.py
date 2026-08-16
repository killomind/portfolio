import csv
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, List

from .models import Product


@dataclass
class ReportEntry:
    article: str
    title: str
    search_query: str
    image_url: str
    source_url: str
    local_file: str
    status: str
    reason: str


FOUND = "FOUND"
DOWNLOADED = "DOWNLOADED"
NOT_FOUND = "NOT_FOUND"
REVIEW = "REVIEW"
ERROR = "ERROR"
SKIPPED = "SKIPPED"


def write_report(entries: Iterable[ReportEntry], path: Path):
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8-sig") as fh:
        writer = csv.DictWriter(
            fh,
            fieldnames=["article", "title", "search_query", "image_url", "source_url", "local_file", "status", "reason"],
            delimiter=";",
        )
        writer.writeheader()
        for entry in entries:
            writer.writerow(
                {
                    "article": entry.article,
                    "title": entry.title,
                    "search_query": entry.search_query,
                    "image_url": entry.image_url,
                    "source_url": entry.source_url,
                    "local_file": entry.local_file,
                    "status": entry.status,
                    "reason": entry.reason,
                }
            )


def write_normalized(products: Iterable[Product], path: Path):
    fields = ["article", "brand", "title", "price", "quantity", "category"]
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8-sig") as fh:
        writer = csv.DictWriter(fh, fieldnames=fields, delimiter=";")
        writer.writeheader()
        for product in products:
            row = product.to_dict()
            writer.writerow({key: row[key] for key in fields})


def write_products(products: Iterable[Product], image_map, path: Path):
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8-sig") as fh:
        writer = csv.DictWriter(
            fh,
            fieldnames=["article", "brand", "title", "price", "quantity", "category", "image"],
            delimiter=";",
        )
        writer.writeheader()
        for product in products:
            row = product.to_dict()
            row["image"] = image_map.get(product.article, "")
            writer.writerow({key: row[key] for key in ["article", "brand", "title", "price", "quantity", "category", "image"]})
