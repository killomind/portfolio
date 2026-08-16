import json
import logging
import re
import time
from dataclasses import dataclass
from typing import List, Optional
from urllib.parse import quote, urlparse

import requests

from .config import (
    AMBIGUOUS_SCORE,
    EXCLUDE_URL_KEYWORDS,
    HTTP_MAX_RETRIES,
    HTTP_TIMEOUT,
    MAX_CANDIDATES,
    MIN_CANDIDATE_SCORE,
    REQUEST_DELAY,
    RETRY_BACKOFF,
    USER_AGENT,
)
from .models import Product

logger = logging.getLogger("importer.search")


@dataclass
class ImageCandidate:
    image_url: str
    source_url: str
    title: str = ""
    score: int = 0


class RequestRateLimiter:
    def __init__(self, delay: float = REQUEST_DELAY):
        self.delay = delay
        self._last = 0.0

    def wait(self):
        now = time.monotonic()
        elapsed = now - self._last
        if elapsed < self.delay:
            time.sleep(self.delay - elapsed)
        self._last = time.monotonic()


class DuckDuckGoSearcher:
    def __init__(self, session: Optional[requests.Session] = None, limiter: Optional[RequestRateLimiter] = None):
        self.session = session or requests.Session()
        self.session.headers["User-Agent"] = USER_AGENT
        self.limiter = limiter or RequestRateLimiter()

    def search(self, query: str) -> List[ImageCandidate]:
        vqd = self._get_vqd(query)
        if not vqd:
            return []
        return self._image_results(query, vqd)

    def _get_vqd(self, query: str) -> str:
        url = "https://duckduckgo.com/"
        response = self._request("GET", url, params={"q": query})
        if not response:
            return ""
        match = re.search(r"vqd=([\d-]+)", response.text)
        if match:
            return match.group(1)
        match = re.search(r"vqd\s*=\s*['\"]([\d-]+)['\"]", response.text)
        if match:
            return match.group(1)
        match = re.search(r"vqd\s*[:=]\s*['\"]([\d-]+)['\"]", response.text)
        return match.group(1) if match else ""

    def _image_results(self, query: str, vqd: str) -> List[ImageCandidate]:
        url = "https://duckduckgo.com/i.js"
        params = {
            "l": "us-en",
            "o": "json",
            "q": query,
            "vqd": vqd,
            "f": ",,,",
            "p": "1",
        }
        response = self._request("GET", url, params=params)
        if not response:
            return []
        try:
            payload = response.json()
        except ValueError:
            return []
        results = payload.get("results") or []
        candidates = []
        for item in results:
            image = (item.get("image") or "").strip()
            source = (item.get("url") or "").strip()
            title = (item.get("title") or "").strip()
            if not image:
                continue
            candidates.append(ImageCandidate(image_url=image, source_url=source, title=title))
            if len(candidates) >= MAX_CANDIDATES:
                break
        return candidates

    def _request(self, method: str, url: str, **kwargs):
        self.limiter.wait()
        kwargs.setdefault("timeout", HTTP_TIMEOUT)
        last_error = None
        for attempt in range(1, HTTP_MAX_RETRIES + 1):
            try:
                response = self.session.request(method, url, **kwargs)
                if response.status_code == 200:
                    return response
                if response.status_code in (403, 429):
                    backoff = RETRY_BACKOFF * attempt
                    logger.warning("HTTP %s for %s, retry %s in %.1fs", response.status_code, url, attempt, backoff)
                    time.sleep(backoff)
                    continue
                logger.warning("HTTP %s for %s", response.status_code, url)
                return None
            except requests.RequestException as exc:
                last_error = exc
                backoff = RETRY_BACKOFF * attempt
                logger.warning("Request error for %s (%s), retry %s in %.1fs", url, exc, attempt, backoff)
                time.sleep(backoff)
        if last_error:
            logger.error("Giving up on %s: %s", url, last_error)
        return None


def build_queries(product: Product) -> List[str]:
    primary = f"{product.brand} {product.article} product".strip()
    fallback = f"{product.brand} {product.title}".strip()
    queries = [primary]
    if fallback.lower() != primary.lower():
        queries.append(fallback)
    return queries


KNOWN_NON_IMAGE_EXTENSIONS = (".mp4", ".webm", ".mov", ".avi", ".pdf", ".doc", ".docx", ".zip", ".html", ".htm")


def filter_by_url(candidates: List[ImageCandidate]) -> List[ImageCandidate]:
    result = []
    for candidate in candidates:
        url = candidate.image_url.lower()
        path = urlparse(candidate.image_url).path.lower()
        if any(keyword in url for keyword in EXCLUDE_URL_KEYWORDS):
            continue
        if path.endswith(KNOWN_NON_IMAGE_EXTENSIONS):
            continue
        result.append(candidate)
    return result


def score_candidates(candidates: List[ImageCandidate], product: Product) -> List[ImageCandidate]:
    article = product.article.lower()
    brand = product.brand.lower()
    for candidate in candidates:
        haystack = f"{candidate.title} {candidate.source_url} {candidate.image_url}".lower()
        score = 0
        if article and article in haystack:
            score += 2
        if brand and brand in haystack:
            score += 1
        if article and article in candidate.title.lower():
            score += 1
        candidate.score = score
    return candidates


def rank_candidates(candidates: List[ImageCandidate], product: Product):
    filtered = filter_by_url(candidates)
    scored = score_candidates(filtered, product)
    valid = [c for c in scored if c.score >= MIN_CANDIDATE_SCORE]
    if not valid:
        return [], False
    title_words = set(re.findall(r"[a-zа-я0-9]+", product.title.lower()))

    def token_hits(candidate):
        words = set(re.findall(r"[a-zа-я0-9]+", candidate.title.lower()))
        return len(title_words & words)

    valid.sort(key=lambda c: (c.score, token_hits(c)), reverse=True)
    top_score = valid[0].score
    tied = [c for c in valid if c.score == top_score]
    ambiguous = len(tied) > 1 and top_score < AMBIGUOUS_SCORE
    if ambiguous:
        logger.info("Ambiguous candidates for %s (score %s), marking REVIEW", product.article, top_score)
    return valid, ambiguous
