import unittest

from src.image_search import build_queries, filter_by_url, score_candidates
from src.models import Product


class TestQueries(unittest.TestCase):
    def test_build_queries(self):
        product = Product("SM58", "Shure", "Vocal Microphone", 120.0, 5, "Microphones")
        queries = build_queries(product)
        self.assertEqual(queries[0], "Shure SM58 product")
        self.assertEqual(queries[1], "Shure Vocal Microphone")

    def test_fallback_uses_title(self):
        product = Product("SM58", "Shure", "SM58", 120.0, 5, "Microphones")
        queries = build_queries(product)
        self.assertEqual(queries, ["Shure SM58 product", "Shure SM58"])


class TestFilter(unittest.TestCase):
    def test_excludes_favicon_logo(self):
        candidates = [
            _c("https://cdn.example.com/favicon.ico"),
            _c("https://cdn.example.com/logo.png"),
            _c("https://cdn.example.com/sprite.png"),
            _c("https://cdn.example.com/real/SM58.jpg"),
        ]
        result = filter_by_url(candidates)
        self.assertEqual(len(result), 1)
        self.assertIn("real/SM58.jpg", result[0].image_url)

    def test_excludes_non_image_extension(self):
        candidates = [_c("https://x.com/video.mp4"), _c("https://x.com/pic.jpg")]
        result = filter_by_url(candidates)
        self.assertEqual(len(result), 1)
        self.assertEqual(result[0].image_url, "https://x.com/pic.jpg")


class TestScoring(unittest.TestCase):
    def test_article_match_scores(self):
        product = Product("SM58", "Shure", "Microphone", 120.0, 1, "Mics")
        candidates = [
            _c("https://x.com/a.jpg", title="Shure SM58 microphone", source="https://shop.com/")
        ]
        scored = score_candidates(candidates, product)
        self.assertGreaterEqual(scored[0].score, 3)

    def test_no_match_scores_zero(self):
        product = Product("SM58", "Shure", "Microphone", 120.0, 1, "Mics")
        candidates = [_c("https://x.com/b.jpg", title="Random image", source="https://other.com/")]
        scored = score_candidates(candidates, product)
        self.assertEqual(scored[0].score, 0)


def _c(url, title="", source=""):
    from src.image_search import ImageCandidate

    return ImageCandidate(image_url=url, source_url=source, title=title)


if __name__ == "__main__":
    unittest.main()
