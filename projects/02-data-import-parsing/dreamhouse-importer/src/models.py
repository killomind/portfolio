from dataclasses import dataclass, asdict, field
from typing import Any, Dict


@dataclass
class Product:
    article: str
    brand: str
    title: str
    price: float
    quantity: int
    category: str
    source: str = ""

    def to_dict(self) -> Dict[str, Any]:
        return asdict(self)
