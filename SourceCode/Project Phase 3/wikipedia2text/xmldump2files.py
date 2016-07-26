#!/usr/bin/python
# Split a Wikipedia XML dump into individual files. The files are stored in a
# directory tree based on hashing the title of the article.
#
# Evan Jones <evanj@mit.edu>
# April, 2008
# Released under a BSD licence.
# http://evanjones.ca/software/wikipedia2text.html

import md5
import os
import sys
import urllib
import xml.sax

def writeArticle(root, title, text):
    # ~2.4 million articles at the moment
    # assuming an even distribution, we want 2 levels of 2 character directories:
    # 3 million / 256 / 256 = 46
    # Thus we won't have too many items in any directory

    title = title.encode("UTF-8")
    hash = md5.new(title).hexdigest()
    level1 = os.path.join(root, hash[0:2])
    level2 = os.path.join(level1, hash[2:4])

    # Wikipedia-ize the title for the file name
    title = title.replace(" ", "_")
    title = urllib.quote(title)
    # Special case for /: "%x" % ord("/") == 2f
    title = title.replace("/", "%2F")
    title += ".txt"
    print title
    filename = os.path.join(level2, title)

    if not os.path.exists(level1):
        os.mkdir(level1)
    if not os.path.exists(level2):
        os.mkdir(level2)
    out = open(filename, "w")
    out.write(text.encode("UTF-8"))
    out.close()


class WikiPageSplitter(xml.sax.ContentHandler):
    def __init__(self, root):
        self.root = root
        self.stack = []
        self.text = None
        self.title = None

    def startElement(self, name, attributes):
        #~ print "start", name
        if name == "page":
            assert self.stack == []
            self.text = None
            self.title = None
        elif name == "title":
            assert self.stack == ["page"]
            assert self.title is None
            self.title = ""
        elif name == "text":
            assert self.stack == ["page"]
            assert self.text is None
            self.text = ""
        else:
            assert len(self.stack) == 0 or self.stack[-1] == "page"
            return

        self.stack.append(name)

    def endElement(self, name):
        #~ print "end", name
        if len(self.stack) > 0 and name == self.stack[-1]:
            del self.stack[-1]
        if name == "text":
            # We have the complete article: write it out
            writeArticle(self.root, self.title, self.text)

    def characters(self, content):
        assert content is not None and len(content) > 0
        if len(self.stack) == 0:
            return

        if self.stack[-1] == "title":
            self.title += content
        elif self.stack[-1] == "text":
            assert self.title is not None
            self.text += content


xml.sax.parse(sys.argv[1], WikiPageSplitter(sys.argv[2]))
