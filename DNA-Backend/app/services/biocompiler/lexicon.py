"""Lexicon and normalisation for the natural-language front end.

Three languages share one lexicon rather than one parser each. A parser per
language would triple the grammar and let the three drift apart; here the
grammar is language-neutral and only the *words* are per-language, so a Kurdish
sentence and its Arabic translation compile to byte-identical DNA.
"""

from __future__ import annotations

import re
import unicodedata

# --------------------------------------------------------------------------
# Normalisation
# --------------------------------------------------------------------------

# Arabic-Indic and Extended Arabic-Indic digits, mapped to ASCII so that
# "٣٧ پلە" and "37 degrees" reach the number parser identically.
_DIGITS = {ord(c): str(i % 10) for i, c in enumerate("٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹")}

# Orthographic variants. Kurdish Sorani and Arabic keyboards produce different
# code points for visually identical letters; unified here so a lexicon entry
# does not need one spelling per keyboard layout.
_LETTERS = {
    "\u064a": "\u06cc",  # ARABIC YEH -> FARSI YEH
    "\u0649": "\u06cc",  # ALEF MAKSURA -> FARSI YEH
    "\u0643": "\u06a9",  # ARABIC KAF -> KEHEH
    "\u0623": "\u0627", "\u0625": "\u0627", "\u0622": "\u0627",  # hamzated alef
    "\u0629": "\u0647",  # TEH MARBUTA -> HEH
    "\u200c": " ", "\u200d": " ", "\u200e": " ", "\u200f": " ",  # zero-width marks
}

_DIACRITICS = re.compile(r"[\u064b-\u0652\u0670\u0640]")

# Arabic-script punctuation lives inside the Arabic Unicode block, so a naive
# "keep everything Arabic" filter keeps the comma and full stop too and glues
# them onto the last word of every sentence.
_PUNCTUATION = re.compile(r"[\u060c\u061b\u061f\u06d4\u066a-\u066d!?;:,()\[\]{}\"'\u00ab\u00bb\u201c\u201d]")

# A full stop is a sentence end unless it sits between two digits.
_SENTENCE_DOT = re.compile(r"(?<!\d)\.|\.(?!\d)")

_ARABIC_DEFINITE = "\u0627\u0644"  # "al-"
_WAW = "\u0648"


def normalise(text: str) -> str:
    text = unicodedata.normalize("NFKC", text)
    text = text.translate(_DIGITS)
    text = "".join(_LETTERS.get(ch, ch) for ch in text)
    text = _DIACRITICS.sub("", text)
    text = _PUNCTUATION.sub(" ", text)
    text = _SENTENCE_DOT.sub(" ", text)
    text = re.sub(r"[^\w\u0600-\u06ff.<>=]+", " ", text)
    return re.sub(r"\s+", " ", text).strip().lower()


def strip_definite(token: str) -> str:
    if token.startswith(_ARABIC_DEFINITE) and len(token) > 4:
        return token[2:]
    return token


def variants(token: str) -> set[str]:
    """Surface forms a single lexicon entry should match."""
    forms = {token, strip_definite(token)}
    return {form for form in forms if form}


def _known_words() -> frozenset[str]:
    words: set[str] = set()
    for table in (SENSORS, ACTUATORS, TERMINAL_ACTIONS, OPERATORS, CONNECTIVES, DURATION_UNITS):
        for phrases in table.values():
            for phrase in phrases:
                words.update(normalise(phrase).split(" "))
    words.update(normalise(" ".join(NEGATION + IF_MARKERS + THEN_MARKERS + TEMPERATURE_UNITS)).split(" "))
    return frozenset(w for w in words if w)


