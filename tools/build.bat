REM This script requires Windows 10 (17063) or later
ECHO OFF

REM Compress content of acptemplates directory
CD /d "%0\..\..\src\acptemplates\"
TAR -cf "%0\..\..\dist\acptemplates.tar" *

REM Compress content of files directory
CD /d "%0\..\..\src\files\"
TAR -cf "%0\..\..\dist\files.tar" *

REM Compress content of templates directory
CD /d "%0\..\..\src\templates\"
TAR -cf "%0\..\..\dist\templates.tar" *

REM Compress all prepared archives and the remaining files/folders from the root directory
CD /d "%0\..\..\dist\"
COPY "%0\..\..\src\acpMenu.xml" .
COPY "%0\..\..\src\menuItem.xml" .
COPY "%0\..\..\src\objectType.xml" .
COPY "%0\..\..\src\option.xml" .
COPY "%0\..\..\src\package.xml" .
COPY "%0\..\..\src\page.xml" .
COPY "%0\..\..\src\userGroupOption.xml" .
COPY "%0\..\..\src\userOption.xml" .
MKDIR "language" & XCOPY "%0\..\..\src\language" "language" /s /e
TAR -cf "%0\..\..\dist\de.berny23.igdb-integration.tar" "acptemplates.tar" "files.tar" "language" "templates.tar" "acpMenu.xml" "menuItem.xml" "objectType.xml" "option.xml" "package.xml" "page.xml" "userGroupOption.xml" "userOption.xml"

REM Remove temporary files and folders
DEL "acptemplates.tar" "files.tar" "templates.tar" "acpMenu.xml" "menuItem.xml" "objectType.xml" "option.xml" "package.xml" "page.xml" "userGroupOption.xml" "userOption.xml"
RMDIR /s /q "language"

ECHO Build finished.