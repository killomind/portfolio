from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
INPUT_DIR = BASE_DIR / "input"
OUTPUT_DIR = BASE_DIR / "output"
IMAGES_DIR = BASE_DIR / "images"
LOGS_DIR = BASE_DIR / "logs"

SUPPORTED_EXTENSIONS = (".csv", ".xlsx", ".xml")

HTTP_TIMEOUT = 15.0
HTTP_MAX_RETRIES = 3
RETRY_BACKOFF = 1.5
REQUEST_DELAY = 1.5
MAX_DOWNLOAD_BYTES = 10 * 1024 * 1024
MIN_WIDTH = 500
MIN_HEIGHT = 500
MIN_BYTES = 2048

EXCLUDE_URL_KEYWORDS = (
    "favicon",
    "logo",
    "banner",
    "sprite",
    "/icons/",
    "spacer",
    "transparent",
    "placeholder",
)

ALLOWED_IMAGE_EXTENSIONS = (
    ".jpg",
    ".jpeg",
    ".png",
    ".gif",
    ".webp",
    ".bmp",
    ".svg",
    ".avif",
)

MAX_CANDIDATES = 10
MIN_CANDIDATE_SCORE = 2
AMBIGUOUS_SCORE = 4
MAX_DOWNLOAD_ATTEMPTS = 6

USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
)
