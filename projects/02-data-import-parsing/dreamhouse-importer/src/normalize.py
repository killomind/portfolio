import re
from typing import Any, Dict, Iterable, List

from .models import Product

_WS_RE = re.compile(r"\s+")

ARTICLE_ALIASES = ("article", "sku", "code", "art", "артикул", "код", "арт")
BRAND_ALIASES = ("brand", "manufacturer", "producer", "бренд", "производитель", "произв")
TITLE_ALIASES = ("title", "name", "product", "item", "название", "наименование", "товар", "номенклатура")
PRICE_ALIASES = ("price", "cost", "unit_price", "цена", "price_rub", "цена уценённого товара", "цена уцененного товара", "цена уценки")
QUANTITY_ALIASES = ("quantity", "qty", "stock", "count", "количество", "остаток", "наличие")
CATEGORY_ALIASES = ("category", "cat", "group", "категория", "тип", "раздел")

_ALIAS_MAP = {
    "article": {a.lower() for a in ARTICLE_ALIASES},
    "brand": {a.lower() for a in BRAND_ALIASES},
    "title": {a.lower() for a in TITLE_ALIASES},
    "price": {a.lower() for a in PRICE_ALIASES},
    "quantity": {a.lower() for a in QUANTITY_ALIASES},
    "category": {a.lower() for a in CATEGORY_ALIASES},
}


def clean_text(value) -> str:
    if value is None:
        return ""
    text = str(value).replace("\ufeff", "").replace("\x00", "")
    return _WS_RE.sub(" ", text).strip()


def normalize_article(value) -> str:
    text = clean_text(value).upper()
    return text


def normalize_brand(value) -> str:
    text = clean_text(value)
    return " ".join(word.capitalize() for word in text.split())


def normalize_title(value) -> str:
    return clean_text(value)


def parse_price(value) -> float:
    if value is None:
        return 0.0
    if isinstance(value, (int, float)):
        return float(value)
    text = re.sub(r"[^\d.,]", "", clean_text(value))
    text = text.rstrip(".,")
    if not text:
        return 0.0
    text = text.replace(",", ".")
    if text.count(".") > 1:
        parts = text.split(".")
        text = "".join(parts[:-1]) + "." + parts[-1]
    if text.count(".") == 1:
        whole, frac = text.split(".")
        if len(frac) == 3 and (len(whole) > 1 or whole != "0"):
            text = text.replace(".", "")
    try:
        return round(float(text), 2)
    except ValueError:
        return 0.0


def parse_quantity(value) -> int:
    if value is None:
        return 0
    if isinstance(value, bool):
        return 1 if value else 0
    if isinstance(value, (int, float)):
        return int(value)
    text = clean_text(value).replace(",", ".")
    numbers = re.findall(r"-?\d+", text)
    if not numbers:
        return 0
    return int(numbers[0])


_UPPER_ONLY_RE = re.compile(r"^[A-ZА-Я]+$")
_MODEL_CHARS_RE = re.compile(r"^[A-ZА-Я0-9\-/]+$")
_TRAIL_PUNCT_RE = re.compile(r"[.,;:\"'()\[\]{}]+$")
_LEAD_PUNCT_RE = re.compile(r"^[.,;:\"'()\[\]{}]+")


def _looks_like_model(token: str) -> bool:
    token = _LEAD_PUNCT_RE.sub("", _TRAIL_PUNCT_RE.sub("", token))
    if not _MODEL_CHARS_RE.fullmatch(token):
        return False
    return any(ch.isdigit() for ch in token)


def derive_identity(title: str):
    text = clean_text(title)
    brand_tokens = []
    article = ""
    for token in text.split():
        if _UPPER_ONLY_RE.match(token):
            brand_tokens.append(token)
            continue
        if _looks_like_model(token):
            article = _LEAD_PUNCT_RE.sub("", _TRAIL_PUNCT_RE.sub("", token))
        break
    return " ".join(brand_tokens), article


def _pick(record: Dict[str, Any], aliases) -> str:
    lowered = {str(k).strip().lower(): v for k, v in record.items()}
    for alias in aliases:
        if alias in lowered:
            return lowered[alias]
    return ""


def normalize_records(records: Iterable[Dict[str, Any]]) -> List[Product]:
    products = []
    for record in records:
        article = normalize_article(_pick(record, ARTICLE_ALIASES))
        brand = normalize_brand(_pick(record, BRAND_ALIASES))
        title = normalize_title(_pick(record, TITLE_ALIASES))
        if not article or not brand:
            derived_brand, derived_article = derive_identity(title)
            if not article:
                article = normalize_article(derived_article)
            if not brand:
                brand = normalize_brand(derived_brand)
        if not article:
            continue
        price = parse_price(_pick(record, PRICE_ALIASES))
        quantity = parse_quantity(_pick(record, QUANTITY_ALIASES))
        category = clean_text(_pick(record, CATEGORY_ALIASES))
        products.append(
            Product(
                article=article,
                brand=brand,
                title=title,
                price=price,
                quantity=quantity,
                category=category,
            )
        )
    return products


def deduplicate(products: Iterable[Product]) -> List[Product]:
    seen = set()
    unique = []
    for product in products:
        key = product.article.upper()
        if key in seen:
            continue
        seen.add(key)
        unique.append(product)
    return unique
