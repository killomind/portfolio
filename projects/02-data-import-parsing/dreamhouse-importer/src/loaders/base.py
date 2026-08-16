from typing import Any, Dict, List


class BaseLoader:
    name = "base"

    def load(self, path) -> List[Dict[str, Any]]:
        raise NotImplementedError
