#!/usr/bin/env bash
set -e

# Resolve project directories relative to this script
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$SCRIPT_DIR/../src"
DIST_DIR="$SCRIPT_DIR/../dist"

mkdir -p "$DIST_DIR"

# Install dependencies and compile TypeScript to JavaScript
(cd "$SRC_DIR" && npm install && npx tsc --pretty)

# Compress content of acptemplates directory
(cd "$SRC_DIR/acptemplates" && tar -cf "$DIST_DIR/acptemplates.tar" -- *)

# Compress content of files directory
(cd "$SRC_DIR/files" && tar -cf "$DIST_DIR/files.tar" -- *)

# Compress content of templates directory
(cd "$SRC_DIR/templates" && tar -cf "$DIST_DIR/templates.tar" -- *)

# Compress all prepared archives and the remaining files/folders from the root directory
cd "$DIST_DIR"
cp "$SRC_DIR/acpMenu.xml" \
   "$SRC_DIR/menuItem.xml" \
   "$SRC_DIR/objectType.xml" \
   "$SRC_DIR/option.xml" \
   "$SRC_DIR/package.xml" \
   "$SRC_DIR/page.xml" \
   "$SRC_DIR/userGroupOption.xml" \
   "$SRC_DIR/userOption.xml" \
   "$SRC_DIR/userProfileMenu.xml" .
cp -r "$SRC_DIR/language" language

tar -cf "de.berny23.igdb-integration.tar" \
    "acptemplates.tar" "files.tar" "language" "templates.tar" \
    "acpMenu.xml" "menuItem.xml" "objectType.xml" "option.xml" \
    "package.xml" "page.xml" "userGroupOption.xml" "userOption.xml" \
    "userProfileMenu.xml"

# Remove temporary files and folders
rm -f "acptemplates.tar" "files.tar" "templates.tar" \
      "acpMenu.xml" "menuItem.xml" "objectType.xml" "option.xml" \
      "package.xml" "page.xml" "userGroupOption.xml" "userOption.xml" \
      "userProfileMenu.xml"
rm -rf language

echo "Build finished."