def tokenise(text: str) -> list[str]:
    """One token per word, with the Arabic conjunction split off when it is
    genuinely a conjunction.

    Arabic writes "and the lactose" as a single word, so the waw has to be
    separated or both the connective and the noun are lost. Splitting only when
    the remainder is a word the lexicon actually knows keeps the rule from
    mangling ordinary Kurdish words that happen to start with the same letter.
    """
    known = KNOWN_WORDS
    tokens: list[str] = []

    for raw in normalise(text).split(" "):
        if not raw:
            continue

        if raw.startswith(_WAW) and len(raw) > 3:
            remainder = raw[1:]
            if variants(remainder) & known:
                tokens.append(_WAW)
                tokens.append(remainder)
                continue

        tokens.append(raw)

    return tokens


# --------------------------------------------------------------------------
# Lexicon
#
# Every entry maps surface forms in three languages onto one symbol. Phrases are
# matched before single words, longest first, so "پرۆتینی سەوز" wins over the
# bare "پرۆتین".
# --------------------------------------------------------------------------

SENSORS: dict[str, list[str]] = {
    "temperature": [
        "پلەی گەرمی", "پلەی گەرما", "تەمپەراتوور", "گەرمی", "گەرما",
        "درجه الحراره", "درجه حراره", "الحراره", "حراره",
        "temperature", "temp", "heat",
    ],
    "lactose": [
        "شەکری لاکتۆز", "لاکتۆز", "لاكتوز", "لاكتوز", "lactose", "iptg",
    ],
    "arabinose": [
        "ئەرابینۆز", "ارابینوز", "arabinose", "arabinoz",
    ],
    "tetracycline": [
        "تیتراسایکلین", "تتراسيكلين", "تتراسایکلین", "tetracycline", "atc",
        "anhydrotetracycline",
    ],
    "oxygen": [
        "ئۆکسجین", "اکسجین", "اوکسجین", "oxygen", "anaerobic", "hypoxia",
    ],
    "quorum": [
        "چڕی خانە", "چری خانه", "کثافه الخلایا", "quorum", "cell density", "ahl",
    ],
    "ph_acid": [
        "ترشی", "الحموضه", "حموضه", "ph", "acidity", "acidic",
    ],
}

ACTUATORS: dict[str, list[str]] = {
    "gfp": [
        "پرۆتینی سەوز", "پرۆتینی سه‌وز", "پرۆتینێکی سەوز", "پرۆتینی سەوزی",
        "بروتین اخضر", "بروتینا اخضر", "البروتین الاخضر", "بروتین سبز",
        "green protein", "green fluorescent protein", "gfp",
    ],
    "rfp": [
        "پرۆتینی سوور", "پرۆتینێکی سوور", "بروتین احمر", "البروتین الاحمر",
        "red protein", "red fluorescent protein", "rfp",
    ],
    "yfp": [
        "پرۆتینی زەرد", "بروتین اصفر", "yellow protein", "yfp",
    ],
    "lacz": [
        "بێتا گالاکتۆزیدەیز", "بیتا جالاکتوزیداز", "lacz", "beta galactosidase",
    ],
}

TERMINAL_ACTIONS: dict[str, list[str]] = {
    "self_destruct": [
        "خۆت لەناوبەرە", "خوت لەناوبەرە", "خۆت له‌ناوبه‌ره‌", "لەناوبردنی خۆکار",
        "خۆکوژی", "خودکشی", "دمر نفسك", "التدمیر الذاتی", "الانتحار الخلوی",
        "self destruct", "selfdestruct", "kill switch", "killswitch", "apoptosis",
        "suicide",
    ],
}

# Comparison direction. `above` also covers "rose past", because a threshold
# crossing and a static comparison compile to the same sensor behaviour.
OPERATORS: dict[str, list[str]] = {
    "above": [
        "زیادی کرد", "زیاتر لە", "زیاتر له", "بەرزتر لە", "بەرز بووەوە", "لە سەرووی",
        "سەروو", "زیاد بوو", "بەرزبووەوە",
        "اکثر من", "تجاوزت", "تجاوز", "ارتفعت", "ارتفع", "فوق", "اعلی من", "زادت",
        "above", "over", "exceeds", "exceed", "greater than", "more than", "rises",
        "higher than", ">",
    ],
    "below": [
        "کەمتر لە", "کەمتر له", "نزمتر", "دابەزی", "لە خوارووی",
        "اقل من", "انخفضت", "انخفض", "تحت", "ادنی من",
        "below", "under", "less than", "lower than", "drops", "<",
    ],
    "equals": [
        "یەکسان", "بەرامبەر", "یساوی", "equals", "equal to", "=", "==",
    ],
}

