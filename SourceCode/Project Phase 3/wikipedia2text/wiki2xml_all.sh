#!/bin/sh
# Runs wiki2xml_command on all the files extracted by xmldump2files.py
#
# Evan Jones <evanj@mit.edu>
# April, 2008
# Released under a BSD licence.
# http://evanjones.ca/software/wikipedia2text.html

WIKI2XML="wiki2xml/php/wiki2xml_command.php"

if [ -z $1 ]; then
    echo wiki2xml_all.sh [directory]
    exit 1
fi

# "Infinite" loops can happen in the parser: limit it to 2 minutes per file
#ulimit -t 120

for i in `find $1 -type f | grep '\.txt$'`; do
    OUT=`echo $i | sed 's/\.txt/.xml/'`
    echo $i
    php $WIKI2XML $i $OUT
done
