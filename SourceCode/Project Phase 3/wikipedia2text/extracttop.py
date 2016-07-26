#!/usr/bin/python
# Exact titles from the Wikipedia top articles pages.
#
# Evan Jones <evanj@mit.edu>
# April, 2008
# Released under a BSD licence.
# http://evanjones.ca/software/wikipedia2text.html
#
# Top articles pages can be found at:
# http://en.wikipedia.org/wiki/Wikipedia:Release_Version
# http://en.wikipedia.org/wiki/Wikipedia:Version_1.0_Editorial_Team/Release_Version_articles_by_quality2

import re
import sys
import urllib

EXTRACT = re.compile(r'<td><a href="[^"]*/wiki/([^"]+)"')

for filename in sys.argv[1:]:
    input = open(filename)
    for line in input:
        match = EXTRACT.match(line)
        if match:
            # Convert escape sequences
            title = urllib.unquote(match.group(1))
            # Convert _ to " "
            print title.replace("_", " ")
