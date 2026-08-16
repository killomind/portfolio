import logging
import time
from pathlib import Path
from typing import Optional
from urllib.parse import urlparse

import requests
from PIL import Image

from .config import (
    HTTP_MAX_RETRIES,
    HTTP_TIMEOUT,
    IMAGES_DIR,
    MAX_DOWNLOAD_BYTES,
    MIN_BYTES,
    MIN_HEIGHT,
    MIN_WIDTH,
    REQUEST_DELAY,
    RETRY_BACKOFF,
    USER_AGENT,
)

logger = logging.getLogger("importer.download")

_CONTENT_TYPE_EXT = {
    "image/jpeg": ".jpg",
    "image/jpg": ".jpg",
    "image/png": ".png",
    "image/gif": ".gif",
    "image/webp": ".webp",
    "image/bmp": ".bmp",
    "image/svg+xml": ".svg",
    "image/x-icon": ".ico",
    "image/vnd.microsoft.icon": ".ico",
    "image/avif": ".avif",
}


class RobotsChecker:
    def __init__(self, session: requests.Session, limiter=None):
        self.session = session
        self.limiter = limiter
        self._cache = {}

    def allowed(self, url: str) -> bool:
        parsed = urlparse(url)
        host = parsed.netloc
        if host in self._cache:
            return self._check_path(self._cache[host], parsed.path or "/")
        rules = self._fetch(host)
        self._cache[host] = rules
        return self._check_path(rules, parsed.path or "/")

    def _fetch(self, host: str) -> list:
        robots_url = f"https://{host}/robots.txt"
        if self.limiter:
            self.limiter.wait()
        try:
            response = self.session.get(robots_url, timeout=HTTP_TIMEOUT)
            if response.status_code != 200:
                return []
            return _parse_robots(response.text)
        except requests.RequestException:
            return []

    def _check_path(self, rules: list, path: str) -> bool:
        for disallow in rules:
            if path.startswith(disallow):
                return False
        return True


def _parse_robots(text: str) -> list:
    rules = []
    in_star_group = False
    for line in text.splitlines():
        stripped = line.strip().lower()
        if not stripped or stripped.startswith("#"):
            continue
        if stripped.startswith("user-agent"):
            value = stripped.split(":", 1)[1].strip()
            in_star_group = value == "*"
            continue
        if in_star_group and stripped.startswith("disallow"):
            value = stripped.split(":", 1)[1].strip()
            if value:
                rules.append(value)
    return rules


class ImageDownloader:
    def __init__(self, session: Optional[requests.Session] = None, limiter=None):
        self.session = session or requests.Session()
        self.session.headers["User-Agent"] = USER_AGENT
        self.limiter = limiter
        self.robots = RobotsChecker(self.session, limiter)

    def download(self, url: str, dest_dir: Path = IMAGES_DIR, article: str = "") -> tuple:
        if self.limiter:
            self.limiter.wait()
        if not self.robots.allowed(url):
            return None, "blocked by robots.txt"
        try:
            response = self.session.get(url, timeout=HTTP_TIMEOUT, stream=True)
        except requests.RequestException as exc:
            return None, f"network error: {exc}"
        if response.status_code != 200:
            return None, f"http {response.status_code}"
        content_type = (response.headers.get("Content-Type") or "").lower().split(";")[0].strip()
        if not content_type.startswith("image/"):
            return None, f"content-type not image: {content_type}"
        length = response.headers.get("Content-Length")
        if length and int(length) > MAX_DOWNLOAD_BYTES:
            return None, f"too large: {length} bytes"
        content = b""
        try:
            for chunk in response.iter_content(chunk_size=65536):
                content += chunk
                if len(content) > MAX_DOWNLOAD_BYTES:
                    return None, "too large"
        except requests.RequestException as exc:
            return None, f"network error: {exc}"
        if len(content) < MIN_BYTES:
            return None, f"too small: {len(content)} bytes"
        try:
            image = Image.open(__import__("io").BytesIO(content))
            width, height = image.size
        except Exception:
            return None, "invalid image data"
        if width < MIN_WIDTH or height < MIN_HEIGHT:
            return None, f"resolution {width}x{height} < {MIN_WIDTH}x{MIN_HEIGHT}"
        extension = _CONTENT_TYPE_EXT.get(content_type, _extension_from_url(url))
        dest_dir.mkdir(parents=True, exist_ok=True)
        local_file = dest_dir / f"{article}{extension}"
        local_file.write_bytes(content)
        return str(local_file), "ok"


def _extension_from_url(url: str) -> str:
    path = urlparse(url).path.lower()
    from pathlib import Path as _P

    suffix = _P(path).suffix
    if suffix in (".jpg", ".jpeg", ".png", ".gif", ".webp", ".bmp", ".svg", ".avif"):
        return suffix
    return ".jpg"