NEGATION: list[str] = [
    "نەبوو", "نییە", "بەبێ", "بێ", "نەبێت", "ئەگەر نەبوو",
    "بدون", "لا یوجد", "غیاب", "لیس", "عدم", "بغیاب",
    "not", "without", "absent", "absence", "no",
]

CONNECTIVES: dict[str, list[str]] = {
    "and": ["و", "هەروەها", "لەگەڵ", "هەردووکیان", "مع", "وایضا", "and", "&&", "&"],
    "or": ["یان", "یاخود", "او", "اما", "or", "||"],
}

# Markers that separate the condition clause from the action clause.
IF_MARKERS = ["ئەگەر", "ئه‌گه‌ر", "کاتێک", "کاتیک", "اذا", "لو", "عندما", "if", "when", "whenever"]
THEN_MARKERS = [
    "ئەوا", "ئینجا", "دەربدە", "دەرببڕە", "بەرهەم بهێنە", "دروست بکە", "پاشان", "دواتر",
    "عندها", "فقم", "قم", "انتج", "اصدر", "ثم", "بعدها",
    "then", "produce", "express", "output", "emit", "make", "afterwards", "after that",
]

DURATION_UNITS: dict[str, list[str]] = {
    "hours": ["کاتژمێر", "کاتژمیر", "سەعات", "ساعه", "ساعات", "hour", "hours", "hr", "hrs", "h"],
    "minutes": ["خولەک", "خوله‌ک", "دقیقه", "دقایق", "minute", "minutes", "min", "mins"],
    "days": ["ڕۆژ", "روژ", "یوم", "ایام", "day", "days"],
    "seconds": ["چرکە", "ثانیه", "second", "seconds", "sec", "s"],
}

DURATION_SECONDS = {"seconds": 1, "minutes": 60, "hours": 3600, "days": 86400}

TEMPERATURE_UNITS = ["پلە", "پله", "سیلیزی", "درجه", "مئویه", "celsius", "degrees", "degree", "c", "°c"]

# Which language a sentence was written in, decided by which lexicon its tokens
# came from. Only used for reporting - parsing itself is language-neutral.
LANGUAGE_HINTS = {
    "ku": ["ئەگەر", "پلەی", "گەرمی", "پرۆتینی", "کاتژمێر", "دەربدە", "خۆت", "سەوز", "هەبوو"],
    "ar": ["اذا", "درجه", "الحراره", "بروتین", "ساعه", "دمر", "نفسك", "وجد", "اخضر"],
    "en": ["if", "temperature", "protein", "hours", "then", "destroy", "green", "present"],
}


def build_phrase_index(table: dict[str, list[str]]) -> list[tuple[list[str], str]]:
    """Return (token-sequence, symbol) pairs, longest phrase first.

    Matching on token sequences rather than raw substrings prevents "or" from
    matching inside "operator" and "و" from matching inside every Kurdish word
    that happens to contain it.
    """
    index: list[tuple[list[str], str]] = []

    for symbol, phrases in table.items():
        for phrase in phrases:
            tokens = normalise(phrase).split(" ")
            if tokens and tokens[0]:
                index.append((tokens, symbol))

    index.sort(key=lambda item: len(item[0]), reverse=True)
    return index


def build_phrase_list(phrases: list[str], symbol: str) -> list[tuple[list[str], str]]:
    return build_phrase_index({symbol: phrases})


def detect_language(tokens: list[str]) -> str:
    scores = {
        code: sum(1 for token in tokens if token in words)
        for code, words in LANGUAGE_HINTS.items()
    }
    best = max(scores, key=lambda code: scores[code])
    return best if scores[best] > 0 else "unknown"


KNOWN_WORDS = _known_words()
