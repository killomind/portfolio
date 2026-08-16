import argparse
import logging
import sys
from pathlib import Path

import requests

from .config import LOGS_DIR, MAX_DOWNLOAD_ATTEMPTS, OUTPUT_DIR, REQUEST_DELAY
from .downloader import ImageDownloader
from .image_search import (
    DuckDuckGoSearcher,
    RequestRateLimiter,
    build_queries,
    rank_candidates,
)
from .loaders import load_file
from .logging_setup import setup_logging
from .normalize import deduplicate, normalize_records
from .report import (
    DOWNLOADED,
    ERROR,
    NOT_FOUND,
    REVIEW,
    SKIPPED,
    ReportEntry,
    write_normalized,
    write_products,
    write_report,
)

logger = logging.getLogger("importer.main")


def parse_args(argv=None):
    parser = argparse.ArgumentParser(description="Local music gear importer")
    parser.add_argument("--input", type=Path, default=None, help="Path to CSV/XLSX/XML price list")
    parser.add_argument("--limit", type=int, default=0, help="Process only N first products")
    parser.add_argument("--dry-run", action="store_true", help="Do not search or download, only print queries")
    parser.add_argument("--supplier", default="", help="XML supplier adapter name (generic, demo_music)")
    parser.add_argument("--delay", type=float, default=REQUEST_DELAY, help="Seconds between HTTP requests")
    return parser.parse_args(argv)


def build_pipeline(args):
    input_path = args.input
    if input_path is None:
        candidates = sorted(p for p in Path("input").glob("*") if p.suffix.lower() in (".csv", ".xlsx", ".xml"))
        if not candidates:
            print("No input files found in input/ and --input not given.")
            sys.exit(2)
        input_path = candidates[0]
    if not input_path.exists():
        print(f"Input file not found: {input_path}")
        sys.exit(2)
    return input_path


def _classify(reason: str) -> str:
    if "robots" in reason:
        return "robots"
    if "resolution" in reason or "content-type" in reason or "invalid" in reason or "too small" in reason or "too large" in reason:
        return "quality"
    if "http" in reason or "network" in reason:
        return "network"
    return "other"


def _decide_status(failures: list, ambiguous_any: bool, saw_candidates: bool) -> tuple:
    classes = {_classify(reason) for _, reason in failures}
    if ambiguous_any and "quality" not in classes and "network" not in classes:
        return REVIEW, "ambiguous candidates; " + _summarize(failures)
    if "quality" in classes:
        return REVIEW, _summarize(failures)
    if "robots" in classes:
        return SKIPPED, _summarize(failures)
    if "network" in classes:
        return ERROR, _summarize(failures)
    if saw_candidates:
        return NOT_FOUND, "no downloadable candidates"
    return NOT_FOUND, "no candidates found"


def _summarize(failures: list) -> str:
    unique = []
    for _, reason in failures:
        if reason not in unique:
            unique.append(reason)
    return "; ".join(unique[:4]) or "no candidates"


def search_product(searcher, product, downloader):
    queries = build_queries(product)
    failures = []
    saw_candidates = False
    ambiguous_any = False
    best_placeholder = None
    attempts = 0
    for query in queries:
        candidates = searcher.search(query)
        if not candidates:
            continue
        saw_candidates = True
        best_placeholder = candidates[0]
        valid, ambiguous = rank_candidates(candidates, product)
        if ambiguous:
            ambiguous_any = True
            continue
        for candidate in valid:
            if attempts >= MAX_DOWNLOAD_ATTEMPTS:
                break
            attempts += 1
            local_file, reason = downloader.download(candidate.image_url, article=product.article)
            if local_file:
                return ReportEntry(
                    product.article,
                    product.title,
                    query,
                    candidate.image_url,
                    candidate.source_url,
                    local_file,
                    DOWNLOADED,
                    "downloaded",
                )
            failures.append((candidate, reason))
        if attempts >= MAX_DOWNLOAD_ATTEMPTS:
            break
    status, reason = _decide_status(failures, ambiguous_any, saw_candidates)
    first = best_placeholder or (failures[0][0] if failures else None)
    return ReportEntry(
        product.article,
        product.title,
        queries[0],
        first.image_url if first else "",
        first.source_url if first else "",
        "",
        status,
        reason,
    )


def run(argv=None):
    args = parse_args(argv)
    setup_logging(LOGS_DIR / "parser.log")
    input_path = build_pipeline(args)
    logger.info("Loading %s", input_path)
    records = load_file(input_path, supplier=args.supplier)
    products = normalize_records(records)
    products = deduplicate(products)
    logger.info("Loaded %d records, normalized %d unique products", len(records), len(products))
    if args.limit:
        products = products[: args.limit]

    limiter = RequestRateLimiter(args.delay)

    if args.dry_run:
        print("\nDRY RUN — товары ({n}) и запросы для поиска изображений:\n".format(n=len(products)))
        for product in products:
            queries = build_queries(product)
            print(f"  {product.article:12s} {queries[0]}")
            for query in queries[1:]:
                print(f"  {'':12s} {query}")
        print("\nНикакие файлы не изменялись (dry run).")
        return

    normalized_path = OUTPUT_DIR / "normalized_products.csv"
    write_normalized(products, normalized_path)
    logger.info("Saved intermediate result to %s", normalized_path)

    session = requests.Session()
    session.headers["User-Agent"] = (
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
    )
    searcher = DuckDuckGoSearcher(session=session, limiter=limiter)
    downloader = ImageDownloader(session=session, limiter=limiter)

    entries = []
    image_map = {}
    for index, product in enumerate(products, start=1):
        logger.info("[%d/%d] Processing %s", index, len(products), product.article)
        entry = search_product(searcher, product, downloader)
        entries.append(entry)
        if entry.local_file:
            image_map[product.article] = entry.local_file
        elif entry.image_url:
            image_map[product.article] = entry.image_url
        print(f"  {product.article:12s} -> {entry.status:10s} {entry.reason}")

    report_path = OUTPUT_DIR / "image_search_report.csv"
    write_report(entries, report_path)
    products_path = OUTPUT_DIR / "products.csv"
    write_products(products, image_map, products_path)
    logger.info("Report written to %s", report_path)
    logger.info("Products written to %s", products_path)


def main():
    run()


if __name__ == "__main__":
    main()
