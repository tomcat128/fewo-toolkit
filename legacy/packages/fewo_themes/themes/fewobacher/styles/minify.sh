#!/bin/bash

rm -f basic.min.css custom.min.css fewobacher.min.css mobile.min.css all-in-one.min.css

wget --post-data="input=`cat basic.css`" --output-document=basic.min.css https://cssminifier.com/raw
wget --post-data="input=`cat custom.css`" --output-document=custom.min.css https://cssminifier.com/raw
wget --post-data="input=`cat mobile.css`" --output-document=mobile.min.css https://cssminifier.com/raw
wget --post-data="input=`cat fewobacher.css`" --output-document=fewobacher.min.css https://cssminifier.com/raw

cat basic.css custom.css mobile.css fewobacher.css > all-in-one.css

wget --post-data="input=`cat all-in-one.css`" --output-document=all-in-one.min.css https://cssminifier.com/raw

chown www-data:www-data all-in-one.min.css
