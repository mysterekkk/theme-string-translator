#!/bin/bash
# Build plugin zip for distribution
VERSION=$(grep "Version:" theme-string-translator.php | head -1 | awk '{print $NF}')
FILENAME="theme-string-translator-${VERSION}.zip"
echo "Building ${FILENAME}..."
cd ..
zip -r "${FILENAME}" theme-string-translator/ \
  -x "theme-string-translator/admin/src/*" \
  -x "theme-string-translator/node_modules/*" \
  -x "theme-string-translator/.git/*" \
  -x "theme-string-translator/package.json" \
  -x "theme-string-translator/package-lock.json" \
  -x "theme-string-translator/tsconfig.json" \
  -x "theme-string-translator/.gitignore" \
  -x "theme-string-translator/build-zip.sh"
mv "${FILENAME}" theme-string-translator/
echo "Done: theme-string-translator/${FILENAME}"
