#!/usr/bin/python
# Take a MediaWiki article in XML format, as produced by wiki2xml, and extract
# plain text. Uses the BeautifulSoup parser, since wiki2xml's output is not XML.
#
# Evan Jones <evanj@mit.edu>
# April, 2008
# Released under a BSD licence.
# http://evanjones.ca/software/wikipedia2text.html

import htmlentitydefs
import re

import BeautifulSoup

# By default, BeautifulStoneSoup doesn't allow nesting
class WikiSoup(BeautifulSoup.BeautifulStoneSoup):
    # Allow nesting most tags except <paragraph> and <heading>
    
    NESTABLE_TAGS = {
        # Forces a <heading> tag to pop back up to <article>.
        "heading": ["article"],

        "link": [],

        #Maybe only allow these under "link"?
        "target": [],
        "part": [],
        "trail": [],
    
        "extension": [],
        "template": [],
        "arg": [],

        "list": [],
        "listitem": [],

        "table": [],
        "tablerow": [],
        "tablecell": [],

        "bold": [],
        "italics": [],

        "sup": [],
        "sub": [],
        "preblock": [],
        "preline": [],
    }

    SELF_CLOSING_TAGS = { "space": None }

    def __init__(self, data):
        BeautifulSoup.BeautifulStoneSoup.__init__(self, data,
                convertEntities=BeautifulSoup.BeautifulStoneSoup.XHTML_ENTITIES)


# Set of plain text tags: we will extract text from inside these tags
PLAIN_TAGS = set([
    "bold",
    "italics",
    "sup",
    "sub",
    "preblock",
    "preline",

    "templatevar",  # Used for some quote templates
    "part",
])


def extractLinkText(linkNode):
    """Extract text from a <link> tag."""
    assert linkNode.name == "link"

    try:
        if len(linkNode.contents) == 0:
            # <link href="..." type="external" />
            return None

        first = linkNode.contents[0]
        if isinstance(first, BeautifulSoup.NavigableString):
            # <link href="..." type="external">text</link>
            assert linkNode["type"] == "external"
            # External links could contain tags such as <space />
            return "".join(extractText(linkNode))

        assert first.name == "target"
        # <target> can contain other tags, in particular <template>
        target_text = "".join(extractText(first))

        # Skip Image, Category and language links
        if ":" in target_text:
            return None

        # target part? trail?
        # <link><target>foo</target><part>words</part></link>
        # <link><target>foo</target><part>word</part><trail>s</trail></link>
        # <link><target>foo</target><trail>s</trail></link>
        assert len(linkNode.contents) <= 3
        text = None
        foundPart = False
        foundTrail = False
        for child in linkNode:
            assert not foundTrail
            if child.name == "target":
                # If the target contains more than one thing, then this is a bad link: extract nothing
                if len(child.contents) != 1:
                    return None
                assert text is None
                text = child.string
            elif child.name == "part":
                # Only take the first <part>. There should only be one, but sometimes users add more
                if foundPart:
                    continue
                assert text is not None
                foundPart = True
                # The <part> can have HTML tags like <part>77<sup>th</sup></part>
                # Or worse, <template>
                text = "".join(extractText(child))
                    
            elif child.name == "trail":
                assert text is not None
                foundTrail = True
                text += child.string
            else:
                assert False
        return text
    except:
        print linkNode
        raise

# All the tags that should be skipped
SKIP_TAGS = set([
    "template",
    "ref",
    "table",
    "tablerow", 
    "tablecell",
    "magic_variable",
    "list",
])
# All the extensions that should be skipped
SKIP_EXTENSIONS = set([
    "ref",
    "references",
    "imagemap",
    "gallery",
    "math",
    "hr",
    "timeline",
    "poem",
    "hiero",
])
INCLUDE_EXTENSIONS = set([
    "blockquote",
    "noinclude",
    "onlyinclude",
    "includeonly",
    "nowiki",
    "var",  # Variables: needed to understand math/physics
    "sarcasm",  # Incorrectly parsed <sarcasm> tags in the "Leet" article
])

def extractText(paragraph_node):
    """Returns text extracted from Wikipedia XML <paragraph> nodes."""
    text = []
    for child in paragraph_node:
        if isinstance(child, BeautifulSoup.NavigableString):
            text.append(child.string)
        elif child.name in SKIP_TAGS:
            # Skip the contents of templates, references and tables
            continue
        elif child.name == "extension":
            if len(child.contents) == 0:
                # If the extension is empty we don't care.
                continue
            name = child["extension_name"]
            if name in SKIP_EXTENSIONS:
                continue
            elif name in INCLUDE_EXTENSIONS:
                # Extract text from extensions which just include text
                text.extend(extractText(child))
            else:
                print child
                raise "Unknown extension"
        elif child.name == "link":
            extracted = extractLinkText(child)
            if extracted is not None:
                text.append(extracted)
        elif child.name == "space":
            assert len(child.contents) == 0
            text.append(" ")
        else:
            # Recursively extract text out of tags like <italics>
            if not (child.name in PLAIN_TAGS or child.name.startswith("xhtml")):
                print child
            assert child.name in PLAIN_TAGS or child.name.startswith("xhtml")
            text.extend(extractText(child))

    return text


# Stolen from scrape: http://zesty.ca/python/scrape.py
HTML_ENTITY = re.compile(r'&(#(\d+|x[\da-fA-F]+)|[\w.:-]+);?')
def HTMLDecode(text):
    """Decodes HTML entities in text."""

    def HTMLEntityReplace(match):
        entity = match.group(1)
        if entity.startswith('#x'):
            return unichr(int(entity[2:], 16))
        elif entity.startswith('#'):
            return unichr(int(entity[1:]))
        elif entity in htmlentitydefs.name2codepoint:
            return unichr(htmlentitydefs.name2codepoint[entity])
        else:
            return match.group(0)

    return HTML_ENTITY.sub(HTMLEntityReplace, text)


def extractWikipediaText(filename):
    """Extract text from a Wikipedia article in XML format. Returns Unicode text normalized in NFKC form."""

    input = open(filename)
    data = input.read()
    input.close()

    dom = WikiSoup(data)
    text = []
    # Iterate over the paragraphs
    for paragraph in dom.findAll("paragraph"):
        try:
            parts = extractText(paragraph)
        except:
            print paragraph
            raise
        for i, fragment in enumerate(parts):
            # wiki2xml does not convert &nbsp; or other HTML entities
            parts[i] = HTMLDecode(fragment)
        text.extend(parts)
        text.append("\n\n")

    return "".join(text)


if __name__ == "__main__":
    import sys
    sys.stdout.write(extractWikipediaText(sys.argv[1]).encode("UTF-8"))
